import OpenAI from 'openai';
import { createLogger } from '../logger.js';

const log = createLogger('whisper');

const MAX_CONCURRENT = 5;
const MAX_RETRIES = 3;
const BASE_DELAY_MS = 1000;

// Prompt biases Whisper toward recognizing "Prime" as the wake word.
// Keep it minimal — extra words leak into transcriptions as hallucinations.
const WHISPER_PROMPT = 'Hey Prime,';

// Errors that should not be retried
const NON_RETRYABLE_CODES = ['audio_too_short', 'audio_too_long'];

// Known Whisper hallucinations on silent/noisy audio
const HALLUCINATION_PATTERNS = [
    /^\.{2,}$/,                              // "..." or "......"
    /untertitel/i,                           // "Untertitel der Amara.org-Community"
    /amara\.org/i,
    /^(he[,.\s]*)+$/i,                      // "He, He, He, He" repeated
    /^(pr+[aeiou]*[,.\s]*)+$/i,             // "Prrrr" noise
    /^.{0,3}$/,                             // 3 chars or less
    /ondertiteling/i,                       // Dutch subtitle hallucination
    /sous-titres/i,                         // French subtitle hallucination
    /copyright/i,
    /^(uh+[,.\s]*)+$/i,                     // "Uh, uh, uh"
    /^(um+[,.\s]*)+$/i,                     // "Um, um, um"
    /blogspot\.com/i,                       // URL hallucinations
    /^(slurp[,.\s]*)+$/i,                   // "Slurp slurp slurp"
    /outtakes/i,
];

// Detect when Whisper echoes the prompt back (repeating the same phrase 3+ times)
function isPromptEcho(text) {
    // Split into sentences and check for 3+ near-identical repetitions
    const parts = text.split(/[.!?]+/).map(s => s.trim().toLowerCase()).filter(s => s.length > 2);
    if (parts.length >= 3) {
        const first = parts[0];
        const matches = parts.filter(p => p === first).length;
        if (matches >= 3) return true;
    }
    return false;
}

// Minimum no_speech probability — segments above this are likely noise
const NO_SPEECH_THRESHOLD = 0.8;

export class WhisperClient {
    constructor(apiKey) {
        this.openai = new OpenAI({ apiKey });
        this.activeRequests = 0;
        this.queue = [];
    }

    async transcribe(wavBuffer) {
        return new Promise((resolve, reject) => {
            const task = { wavBuffer, resolve, reject, retries: 0 };
            this.queue.push(task);
            this._processQueue();
        });
    }

    _processQueue() {
        while (this.activeRequests < MAX_CONCURRENT && this.queue.length > 0) {
            const task = this.queue.shift();
            this.activeRequests++;
            this._executeTask(task).finally(() => {
                this.activeRequests--;
                this._processQueue();
            });
        }
    }

    async _executeTask(task) {
        try {
            const result = await this._callWhisper(task.wavBuffer);
            task.resolve(result);
        } catch (err) {
            if (err.code && NON_RETRYABLE_CODES.includes(err.code)) {
                log.debug(`Whisper: ${err.code}, skipping segment`);
                task.resolve(null);
                return;
            }

            if (task.retries < MAX_RETRIES) {
                task.retries++;
                const delay = BASE_DELAY_MS * Math.pow(2, task.retries - 1);
                log.warn(`Whisper request failed, retrying in ${delay}ms (attempt ${task.retries}/${MAX_RETRIES})`);
                await new Promise((r) => setTimeout(r, delay));
                this.queue.unshift(task);
                this._processQueue();
            } else {
                log.error('Whisper request failed after max retries:', err.message);
                task.resolve(null);
            }
        }
    }

    async _callWhisper(wavBuffer) {
        const file = new File([wavBuffer], 'audio.wav', { type: 'audio/wav' });

        const response = await this.openai.audio.transcriptions.create({
            model: 'whisper-1',
            file,
            response_format: 'verbose_json',
            prompt: WHISPER_PROMPT,
        });

        if (!response || !response.text) {
            return null;
        }

        const text = response.text.trim();

        // Filter hallucinations and prompt echoes
        if (this._isHallucination(text) || isPromptEcho(text)) {
            log.debug(`Filtered hallucination: "${text.substring(0, 50)}"`);
            return null;
        }

        // Check no_speech_probability on segments
        const segments = response.segments || [];
        if (segments.length > 0) {
            const avgNoSpeech = segments.reduce((sum, s) => sum + (s.no_speech_prob || 0), 0) / segments.length;
            if (avgNoSpeech > NO_SPEECH_THRESHOLD) {
                log.debug(`Filtered low-confidence speech (no_speech_prob=${avgNoSpeech.toFixed(2)}): "${text.substring(0, 50)}"`);
                return null;
            }
        }

        return {
            text,
            language: response.language || null,
            confidence: segments[0]?.avg_logprob
                ? Math.exp(segments[0].avg_logprob)
                : null,
        };
    }

    _isHallucination(text) {
        return HALLUCINATION_PATTERNS.some((pattern) => pattern.test(text));
    }
}
