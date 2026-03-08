// Extract minute count from deferred commands like "last 5 minutes"
const MINUTES_PATTERN = /(?:last|laatste)\s+(\d+)\s+min/i;

export class CommandParser {
    constructor(transcriptBuffer) {
        this.transcriptBuffer = transcriptBuffer;
    }

    assemble(detection, fullText) {
        if (detection.type === 'deferred') {
            return this._assembleDeferred(detection, fullText);
        }
        return this._assembleInline(detection, fullText);
    }

    _assembleInline(detection, fullText) {
        // For inline commands, send just the command as user message
        // Include a small amount of recent context so Prime knows what "that" or "this" refers to
        const recentContext = this.transcriptBuffer.getRecentText(2); // Last 2 minutes for context

        const history = [];

        if (recentContext && recentContext.length > 0) {
            history.push({
                role: 'user',
                text: `[Voice channel context - recent conversation transcript]\n${recentContext}`,
            });
            history.push({
                role: 'assistant',
                text: 'Understood, I have the conversation context.',
            });
        }

        history.push({
            role: 'user',
            text: detection.command || fullText,
        });

        return {
            type: 'inline',
            history,
            contextText: recentContext || null,
        };
    }

    _assembleDeferred(detection, fullText) {
        // For deferred commands, include more transcript context
        const minutesMatch = detection.command.match(MINUTES_PATTERN);
        const minutes = minutesMatch ? parseInt(minutesMatch[1], 10) : 10;
        const contextText = this.transcriptBuffer.getRecentText(minutes);

        const history = [
            {
                role: 'user',
                text: `[Voice channel transcript - last ${minutes} minutes]\n${contextText}\n\n[End of transcript]\n\nRequest: ${detection.command}`,
            },
        ];

        return {
            type: 'deferred',
            history,
            contextText,
        };
    }
}
