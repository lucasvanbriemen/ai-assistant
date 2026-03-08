const REQUEST_TIMEOUT_MS = 120_000;

export class PrimeClient {
    constructor(apiUrl, agentToken) {
        this.endpoint = `${apiUrl}/api/test`;
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
                body: JSON.stringify({ history, mode: 'voice' }),
                signal: controller.signal,
            });

            console.log(`[PrimeClient] response status: ${response.status}`);

            if (!response.ok) {
                const body = await response.text();
                throw new Error(`Prime API error ${response.status}: ${body}`);
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let fullText = '';
            let usedTools = [];
            let chunkCount = 0;

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;

                const chunk = decoder.decode(value, { stream: true });
                chunkCount++;
                const lines = chunk.split('\n').filter(line => line.trim());

                for (const line of lines) {
                    try {
                        const parsed = JSON.parse(line);
                        if (parsed.data) {
                            if (parsed.data.text_chunk) {
                                fullText += parsed.data.text_chunk;
                            }
                            if (parsed.data.used_tools?.length) {
                                usedTools = parsed.data.used_tools;
                            }
                        }
                    } catch {
                        console.warn(`[PrimeClient] unparseable line: ${line.substring(0, 200)}`);
                    }
                }
            }

            console.log(`[PrimeClient] stream done: ${chunkCount} chunks, ${fullText.length} chars`);

            return {
                response: fullText,
                used_tools: usedTools,
            };
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
