<script>
  import api from '../lib/api.js';

  let messages = $state([]);
  let input = $state('');
  let loading = $state(false);
  let error = $state('');
  let messagesContainer = $state();

  const examples = [
    'Show me my unread emails',
    'When did I get a confirmation mail for my holiday?',
    'Create a calendar event for the cinema on Friday at 7pm',
    'What events do I have next week?',
  ];

  async function sendMessage() {
    if (!input.trim() || loading) return;

    const userMessage = input.trim();
    input = '';
    error = '';

    // Add user message to chat
    messages.push({
      role: 'user',
      content: userMessage,
      timestamp: new Date(),
    });

    loading = true;
    scrollToBottom();

    try {
      const response = await api.post('/api/chat/send', {
        message: userMessage,
        history: messages
          .filter(m => m.role !== 'system' && m.role !== 'error')
          .map(m => ({
            role: m.role,
            content: m.content,
          })),
      });

      if (response.success) {
        // Add assistant message
        messages.push({
          role: 'assistant',
          content: response.message,
          timestamp: new Date(),
          toolsUsed: response.tools_used || [],
        });

        // Update history
        messages = messages;
      } else {
        error = response.error || 'Failed to get response';
        messages.push({
          role: 'error',
          content: error,
          timestamp: new Date(),
        });
      }
    } catch (e) {
      error = 'Network error: ' + e.message;
      messages.push({
        role: 'error',
        content: error,
        timestamp: new Date(),
      });
    }

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
    error = '';
  }

  function handleKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  }

  function useExample(example) {
    input = example;
  }
</script>

<div class="chatbot">
  <div class="chatbot-header">
    <h2>AI Chatbot</h2>
    <button onclick={clearChat} class="btn-small">Clear Chat</button>
  </div>

  {#if messages.length === 0}
    <div class="chatbot-empty">
      <p>Welcome! I can help you search emails, manage your calendar, and more.</p>
      <p>Try asking:</p>
      <div class="examples">
        {#each examples as example}
          <button
            class="example-btn"
            onclick={() => useExample(example)}
          >
            {example}
          </button>
        {/each}
      </div>
    </div>
  {/if}

  <div class="messages" bind:this={messagesContainer}>
    {#each messages as message, i (i)}
      <div class="message message-{message.role}">
        <div class="message-content">
          {#if message.role === 'user'}
            <div class="message-text">{message.content}</div>
          {:else if message.role === 'error'}
            <div class="message-text error">{message.content}</div>
          {:else}
            <div class="message-text">{message.content}</div>
            {#if message.toolsUsed && message.toolsUsed.length > 0}
              <div class="tools-used">
                Used tools:
                {#each message.toolsUsed as tool}
                  <span class="tool-badge">{tool.name}</span>
                {/each}
              </div>
            {/if}
          {/if}
        </div>
        <div class="message-time">
          {#if message.timestamp}
            {message.timestamp.toLocaleTimeString([], {
              hour: '2-digit',
              minute: '2-digit',
            })}
          {/if}
        </div>
      </div>
    {/each}

    {#if loading}
      <div class="message message-assistant">
        <div class="message-content">
          <div class="typing-indicator">
            <span></span>
            <span></span>
            <span></span>
          </div>
        </div>
      </div>
    {/if}
  </div>

  <div class="input-area">
    <textarea
      bind:value={input}
      placeholder="Ask me anything..."
      onkeydown={handleKeydown}
      disabled={loading}
      rows="2"
    ></textarea>
    <button
      onclick={sendMessage}
      disabled={!input.trim() || loading}
      class="btn-send"
    >
      {loading ? 'Sending...' : 'Send'}
    </button>
  </div>
</div>

<style>
  .chatbot {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    overflow: hidden;
  }

  .chatbot-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #e0e0e0;
    background: #f5f5f5;
  }

  .chatbot-header h2 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
  }

  .btn-small {
    padding: 8px 12px;
    font-size: 13px;
    background: #f0f0f0;
    border: 1px solid #ddd;
    border-radius: 4px;
    cursor: pointer;
    transition: background 0.2s;
  }

  .btn-small:hover {
    background: #e0e0e0;
  }

  .chatbot-empty {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    flex: 1;
    padding: 40px;
    text-align: center;
    color: #666;
  }

  .chatbot-empty p {
    margin: 10px 0;
  }

  .examples {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 20px;
    width: 100%;
    max-width: 400px;
  }

  .example-btn {
    padding: 12px;
    text-align: left;
    background: #f0f0f0;
    border: 1px solid #ddd;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
    transition: all 0.2s;
  }

  .example-btn:hover {
    background: #e0e0e0;
    border-color: #ccc;
  }

  .messages {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .message {
    display: flex;
    gap: 8px;
    animation: slideIn 0.3s ease;
  }

  @keyframes slideIn {
    from {
      opacity: 0;
      transform: translateY(10px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  .message-user {
    justify-content: flex-end;
  }

  .message-assistant {
    justify-content: flex-start;
  }

  .message-error {
    justify-content: flex-start;
  }

  .message-content {
    max-width: 70%;
    padding: 12px;
    border-radius: 8px;
    word-wrap: break-word;
  }

  .message-user .message-content {
    background: #007bff;
    color: white;
  }

  .message-assistant .message-content {
    background: #e9ecef;
    color: #333;
  }

  .message-error .message-content {
    background: #f8d7da;
    color: #721c24;
  }

  .message-text {
    margin: 0;
    line-height: 1.4;
  }

  .message-text.error {
    font-weight: 500;
  }

  .message-time {
    font-size: 11px;
    color: #999;
    align-self: flex-end;
    margin-bottom: 4px;
  }

  .tools-used {
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid rgba(0, 0, 0, 0.1);
    font-size: 12px;
    color: rgba(0, 0, 0, 0.7);
  }

  .tool-badge {
    display: inline-block;
    background: rgba(0, 0, 0, 0.1);
    padding: 2px 6px;
    border-radius: 3px;
    margin-left: 4px;
    font-family: monospace;
  }

  .typing-indicator {
    display: flex;
    gap: 4px;
    align-items: center;
    height: 8px;
  }

  .typing-indicator span {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #ccc;
    animation: typing 1.4s infinite;
  }

  .typing-indicator span:nth-child(2) {
    animation-delay: 0.2s;
  }

  .typing-indicator span:nth-child(3) {
    animation-delay: 0.4s;
  }

  @keyframes typing {
    0%, 60%, 100% {
      opacity: 0.3;
      transform: translateY(0);
    }
    30% {
      opacity: 1;
      transform: translateY(-10px);
    }
  }

  .input-area {
    display: flex;
    gap: 10px;
    padding: 20px;
    border-top: 1px solid #e0e0e0;
    background: #f9f9f9;
  }

  textarea {
    flex: 1;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-family: inherit;
    font-size: 14px;
    resize: none;
    max-height: 100px;
  }

  textarea:focus {
    outline: none;
    border-color: #007bff;
  }

  textarea:disabled {
    background: #f0f0f0;
    color: #999;
  }

  .btn-send {
    padding: 10px 20px;
    background: #007bff;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
    transition: background 0.2s;
  }

  .btn-send:hover:not(:disabled) {
    background: #0056b3;
  }

  .btn-send:disabled {
    background: #ccc;
    cursor: not-allowed;
  }
</style>
