// Load libsodium before @discordjs/voice discovers encryption libs
import { createRequire } from 'module';
const require = createRequire(import.meta.url);
const sodium = require('libsodium-wrappers');
await sodium.ready;

import { Client, GatewayIntentBits, Events } from 'discord.js';
import config from './config.js';
import { createLogger } from './logger.js';
import { VoiceManager } from './voice/VoiceManager.js';
import { AudioReceiver } from './voice/AudioReceiver.js';
import { AudioBuffer } from './voice/AudioBuffer.js';
import { WhisperClient } from './transcription/WhisperClient.js';
import { TranscriptStore } from './transcription/TranscriptStore.js';
import { TranscriptBuffer } from './transcription/TranscriptBuffer.js';
import { WakeWordDetector } from './detection/WakeWordDetector.js';
import { CommandParser } from './ai/CommandParser.js';
import { PrimeClient } from './ai/PrimeClient.js';
import { TTSEngine } from './response/TTSEngine.js';
import { DiscordResponder } from './response/DiscordResponder.js';
import { ThinkingIndicator } from './response/ThinkingIndicator.js';

const log = createLogger('main');

const client = new Client({
    intents: [
        GatewayIntentBits.Guilds,
        GatewayIntentBits.GuildVoiceStates,
        GatewayIntentBits.GuildMessages,
        GatewayIntentBits.MessageContent,
    ],
});

// Initialize components
const transcriptStore = new TranscriptStore();
const transcriptBuffer = new TranscriptBuffer(config.transcript.bufferMinutes);
const whisperClient = new WhisperClient(config.openai.apiKey);
const wakeWordDetector = new WakeWordDetector();
const commandParser = new CommandParser(transcriptBuffer);
const primeClient = new PrimeClient(config.laravel.apiUrl, config.laravel.agentToken);
const ttsEngine = new TTSEngine(config.openai.apiKey, config.tts.voice);

let voiceManager = null;
let audioReceiver = null;
let responder = null;
let currentSessionId = null;

// Conversation mode: after a wake word, keep listening for follow-ups without requiring wake word
const CONVERSATION_TIMEOUT_MS = 30_000; // 30 seconds of follow-up window
let conversationActive = false;
let conversationTimer = null;
let conversationHistory = []; // Maintain multi-turn history during conversation

client.once(Events.ClientReady, async (readyClient) => {
    log.info(`Logged in as ${readyClient.user.tag}`);

    const guild = readyClient.guilds.cache.get(config.discord.guildId);
    if (!guild) {
        log.error(`Guild ${config.discord.guildId} not found`);
        process.exit(1);
    }

    const voiceChannel = guild.channels.cache.get(config.discord.voiceChannelId);
    if (!voiceChannel) {
        log.error(`Voice channel ${config.discord.voiceChannelId} not found`);
        process.exit(1);
    }

    const textChannel = guild.channels.cache.get(config.discord.textChannelId);
    if (!textChannel) {
        log.error(`Text channel ${config.discord.textChannelId} not found`);
        process.exit(1);
    }

    responder = new DiscordResponder(textChannel);
    voiceManager = new VoiceManager(voiceChannel);
    const connection = await voiceManager.join();

    // Wait for async SQLite init
    await transcriptStore.ready();

    currentSessionId = `session-${Date.now()}`;
    transcriptStore.createSession(currentSessionId, config.discord.guildId, config.discord.voiceChannelId);

    audioReceiver = new AudioReceiver(connection);
    const audioBuffer = new AudioBuffer(config.audio);

    // Wire up the audio pipeline
    audioReceiver.on('audio', (data) => {
        audioBuffer.push(data);
    });

    audioBuffer.on('segment', async (wavBuffer, durationMs) => {
        await handleAudioSegment(wavBuffer, durationMs);
    });

    audioReceiver.start();
    log.info('Audio pipeline active — listening for speech');
});

async function handleAudioSegment(wavBuffer, durationMs) {
    try {
        const result = await whisperClient.transcribe(wavBuffer);
        if (!result || !result.text || result.text.trim().length === 0) {
            return;
        }

        const text = result.text.trim();
        const language = result.language || null;

        log.info(`Transcribed (${language}): "${text}"`);

        // Store in DB and in-memory buffer
        transcriptStore.addTranscript({
            guildId: config.discord.guildId,
            channelId: config.discord.voiceChannelId,
            speaker: 'room',
            text,
            language,
            confidence: result.confidence || null,
            audioDurationMs: durationMs,
            startedAt: new Date().toISOString(),
            sessionId: currentSessionId,
        });

        transcriptBuffer.add(text, language);

        // Check for wake word
        const detection = wakeWordDetector.detect(text);
        if (detection) {
            log.info(`Wake word detected: type=${detection.type}, command="${detection.command}"`);
            await handleCommand(detection, text);
        } else if (conversationActive) {
            // In conversation mode — treat any speech as a follow-up command
            log.info(`Conversation follow-up: "${text}"`);
            const followUp = { type: 'inline', command: text, fullMatch: text };
            await handleCommand(followUp, text);
        }
    } catch (err) {
        log.error('Error processing audio segment:', err);
    }
}

async function handleCommand(detection, fullText) {
    const thinking = new ThinkingIndicator(responder.textChannel, voiceManager);

    try {
        // Start thinking indicator (chime + typing) immediately
        await thinking.start();

        let history;

        if (conversationActive && conversationHistory.length > 0) {
            // Continue existing conversation — append the new user message
            history = [
                ...conversationHistory,
                { role: 'user', text: detection.command || fullText },
            ];
        } else {
            // New conversation — use CommandParser for context assembly
            const context = commandParser.assemble(detection, fullText);
            history = context.history;

            transcriptStore.addCommand({
                sessionId: currentSessionId,
                triggerType: context.type,
                triggerText: fullText,
                contextText: context.contextText || null,
                requestedBy: 'room',
            });
        }

        log.info(`Sending to Prime (conversation=${conversationActive})`);
        const result = await primeClient.chat(history);

        // Stop thinking indicator before responding
        thinking.stop();

        if (!result || !result.response) {
            log.warn('Empty response from Prime');
            return;
        }

        log.info(`Prime response: "${result.response.substring(0, 100)}..."`);

        // Update conversation history for multi-turn
        conversationHistory = [
            ...history,
            { role: 'assistant', text: result.response },
        ];

        // Update command status
        transcriptStore.updateCommandResponse(fullText, result.response);

        // Respond via TTS + text channel — wait for it to finish before starting conversation timer
        await responder.respond(result.response, ttsEngine, voiceManager);

        // Start/reset conversation mode AFTER TTS finishes, so the user has 30s from hearing the response
        startConversationMode();
    } catch (err) {
        thinking.stop();
        log.error('Error handling command:', err);
    }
}

function startConversationMode() {
    conversationActive = true;
    if (conversationTimer) {
        clearTimeout(conversationTimer);
    }
    conversationTimer = setTimeout(() => {
        log.info('Conversation mode ended (timeout)');
        conversationActive = false;
        conversationHistory = [];
        conversationTimer = null;
    }, CONVERSATION_TIMEOUT_MS);
}

// Graceful shutdown
function shutdown() {
    log.info('Shutting down...');
    if (currentSessionId) {
        transcriptStore.endSession(currentSessionId);
    }
    if (voiceManager) {
        voiceManager.leave();
    }
    client.destroy();
    process.exit(0);
}

process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);
process.on('unhandledRejection', (err) => {
    log.error('Unhandled rejection:', err);
});

client.login(config.discord.token);
