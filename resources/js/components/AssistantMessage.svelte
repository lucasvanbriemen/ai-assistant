<script>
  import MarkdownRenderer from '@/components/MarkdownRenderer.svelte';
  import '@styles/Message.scss';

  let { content, timestamp, isStreaming = false } = $props();
</script>

<div class="message assistant-message">
  <div class="message-avatar">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
  </div>
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
