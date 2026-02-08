export class StreamHandler {
    constructor(url, data, callbacks) {
        this.url = url;
        this.data = data;
        this.callbacks = callbacks;
        this.accumulator = '';
        this.aborted = false;
    }

    async connect() {
        try {
            const response = await fetch(this.url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'text/event-stream',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                },
                body: JSON.stringify(this.data),
            });

            if (!response.ok) {
                throw new Error(`Stream connection failed with status ${response.status}`);
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';

            while (true) {
                if (this.aborted) {
                    reader.cancel();
                    break;
                }

                const { done, value } = await reader.read();
                if (done) {
                    break;
                }

                const chunk = decoder.decode(value, { stream: true });
                buffer += chunk;

                // Process complete lines
                const lines = buffer.split('\n');
                buffer = lines.pop(); // Keep incomplete line in buffer

                for (const line of lines) {
                    this.processLine(line);
                }
            }

            // Process any remaining buffer
            if (buffer.length > 0) {
                this.processLine(buffer);
            }
        } catch (error) {
            if (!this.aborted) {
                this.callbacks.onError?.(error);
            }
        }
    }

    processLine(line) {
        const trimmedLine = line.trim();

        let eventType = 'chunk';
        if (trimmedLine.startsWith('event: ')) {
            // Extract event type for tool tracking
            eventType = trimmedLine.substring(7).trim();
            return;
        }

        if (trimmedLine.startsWith('data: ')) {
            try {
                const jsonStr = trimmedLine.substring(6);
                const data = JSON.parse(jsonStr);

                if (data.content) {
                    this.accumulator += data.content;
                    this.callbacks.onChunk?.(data.content, this.accumulator);
                } else if (data.message) {
                    this.callbacks.onComplete?.(data.message);
                } else if (data.name && data.action) {
                    // Tool event
                    this.callbacks.onTool?.(data.name, data.action);
                } else if (data.error) {
                    this.callbacks.onError?.(new Error(data.message || 'Unknown streaming error'));
                }
            } catch (error) {
                // Silently ignore JSON parse errors
                return;
            }
        }
    }

    abort() {
        this.aborted = true;
    }
}
