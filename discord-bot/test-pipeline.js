/**
 * Test script: verifies Whisper transcription, TTS, wake word detection,
 * and Laravel API independently — no Discord connection needed.
 */
import 'dotenv/config';
import OpenAI from 'openai';
import fs from 'fs';
import { WakeWordDetector } from './src/detection/WakeWordDetector.js';

const openai = new OpenAI({ apiKey: process.env.OPENAI_API_KEY });
const detector = new WakeWordDetector();

async function testWhisperWithTTS() {
    console.log('\n=== TEST 1: Generate TTS audio, then transcribe with Whisper ===\n');

    // Step 1: Generate a known audio clip using TTS
    const phrases = [
        'Hey Prime, what is two plus two?',
        'Hey Prime, summarize the last five minutes.',
        'Prime, what time is it?',
    ];

    for (const phrase of phrases) {
        console.log(`[TTS] Generating: "${phrase}"`);
        const ttsResponse = await openai.audio.speech.create({
            model: 'tts-1',
            voice: 'onyx',
            input: phrase,
            response_format: 'wav',
        });

        const arrayBuffer = await ttsResponse.arrayBuffer();
        const wavBuffer = Buffer.from(arrayBuffer);
        console.log(`[TTS] Generated ${wavBuffer.length} bytes`);

        // Step 2: Transcribe the TTS audio with Whisper
        const file = new File([wavBuffer], 'test.wav', { type: 'audio/wav' });
        const whisperResponse = await openai.audio.transcriptions.create({
            model: 'whisper-1',
            file,
            response_format: 'verbose_json',
            prompt: 'Hey Prime,',
        });

        console.log(`[Whisper] Transcribed: "${whisperResponse.text}"`);
        console.log(`[Whisper] Language: ${whisperResponse.language}`);

        // Step 3: Test wake word detection on the transcription
        const detection = detector.detect(whisperResponse.text);
        console.log(`[WakeWord] Detection: ${detection ? `type=${detection.type}, command="${detection.command}"` : 'none'}`);
        console.log('');
    }
}

async function testLaravelAPI() {
    console.log('\n=== TEST 2: Laravel /api/prime/chat endpoint ===\n');

    const url = `${process.env.LARAVEL_API_URL || 'http://127.0.0.1:8000'}/api/prime/chat`;
    console.log(`[API] POST ${url}`);

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${process.env.LARAVEL_AGENT_TOKEN}`,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                history: [{ role: 'user', text: 'What is 2 + 2 x 4 - 7?' }],
            }),
        });

        if (!response.ok) {
            console.log(`[API] ERROR: ${response.status} ${await response.text()}`);
            return;
        }

        const data = await response.json();
        console.log(`[API] Response: "${data.response}"`);
        console.log(`[API] Used tools: ${data.used_tools?.join(', ') || 'none'}`);
    } catch (err) {
        console.log(`[API] Connection failed: ${err.message}`);
    }
}

async function testWakeWordDetector() {
    console.log('\n=== TEST 3: Wake word detection patterns ===\n');

    const cases = [
        'Hey Prime, what is two plus two?',
        'Hee Prime, can you help me?',
        'Prime, summarize the last 5 minutes.',
        'Prime, what time is it?',
        'Hey prime tell me a joke',
        'Hello everyone, welcome to the meeting.',
        'I was talking about prime numbers.',
        'Hey friend, can you help?',
    ];

    for (const text of cases) {
        const detection = detector.detect(text);
        const result = detection
            ? `DETECTED type=${detection.type}, command="${detection.command}"`
            : 'no match';
        console.log(`  "${text}" → ${result}`);
    }
}

// Run all tests
console.log('Starting pipeline tests...');
await testWakeWordDetector();
await testLaravelAPI();
await testWhisperWithTTS();
console.log('\n=== ALL TESTS COMPLETE ===');
