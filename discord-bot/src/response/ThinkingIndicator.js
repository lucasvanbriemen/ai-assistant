import { Readable } from 'stream';
import { readFileSync } from 'fs';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';
import { createLogger } from '../logger.js';

const log = createLogger('thinking');

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);
const CHIME_PATH = join(__dirname, '../../data/thinking-chime.ogg');

let chimeBuffer = null;

function getChimeBuffer() {
    if (!chimeBuffer) {
        try {
            chimeBuffer = readFileSync(CHIME_PATH);
            log.debug(`Loaded thinking chime: ${chimeBuffer.length} bytes`);
        } catch (err) {
            log.warn('Could not load thinking chime:', err.message);
        }
    }
    return chimeBuffer;
}

export class ThinkingIndicator {
    constructor(textChannel, voiceManager) {
        this.textChannel = textChannel;
        this.voiceManager = voiceManager;
        this.typingInterval = null;
    }

    /**
     * Start the thinking indicator:
     * - Play a short chime in the voice channel
     * - Start typing indicator in the text channel
     */
    async start() {
        // Start typing indicator (repeats every 8s, Discord typing lasts ~10s)
        this._startTyping();

        // Play chime in voice channel
        await this._playChime();
    }

    /**
     * Stop the thinking indicator.
     */
    stop() {
        if (this.typingInterval) {
            clearInterval(this.typingInterval);
            this.typingInterval = null;
        }
    }

    _startTyping() {
        // Send typing indicator immediately, then repeat every 8 seconds
        this.textChannel.sendTyping().catch(() => {});
        this.typingInterval = setInterval(() => {
            this.textChannel.sendTyping().catch(() => {});
        }, 8_000);
    }

    async _playChime() {
        const buf = getChimeBuffer();
        if (!buf) return;

        try {
            const stream = Readable.from(buf);
            await this.voiceManager.playAudio(stream);
            log.debug('Thinking chime played');
        } catch (err) {
            log.warn('Failed to play thinking chime:', err.message);
        }
    }
}
