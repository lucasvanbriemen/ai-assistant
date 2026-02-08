export class StreamHandler {
    constructor(url, data, callbacks) {
        this.url = url;
        this.data = data;
        this.callbacks = callbacks;
        this.accumulator = '';
    }

    async connect() {
        const response = await fetch(this.url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'text/event-stream',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
            },
            body: JSON.stringify(this.data),
        });

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        while (true) {
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
    }

    processLine(line) {
        const trimmedLine = line.trim();

        if (trimmedLine.startsWith('event: ')) {
            return;
        }

        if (trimmedLine.startsWith('data: ')) {
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
            }
        }
    }
}
