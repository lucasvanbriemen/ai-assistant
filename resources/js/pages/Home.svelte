<script>
  import AtomLogo from '../components/AtomLogo.svelte';

  let messages = $state([]);
  let input = $state('');
  let executingTools = $state([]);

  async function sendMessage() {
    const userMessage = input.trim();
    input = '';
    messages.push({role: 'user', content: userMessage, timestamp: new Date()});

    // Add placeholder for streaming response
    const placeholderIndex = messages.length;
    messages.push({role: 'assistant', content: '', timestamp: new Date()});
    messages = messages;

    api.stream('/api/chat/send', {
        message: userMessage,
        history: messages
          .filter(m => m.role !== 'system' && m.role !== 'error')
          .map(m => ({
            role: m.role,
            content: m.content,
          })),
      },
      // onChunk
      (chunk, fullMessage) => {
        messages[placeholderIndex].content = fullMessage;
        messages = messages; // Trigger reactivity
      },
      // onComplete
      (finalMessage) => {
        messages[placeholderIndex].content = finalMessage;
        executingTools = [];
        messages = messages;
      },
      // onTool
      (toolName, action) => {
        if (action === 'start') {
          executingTools.push(toolName);
        } else if (action === 'complete') {
          executingTools = executingTools.filter(t => t !== toolName);
        }
        executingTools = executingTools; // Trigger reactivity
      }
    );
  }

  function handleKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  }
</script>

<div class="header">
  <AtomLogo size={200} />
  <h1>AI Assistant</h1>
</div>

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

{#if executingTools.length > 0}
  {#each executingTools as tool, i (i)}
   🔧 {tool}
  {/each}
{/if}

<textarea bind:value={input} onkeydown={handleKeydown} rows="2"></textarea>
<button onclick={sendMessage} disabled={!input.trim()}>Send</button>

<style>
  .header {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
    padding: 2rem 0;
  }

  .header h1 {
    margin: 0;
    font-size: 2rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
  }
</style>
