import { Readable } from 'stream';

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
        } catch (err) {
        }
    }

    async _playTTS(text, ttsEngine, voiceManager) {
        try {
            const audioBuffer = await ttsEngine.synthesize(text);
            if (!audioBuffer) {
                return;
            }

            const stream = Readable.from(audioBuffer);
            await voiceManager.playAudio(stream);
        } catch (err) {
        }
    }
}
