/**
 * Test: Capture raw Discord audio, save the WAV, and transcribe it.
 * This isolates the Discord audio → Opus decode → WAV → Whisper pipeline.
 */
import { createRequire } from 'module';
const require = createRequire(import.meta.url);
const sodium = require('libsodium-wrappers');
await sodium.ready;

import 'dotenv/config';
import { Client, GatewayIntentBits, Events } from 'discord.js';
import { joinVoiceChannel, VoiceConnectionStatus, entersState, EndBehaviorType } from '@discordjs/voice';
import OpusScript from 'opusscript';
import { spawn } from 'child_process';
import ffmpegPath from 'ffmpeg-static';
import fs from 'fs';
import OpenAI from 'openai';

const openai = new OpenAI({ apiKey: process.env.OPENAI_API_KEY });

const SAMPLE_RATE = 48000;
const CHANNELS = 2;

const client = new Client({
    intents: [GatewayIntentBits.Guilds, GatewayIntentBits.GuildVoiceStates],
});

client.once(Events.ClientReady, async (c) => {
    console.log(`Logged in as ${c.user.tag}`);

    const guild = c.guilds.cache.get(process.env.DISCORD_GUILD_ID);
    const vc = guild.channels.cache.get(process.env.DISCORD_VOICE_CHANNEL_ID);

    const conn = joinVoiceChannel({
        channelId: vc.id,
        guildId: guild.id,
        adapterCreator: guild.voiceAdapterCreator,
        selfDeaf: false,
        selfMute: false,
    });

    await entersState(conn, VoiceConnectionStatus.Ready, 30_000);
    console.log(`Joined ${vc.name}`);
    console.log('');
    console.log('>>> SPEAK NOW! Recording for 8 seconds... <<<');
    console.log('');

    const receiver = conn.receiver;
    const decoder = new OpusScript(SAMPLE_RATE, CHANNELS, OpusScript.Application.AUDIO);
    const opusPackets = [];
    const pcmChunks = [];

    // Collect ALL packets for 8 seconds
    receiver.speaking.on('start', (userId) => {
        console.log(`User ${userId} started speaking`);
        const stream = receiver.subscribe(userId, {
            end: { behavior: EndBehaviorType.AfterSilence, duration: 5000 },
        });

        stream.on('data', (packet) => {
            opusPackets.push(packet);
            try {
                const pcm = decoder.decode(packet);
                pcmChunks.push(Buffer.from(pcm.buffer));
            } catch (e) {
                // skip decode errors
            }
        });
    });

    // Wait 20 seconds
    await new Promise(r => setTimeout(r, 20000));

    console.log(`\nCollected ${opusPackets.length} opus packets`);
    console.log(`PCM chunks: ${pcmChunks.length}`);

    if (pcmChunks.length === 0) {
        console.log('ERROR: No audio captured! Make sure you are speaking in the voice channel.');
        conn.destroy();
        client.destroy();
        process.exit(1);
    }

    const pcmBuffer = Buffer.concat(pcmChunks);
    console.log(`Total PCM: ${pcmBuffer.length} bytes`);

    // Check RMS
    let sumSquares = 0;
    for (let i = 0; i < pcmBuffer.length - 1; i += 2) {
        const sample = pcmBuffer.readInt16LE(i);
        sumSquares += sample * sample;
    }
    const rms = Math.sqrt(sumSquares / (pcmBuffer.length / 2));
    console.log(`PCM RMS energy: ${rms.toFixed(0)}`);

    // Save raw PCM for debugging
    fs.writeFileSync('data/test-raw.pcm', pcmBuffer);
    console.log('Saved raw PCM to data/test-raw.pcm');

    // Convert to WAV via ffmpeg
    const wavBuffer = await new Promise((resolve, reject) => {
        const ffmpeg = spawn(ffmpegPath, [
            '-f', 's16le', '-ar', String(SAMPLE_RATE), '-ac', String(CHANNELS),
            '-i', 'pipe:0',
            '-ar', '16000', '-ac', '1', '-f', 'wav', 'pipe:1',
        ], { stdio: ['pipe', 'pipe', 'pipe'] });

        const chunks = [];
        ffmpeg.stdout.on('data', c => chunks.push(c));
        ffmpeg.stderr.on('data', () => {});
        ffmpeg.on('close', code => code === 0 ? resolve(Buffer.concat(chunks)) : reject(new Error(`ffmpeg: ${code}`)));
        ffmpeg.stdin.write(pcmBuffer);
        ffmpeg.stdin.end();
    });

    // Save WAV
    fs.writeFileSync('data/test-capture.wav', wavBuffer);
    console.log(`Saved WAV to data/test-capture.wav (${wavBuffer.length} bytes)`);

    // Transcribe with Whisper
    console.log('\nTranscribing with Whisper...');
    const file = new File([wavBuffer], 'test.wav', { type: 'audio/wav' });
    const result = await openai.audio.transcriptions.create({
        model: 'whisper-1',
        file,
        response_format: 'verbose_json',
        prompt: 'Hey Prime,',
    });

    console.log(`Whisper result: "${result.text}"`);
    console.log(`Language: ${result.language}`);
    if (result.segments) {
        for (const seg of result.segments) {
            console.log(`  [${seg.start.toFixed(1)}s-${seg.end.toFixed(1)}s] "${seg.text}" (no_speech=${seg.no_speech_prob?.toFixed(2)})`);
        }
    }

    conn.destroy();
    client.destroy();
    process.exit(0);
});

client.login(process.env.DISCORD_TOKEN);
