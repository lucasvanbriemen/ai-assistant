<script>
    import AppHead from '@/components/AppHead.svelte';
    import '../../scss/pages/welcome.scss';

    let prompt = $state('Explain the theory of relativity in 1 paragraph.');

    let messages = $state([]);

    let decoder = new TextDecoder();
    let reader;
    let lastIncompleteLine = '';

    function click() {
        fetch('/api/test', {
            method: 'POST',
            body: JSON.stringify({ prompt }),
            headers: {'Content-Type': 'application/json'},
        }).then((response) => {
            reader = response.body.getReader();
            messages.push('');
            read();
        })
    }

    function read() {
        reader.read().then(({ done, value }) => {
            if (done) {
                processLine(lastIncompleteLine);
                return;
            }

            const chunk = decoder.decode(value, { stream: true });
            lastIncompleteLine += chunk;

            // split on newline, leave last partial line in buffer
            const lines = lastIncompleteLine.split('\n');
            lastIncompleteLine = lines.pop();

            lines.forEach(processLine);

            read();
        });
    }

    function processLine(line) {
        line = line.trim();
        if (!line.startsWith('data:')) return;

        // We need to remove the event: part of the line to get the JSON string
        const json = JSON.parse(line.substring(5).trim());

        if (json.type === 'content_block_delta') {
            messages[messages.length - 1] += json.delta.text || '';
            console.log(json.delta.text);
            console.log(messages);
        }
    }
</script>

<AppHead title="Home">
</AppHead>

<div class="welcome-page">
    {#each messages as message}
        <p>{message}</p>
    {/each}

    <br>

    <div class="prompt-inputs">
        <textarea placeholder="Type something..." bind:value={prompt}></textarea>
        <button onclick={click}>submit</button>
    </div>
</div>
