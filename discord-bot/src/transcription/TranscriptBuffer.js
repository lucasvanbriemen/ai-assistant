import { createLogger } from '../logger.js';

const log = createLogger('transcript-buffer');

export class TranscriptBuffer {
    constructor(bufferMinutes = 10) {
        this.bufferMinutes = bufferMinutes;
        this.entries = [];
    }

    add(text, language = null) {
        this.entries.push({
            text,
            language,
            timestamp: Date.now(),
        });
        this._prune();
    }

    getRecent(minutes = null) {
        const lookbackMs = (minutes || this.bufferMinutes) * 60 * 1000;
        const cutoff = Date.now() - lookbackMs;
        return this.entries.filter((e) => e.timestamp >= cutoff);
    }

    getRecentText(minutes = null) {
        return this.getRecent(minutes)
            .map((e) => e.text)
            .join(' ');
    }

    _prune() {
        const cutoff = Date.now() - this.bufferMinutes * 60 * 1000;
        this.entries = this.entries.filter((e) => e.timestamp >= cutoff);
    }

    get length() {
        return this.entries.length;
    }

    clear() {
        this.entries = [];
    }
}
