<script>
  import MarkdownRenderer from '@/components/MarkdownRenderer.svelte';
  import '@styles/Message.scss';

  let { content, timestamp, isStreaming = false } = $props();
</script>

<div class="message assistant-message">
  <div class="message-body">
    <div class="message-content">
      {#if content}
        <MarkdownRenderer content={content} />
      {/if}
      {#if isStreaming}
        <span class="cursor-blink">▋</span>
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
</div>
