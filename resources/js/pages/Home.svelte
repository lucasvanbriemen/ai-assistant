<script>
  let messages = $state([]);
  let input = $state('');

  async function sendMessage() {
    const userMessage = input.trim();
    input = '';

    messages.push({role: 'user', content: userMessage, timestamp: new Date(),});

    const response = await api.post('/api/chat/send', {
        message: userMessage,
        history: messages
            .filter(m => m.role !== 'system' && m.role !== 'error')
            .map(m => ({
            role: m.role,
            content: m.content,
        })),
    });

    messages.push({role: 'assistant', content: response.message, timestamp: new Date()});
    messages = messages;
  }

  function handleKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  }
</script>

<div class="messages" bind:this={messagesContainer}>
{#each messages as message, i (i)}
    {message.content}
    <hr>
    {#if message.timestamp}
        {message.timestamp.toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit',
        })}
    {/if}

    <hr><hr><hr><hr>
{/each}
</div>

<textarea bind:value={input} onkeydown={handleKeydown} rows="2"></textarea>
<button onclick={sendMessage} disabled={!input.trim()}>Send</button>
