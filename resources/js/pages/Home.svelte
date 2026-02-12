<script>
  import GreetingCard from '@/components/GreetingCard.svelte';
  import MessageInput from '@/components/MessageInput.svelte';
  import UserMessage from '@/components/UserMessage.svelte';
  import AssistantMessage from '@/components/AssistantMessage.svelte';
  import ThinkingIndicator from '@/components/ThinkingIndicator.svelte';
  import ToolExecutionBadge from '@/components/ToolExecutionBadge.svelte';
  import '@styles/Home.scss';

  let messages = $state([]);
  let input = $state('');
  let executingTools = $state([]);
  let isThinking = $state(false);
  let isStreaming = $state(false);
  let messagesListEl = $state(null);

  // Track if we have any messages
  let hasMessages = $derived(messages.length > 0);

  // Auto-scroll to bottom when messages change or when thinking/streaming
  $effect(() => {
    if (messagesListEl && (messages.length > 0 || isThinking || isStreaming)) {
      messagesListEl.scrollTop = messagesListEl.scrollHeight;
    }
  });

  async function sendMessage() {
    const userMessage = input.trim();
    if (!userMessage) return;

    input = '';
    messages.push({role: 'user', content: userMessage, timestamp: new Date()});
    messages = messages;

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
        isThinking = false;
        isStreaming = true;
        messages[placeholderIndex].content = fullMessage;
        messages = messages; // Trigger reactivity
      },
      // onComplete
      (finalMessage) => {
        messages[placeholderIndex].content = finalMessage;
        executingTools = [];
        isStreaming = false;
        isThinking = false;
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
      },
      // onThinking
      (status) => {
        if (status === 'start') {
          isThinking = true;
        } else if (status === 'end') {
          isThinking = false;
        }
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

<div class="home-container" class:with-messages={hasMessages}>
  {#if !hasMessages}
    <GreetingCard />
  {:else}
    <div class="messages-container">
      <div class="messages-list" bind:this={messagesListEl}>
        {#each messages as message, i (i)}
          {#if message.role === 'user'}
            <UserMessage content={message.content} timestamp={message.timestamp} />
          {:else if message.role === 'assistant'}
            <AssistantMessage
              content={message.content}
              timestamp={message.timestamp}
              isStreaming={isStreaming && i === messages.length - 1}
            />
          {/if}
        {/each}

        {#if isThinking && !isStreaming}
          <ThinkingIndicator />
        {/if}
      </div>

      <ToolExecutionBadge tools={executingTools} />
    </div>
  {/if}

  <MessageInput bind:input onkeydown={handleKeydown} onhandleSend={sendMessage} disabled={isThinking || isStreaming} />
</div>
