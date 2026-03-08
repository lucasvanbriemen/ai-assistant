const REQUEST_TIMEOUT_MS = 120_000;

export class PrimeClient {
    constructor(apiUrl, agentToken) {
        this.endpoint = `${apiUrl}/api/prime/call`;
        this.agentToken = agentToken;
    }

    async chat(history) {
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS);

        try {
            const response = await fetch(this.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${this.agentToken}`,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ history }),
                signal: controller.signal,
            });

            if (!response.ok) {
                const body = await response.text();
                throw new Error(`Prime API error ${response.status}: ${body}`);
            }

            const data = await response.json();

            return data;
        } catch (err) {
            if (err.name === 'AbortError') {
                throw new Error('Prime API request timed out');
            }
            throw err;
        } finally {
            clearTimeout(timeout);
        }
    }
}
