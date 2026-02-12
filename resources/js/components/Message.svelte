<script>
  import MarkdownRenderer from '@/components/MarkdownRenderer.svelte';
  import ThinkingIndicator from '@/components/ThinkingIndicator.svelte';
  import '@styles/Message.scss';

  let { content, timestamp, role = "user", isThinking, isStreaming, executingTools = [] } = $props();

  // Track displayed tools with minimum display duration (750ms)
  let displayedTools = $state([]);
  let toolTimestamps = new Map();
  const MIN_DISPLAY_DURATION = 1000; // milliseconds

  $effect(() => {
    // When new tools are added, show them immediately
    executingTools.forEach(tool => {
      if (!displayedTools.includes(tool)) {
        toolTimestamps.set(tool, Date.now());
        displayedTools = [...displayedTools, tool];
      }
    });

    // When tools are removed, wait for minimum duration before hiding
    displayedTools.forEach(tool => {
      if (!executingTools.includes(tool)) {
        const startTime = toolTimestamps.get(tool);
        const elapsed = Date.now() - startTime;
        const remainingTime = Math.max(0, MIN_DISPLAY_DURATION - elapsed);

        setTimeout(() => {
          displayedTools = displayedTools.filter(t => t !== tool);
          toolTimestamps.delete(tool);
        }, remainingTime);
      }
    });
  });
</script>

<div class="message {role}-message">
  <div class="message-content">
    {#if role === 'assistant' && displayedTools.length > 0}
      <div class="tool-execution-inline">
        <div class="tool-spinner"></div>
        <span class="tool-text">Using: {displayedTools.join(', ')}</span>
      </div>
    {/if}

    {#if content}
      <MarkdownRenderer content={content} />
    {:else if isThinking && !isStreaming && content === ''}
      <ThinkingIndicator />
    {/if}
  </div>

  <div class="message-timestamp">
    {timestamp.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'})}
  </div>
</div>
