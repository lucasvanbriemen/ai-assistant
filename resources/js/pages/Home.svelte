<script>
  let messages = $state([]);
  let input = $state('');
  let loading = $state(false);
  let messagesContainer = $state();

  async function sendMessage() {
    if (!input.trim() || loading) return;

    const userMessage = input.trim();
    input = '';

    messages.push({
      role: 'user',
      content: userMessage,
      timestamp: new Date(),
    });

    loading = true;
    scrollToBottom();

    const response = await api.post('/api/chat/send', {
        message: userMessage,
        history: messages
            .filter(m => m.role !== 'system' && m.role !== 'error')
            .map(m => ({
            role: m.role,
            content: m.content,
        })),
    });

    messages.push({
        role: 'assistant',
        content: response.message,
        timestamp: new Date()
    });

    messages = messages;

    loading = false;
    scrollToBottom();
  }

  function scrollToBottom() {
    setTimeout(() => {
      if (messagesContainer) {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
      }
    }, 0);
  }

  function clearChat() {
    messages = [];
    input = '';
  }

  function handleKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  }
</script>

<h2>AI Chatbot</h2>
<button onclick="{clearChat}">Clear Chat</button>

<div class="messages" bind:this={messagesContainer}>
{#each messages as message, i (i)}
    {message.content}
    {#if message.timestamp}
        {message.timestamp.toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit',
        })}
    {/if}
{/each}
</div>

<textarea bind:value={input} onkeydown={handleKeydown} disabled={loading} rows="2"></textarea>
<button onclick={sendMessage} disabled={!input.trim() || loading}>{loading ? 'Sending...' : 'Send'}</button>
