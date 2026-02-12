<script>
  import MarkdownRenderer from '@/components/MarkdownRenderer.svelte';
  import ThinkingIndicator from '@/components/ThinkingIndicator.svelte';
  import '@styles/Message.scss';

  let { content, timestamp, role = "user", isThinking, isStreaming } = $props();
</script>

<div class="message {role}-message">
  <div class="message-content">
    {#if content}
      <MarkdownRenderer content={content} />
    {:else if isThinking && !isStreaming && content === ''}
      <ThinkingIndicator />
    {/if}
  </div>
  {#if timestamp}
    <div class="message-timestamp">
      {timestamp.toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit',
      })}
    </div>
  {/if}
</div>
