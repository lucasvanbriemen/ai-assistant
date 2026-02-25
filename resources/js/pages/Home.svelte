<script>
    import AppHead from '@/components/AppHead.svelte';
    import GreetingCard from '@/components/GreetingCard.svelte';
    import '../../scss/pages/welcome.scss';

    let prompt = $state('Explain the theory of relativity in 1 paragraph.');
    let isSending = $state(false);

    let messages = $state([]);

    let decoder = new TextDecoder();
    let reader;
    let lastIncompleteLine = '';

    function submitPrompt() {
        isSending = true;

        messages.push({ text: prompt, role: 'user' });

        fetch('/api/test', {
            method: 'POST',
            body: JSON.stringify({ history: messages }),
            headers: {'Content-Type': 'application/json'},
        }).then((response) => {
            reader = response.body.getReader();
            messages.push({ text: '', role: 'assistant' });
            read();
        })
    }

    function promptInput(event) {
        // If it was the enter key, call the click function (unless shift is pressed)
        if (event.key === 'Enter' && event.shiftKey) {
            event.stopPropagation();
            event.preventDefault();
            submitPrompt();
        }
    }

    function read() {
        reader.read().then(({ done, value }) => {
            if (done) {
                processLine(lastIncompleteLine);
                isSending = false;
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
            messages[messages.length - 1].text += json.delta.text || '';
        }
    }
</script>

<AppHead title="Home">
</AppHead>

<div class="welcome-page">
    <GreetingCard />
    {#each messages as message}
        <h2>{message.role}</h2>
        <p>{message.text}</p>
        <br>
    {/each}

    <br>

    <div class="prompt-inputs">
        <textarea placeholder="Type something..." bind:value={prompt} autofocus onkeydown={promptInput}></textarea>
        <button onclick={submitPrompt} disabled={isSending}>submit</button>
    </div>
</div>
