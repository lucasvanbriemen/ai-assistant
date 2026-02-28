<script>
    import { tick } from 'svelte';
    import AppHead from '@/components/AppHead.svelte';
    import StatusCard from '@/components/StatusCard.svelte';
    import Message from '@/components/Message.svelte';
    import '../../scss/pages/home.scss';
    import Markdown from 'svelte-exmarkdown';
    import { gfmPlugin } from 'svelte-exmarkdown/gfm';

    let prompt = $state('Explain the theory of relativity in 1 paragraph.');
    let isThinking = $state(false);

    let messages = $state([]);

    let statusCardWrapper;
    let chatContainerEl;

    let decoder = new TextDecoder();
    let reader;
    let lastIncompleteLine = '';

    async function scrollToBottom() {
        await tick();
        chatContainerEl.scrollTo({ top: chatContainerEl.scrollHeight, behavior: 'smooth' });
    }

    async function submitPrompt() {
        const isFirstMessage = messages.length === 0;
        let startRect;

        if (isFirstMessage) {
            startRect = statusCardWrapper.getBoundingClientRect();
        }

        isThinking = true;
        messages.push({ text: prompt, role: 'user' });
        scrollToBottom();

        if (isFirstMessage) {
            await tick();

            const endRect = statusCardWrapper.getBoundingClientRect();
            const dx = startRect.left - endRect.left;
            const dy = startRect.top - endRect.top;

            statusCardWrapper.animate([
                { transform: `translate(${dx}px, ${dy}px)` },
                { transform: 'translate(0, 0)' }
            ], {
                duration: 600,
                easing: 'cubic-bezier(0.4, 0, 0.2, 1)',
            });

            chatContainerEl.animate([
                { opacity: 0, transform: 'translateY(20px)' },
                { opacity: 1, transform: 'translateY(0)' }
            ], {
                duration: 400,
                delay: 200,
                easing: 'ease-out',
                fill: 'backwards',
            });
        }

        fetch('/api/test', {
            method: 'POST',
            body: JSON.stringify({ history: messages }),
            headers: {'Content-Type': 'application/json'},
        }).then((response) => {
            reader = response.body.getReader();
            messages.push({ text: '', role: 'assistant' });
            scrollToBottom();
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
                isThinking = false;
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
            scrollToBottom();
        }
    }
</script>

<AppHead title="Home">
</AppHead>

<div class="home-page" class:no-messages={messages.length == 0}>

    <div class="status-card-wrapper" bind:this={statusCardWrapper}>
        <StatusCard status={isThinking ? 'thinking' : 'normal'} />
    </div>

    <div class="chat-container" bind:this={chatContainerEl}>
        <div class="messages-wrapper">
            {#each messages as message, i (i)}
                <Message role={message.role} text={message.text} isLast={i === messages.length - 1} />
            {/each}
        </div>

        <div class="prompt-inputs">
            <textarea placeholder="Type something..." bind:value={prompt} autofocus onkeydown={promptInput}></textarea>
            <button onclick={submitPrompt} disabled={isThinking}>submit</button>
        </div>
    </div>
</div>
