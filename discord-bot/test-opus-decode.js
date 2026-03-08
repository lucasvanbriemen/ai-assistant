/**
 * Test: Verify OpusScript decode works correctly.
 * Encode known PCM audio with Opus, decode it back, convert to WAV, transcribe.
 * This tests the exact same decode path the bot uses.
 */
import 'dotenv/config';
import OpusScript from 'opusscript';
import { spawn } from 'child_process';
import ffmpegPath from 'ffmpeg-static';
import fs from 'fs';
import OpenAI from 'openai';

const openai = new OpenAI({ apiKey: process.env.OPENAI_API_KEY });

const SAMPLE_RATE = 48000;
const CHANNELS = 2;
const FRAME_DURATION_MS = 20;
const FRAME_SIZE = (SAMPLE_RATE * FRAME_DURATION_MS) / 1000; // 960 samples per frame

async function main() {
    console.log('=== TEST: OpusScript encode/decode round-trip ===\n');

    // Step 1: Generate known audio with TTS (WAV format at 48kHz stereo to match Discord)
    console.log('[1] Generating TTS audio...');
    const ttsResponse = await openai.audio.speech.create({
        model: 'tts-1',
        voice: 'onyx',
        input: 'Hey Prime, what is two plus two times four minus seven?',
        response_format: 'wav',
    });
    const ttsWav = Buffer.from(await ttsResponse.arrayBuffer());
    fs.writeFileSync('data/test-tts-original.wav', ttsWav);
    console.log(`   TTS WAV: ${ttsWav.length} bytes`);

    // Step 2: Convert to 48kHz stereo s16le PCM (same format Discord uses)
    console.log('[2] Converting to 48kHz stereo PCM...');
    const pcm48k = await ffmpegConvert(ttsWav, [
        '-f', 'wav', '-i', 'pipe:0',
        '-ar', String(SAMPLE_RATE), '-ac', String(CHANNELS),
        '-f', 's16le', 'pipe:1',
    ]);
    console.log(`   PCM 48kHz stereo: ${pcm48k.length} bytes`);

    // Step 3: Encode PCM to Opus frames (simulating what Discord sends)
    console.log('[3] Encoding to Opus frames...');
    const encoder = new OpusScript(SAMPLE_RATE, CHANNELS, OpusScript.Application.AUDIO);
    const frameSizeBytes = FRAME_SIZE * CHANNELS * 2; // 960 * 2 * 2 = 3840 bytes per frame
    const opusFrames = [];

    for (let offset = 0; offset + frameSizeBytes <= pcm48k.length; offset += frameSizeBytes) {
        const frame = pcm48k.subarray(offset, offset + frameSizeBytes);
        const encoded = encoder.encode(frame, FRAME_SIZE);
        opusFrames.push(Buffer.from(encoded));
    }
    console.log(`   Encoded ${opusFrames.length} Opus frames (avg ${(opusFrames.reduce((s, f) => s + f.length, 0) / opusFrames.length).toFixed(0)} bytes each)`);

    // Step 4: Decode Opus frames back to PCM (same as the bot does)
    console.log('[4] Decoding Opus back to PCM (OpusScript)...');
    const decoder = new OpusScript(SAMPLE_RATE, CHANNELS, OpusScript.Application.AUDIO);
    const pcmChunks = [];
    let decodeErrors = 0;

    for (const opusFrame of opusFrames) {
        try {
            const pcm = decoder.decode(opusFrame);
            pcmChunks.push(Buffer.from(pcm.buffer));
        } catch (e) {
            decodeErrors++;
        }
    }
    const decodedPcm = Buffer.concat(pcmChunks);
    console.log(`   Decoded PCM: ${decodedPcm.length} bytes (${decodeErrors} errors)`);

    // Check RMS
    let sumSquares = 0;
    for (let i = 0; i < decodedPcm.length - 1; i += 2) {
        const sample = decodedPcm.readInt16LE(i);
        sumSquares += sample * sample;
    }
    const rms = Math.sqrt(sumSquares / (decodedPcm.length / 2));
    console.log(`   RMS energy: ${rms.toFixed(0)}`);

    // Step 5: Convert decoded PCM to 16kHz mono WAV (same as bot does for Whisper)
    console.log('[5] Converting to 16kHz mono WAV...');
    const wavBuffer = await ffmpegConvert(decodedPcm, [
        '-f', 's16le', '-ar', String(SAMPLE_RATE), '-ac', String(CHANNELS),
        '-i', 'pipe:0',
        '-ar', '16000', '-ac', '1', '-f', 'wav', 'pipe:1',
    ]);
    fs.writeFileSync('data/test-opus-roundtrip.wav', wavBuffer);
    console.log(`   WAV: ${wavBuffer.length} bytes (saved to data/test-opus-roundtrip.wav)`);

    // Step 6: Transcribe with Whisper
    console.log('[6] Transcribing with Whisper...');
    const file = new File([wavBuffer], 'test.wav', { type: 'audio/wav' });
    const result = await openai.audio.transcriptions.create({
        model: 'whisper-1',
        file,
        response_format: 'verbose_json',
        prompt: 'Hey Prime,',
    });
    console.log(`   Result: "${result.text}"`);
    console.log(`   Language: ${result.language}`);

    // Step 7: Also transcribe the original TTS WAV for comparison
    console.log('\n[7] Transcribing ORIGINAL TTS WAV for comparison...');
    const origFile = new File([ttsWav], 'orig.wav', { type: 'audio/wav' });
    const origResult = await openai.audio.transcriptions.create({
        model: 'whisper-1',
        file: origFile,
        response_format: 'verbose_json',
        prompt: 'Hey Prime,',
    });
    console.log(`   Result: "${origResult.text}"`);

    console.log('\n=== DONE ===');
}

function ffmpegConvert(input, args) {
    return new Promise((resolve, reject) => {
        const ffmpeg = spawn(ffmpegPath, args, { stdio: ['pipe', 'pipe', 'pipe'] });
        const chunks = [];
        ffmpeg.stdout.on('data', c => chunks.push(c));
        ffmpeg.stderr.on('data', () => {});
        ffmpeg.on('close', code => code === 0 ? resolve(Buffer.concat(chunks)) : reject(new Error(`ffmpeg: ${code}`)));
        ffmpeg.on('error', reject);
        ffmpeg.stdin.write(input);
        ffmpeg.stdin.end();
    });
}

main().catch(console.error);
