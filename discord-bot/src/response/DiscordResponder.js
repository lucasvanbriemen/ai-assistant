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

    _sanitizeForSpeech(text) {
        return text
            .replace(/```[\s\S]*?```/g, '')       // Remove code blocks
            .replace(/`([^`]+)`/g, '$1')           // Unwrap inline code
            .replace(/\*\*([^*]+)\*\*/g, '$1')     // Remove bold
            .replace(/\*([^*]+)\*/g, '$1')         // Remove italic
            .replace(/__([^_]+)__/g, '$1')         // Remove underline bold
            .replace(/_([^_]+)_/g, '$1')           // Remove underline italic
            .replace(/~~([^~]+)~~/g, '$1')         // Remove strikethrough
            .replace(/^#{1,6}\s+/gm, '')           // Remove heading markers
            .replace(/^[-*+]\s+/gm, '')            // Remove list markers
            .replace(/^\d+\.\s+/gm, '')            // Remove numbered list markers
            .replace(/!?\[([^\]]*)\]\([^)]+\)/g, '$1') // Remove links/images, keep label
            .replace(/\n{2,}/g, '\n')              // Collapse multiple newlines
            .trim();
    }

    async _playTTS(text, ttsEngine, voiceManager) {
        try {
            const speechText = this._sanitizeForSpeech(text);
            const audioBuffer = await ttsEngine.synthesize(speechText);
            if (!audioBuffer) {
                return;
            }

            const stream = Readable.from(audioBuffer);
            await voiceManager.playAudio(stream);
        } catch (err) {
        }
    }
}
