<script>
  let messages = $state([]);
  let input = $state('');
  let isStreaming = $state(false);
  let streamAbort = $state(null);
  let executingTools = $state([]);

  async function sendMessage() {
    const userMessage = input.trim();
    if (!userMessage || isStreaming) return;

    input = '';
    messages.push({role: 'user', content: userMessage, timestamp: new Date()});

    // Add placeholder for streaming response
    const placeholderIndex = messages.length;
    messages.push({role: 'assistant', content: '', timestamp: new Date(), streaming: true});
    messages = messages;

    isStreaming = true;

    try {
      // Attempt streaming
      streamAbort = api.stream(
        '/api/chat/stream',
        {
          message: userMessage,
          history: messages
            .filter(m => m.role !== 'system' && m.role !== 'error' && !m.streaming)
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
          messages[placeholderIndex].streaming = false;
          executingTools = [];
          messages = messages;
          isStreaming = false;
          streamAbort = null;
        },
        // onError - fallback to synchronous
        async (error) => {
          console.warn('Streaming failed, falling back to sync:', error);

          try {
            const response = await api.post('/api/chat/send', {
              message: userMessage,
              history: messages
                .filter(m => m.role !== 'system' && m.role !== 'error' && !m.streaming)
                .map(m => ({
                  role: m.role,
                  content: m.content,
                })),
            });

            messages[placeholderIndex].content = response.message;
            messages[placeholderIndex].streaming = false;
            executingTools = [];
            messages = messages;
            isStreaming = false;
            streamAbort = null;
          } catch (syncError) {
            console.error('Fallback sync error:', syncError);
            messages[placeholderIndex].content = 'Error: Could not get response';
            messages[placeholderIndex].streaming = false;
            executingTools = [];
            messages = messages;
            isStreaming = false;
            streamAbort = null;
          }
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
    } catch (error) {
      console.error('Error:', error);
      messages[placeholderIndex].content = 'Error: Could not get response';
      messages[placeholderIndex].streaming = false;
      isStreaming = false;
    }
  }

  function handleKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  }
</script>

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
  <div style="opacity: 0.6; font-style: italic;">
    {#each executingTools as tool (tool)}
      <div>🔧 {tool}</div>
    {/each}
  </div>
{/if}

<textarea bind:value={input} onkeydown={handleKeydown} rows="2" disabled={isStreaming}></textarea>
<button onclick={sendMessage} disabled={!input.trim() || isStreaming}>
  {isStreaming ? 'Streaming...' : 'Send'}
</button>
