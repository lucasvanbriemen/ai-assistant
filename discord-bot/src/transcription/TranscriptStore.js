import config from '../config.js';

export class TranscriptStore {
    constructor() {
        this.baseUrl = `${config.laravel.apiUrl}/api/voice`;
        this.token = config.laravel.agentToken;
    }

    async _post(path, body = {}) {
        const res = await fetch(`${this.baseUrl}${path}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${this.token}`,
                'Accept': 'application/json',
            },
            body: JSON.stringify(body),
        });

        if (!res.ok) {
            const text = await res.text();
            throw new Error(`Voice API error ${res.status} on ${path}: ${text}`);
        }

        return res.json();
    }

    async _put(path, body = {}) {
        const res = await fetch(`${this.baseUrl}${path}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${this.token}`,
                'Accept': 'application/json',
            },
            body: JSON.stringify(body),
        });

        if (!res.ok) {
            const text = await res.text();
            throw new Error(`Voice API error ${res.status} on ${path}: ${text}`);
        }

        return res.json();
    }

    async addTranscript({ text, language, confidence, audioDurationMs, startedAt }) {
        await this._post('/transcripts', {
            text,
            language,
            confidence,
            audio_duration_ms: audioDurationMs,
            started_at: startedAt,
        });
    }

    async addCommand({ triggerType, triggerText, contextText }) {
        await this._post('/commands', {
            trigger_type: triggerType,
            trigger_text: triggerText,
            context_text: contextText,
        });
    }

    async updateCommandResponse(triggerText, responseText) {
        await this._put('/commands/complete', {
            trigger_text: triggerText,
            response_text: responseText,
        });
    }

    close() {
        // No-op — no local connections to close
    }
}
