<script>
  import '@styles/Message.scss';

  let { content, timestamp } = $props();
</script>

<div class="message user-message">
  <div class="message-content">
    <p>{content}</p>
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
