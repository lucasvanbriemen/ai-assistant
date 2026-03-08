import { createLogger } from '../logger.js';

const log = createLogger('wake-word');

// Wake word patterns
const STRONG_PATTERN = /\bh[ea]+[iy]?\s+prime\b/i;       // "hey prime", "hee prime" (Dutch)
const SENTENCE_START_PATTERN = /^prime[\s,]/i;             // "Prime, ..." at start of sentence

// Deferred command keywords — these indicate the user wants context from the transcript buffer
const DEFERRED_KEYWORDS = [
    /\bsummar/i,           // summarize, summary
    /\bsamenva/i,          // samenvatten, samenvatting (Dutch)
    /\brecap\b/i,
    /\blast\s+\d+\s+min/i, // "last 5 minutes"
    /\blaatste\s+\d+\s+min/i, // "laatste 5 minuten" (Dutch)
    /\bwat\s+(hebben|was|waren)\s+/i,  // "wat hebben we besproken" (Dutch)
    /\bwhat\s+(did|was|were|have)\s+/i,
];

export class WakeWordDetector {
    detect(text) {
        if (!text || text.trim().length === 0) return null;

        const trimmed = text.trim();

        let commandText = null;

        // Check strong pattern first ("hey prime ...")
        const strongMatch = trimmed.match(STRONG_PATTERN);
        if (strongMatch) {
            commandText = trimmed.substring(strongMatch.index + strongMatch[0].length).trim();
        }

        // Check sentence start ("Prime, ...")
        if (!commandText) {
            const startMatch = trimmed.match(SENTENCE_START_PATTERN);
            if (startMatch) {
                commandText = trimmed.substring(startMatch[0].length).trim();
            }
        }

        if (commandText === null) return null;

        // Remove leading punctuation/whitespace from command
        commandText = commandText.replace(/^[,\s]+/, '').trim();

        if (commandText.length === 0) {
            // Just said "Hey Prime" with nothing after — treat as attention-getter
            return {
                type: 'inline',
                command: '',
                fullMatch: trimmed,
            };
        }

        // Classify as inline or deferred
        const isDeferred = DEFERRED_KEYWORDS.some((pattern) => pattern.test(commandText));

        return {
            type: isDeferred ? 'deferred' : 'inline',
            command: commandText,
            fullMatch: trimmed,
        };
    }
}
