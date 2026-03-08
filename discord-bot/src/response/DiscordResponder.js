import { Readable } from 'stream';
import { createLogger } from '../logger.js';

const log = createLogger('responder');

export class DiscordResponder {
    constructor(textChannel) {
        this.textChannel = textChannel;
        this.responseQueue = [];
        this.isProcessing = false;
    }

    async respond(text, ttsEngine, voiceManager) {
        this.responseQueue.push({ text, ttsEngine, voiceManager });
        if (!this.isProcessing) {
            await this._processQueue();
        }
    }

    async _processQueue() {
        this.isProcessing = true;

        while (this.responseQueue.length > 0) {
            const { text, ttsEngine, voiceManager } = this.responseQueue.shift();
            try {
                await this._sendResponse(text, ttsEngine, voiceManager);
            } catch (err) {
                log.error('Failed to send response:', err);
            }
        }

        this.isProcessing = false;
    }

    async _sendResponse(text, ttsEngine, voiceManager) {
        // Send text to text channel and TTS to voice channel in parallel
        const results = await Promise.allSettled([
            this._sendTextMessage(text),
            this._playTTS(text, ttsEngine, voiceManager),
        ]);

        for (const result of results) {
            if (result.status === 'rejected') {
                log.error('Response delivery failed:', result.reason);
            }
        }
    }

    async _sendTextMessage(text) {
        try {
            // Discord message limit is 2000 chars
            if (text.length <= 2000) {
                await this.textChannel.send(text);
            } else {
                const chunks = [];
                for (let i = 0; i < text.length; i += 2000) {
                    chunks.push(text.substring(i, i + 2000));
                }
                for (const chunk of chunks) {
                    await this.textChannel.send(chunk);
                }
            }
            log.info('Text message sent to Discord channel');
        } catch (err) {
            log.error('Failed to send text message:', err.message);
        }
    }

    async _playTTS(text, ttsEngine, voiceManager) {
        try {
            log.info('Generating TTS audio...');
            const audioBuffer = await ttsEngine.synthesize(text);
            if (!audioBuffer) {
                log.warn('TTS returned no audio');
                return;
            }

            log.info(`TTS audio generated (${audioBuffer.length} bytes), playing in voice channel...`);
            const stream = Readable.from(audioBuffer);
            await voiceManager.playAudio(stream);
            log.info('TTS audio playback complete');
        } catch (err) {
            log.error('Failed to play TTS:', err.message);
        }
    }
}
