<script>
  import Icon from '@/components/Icon.svelte';
  import '@styles/MessageInput.scss';

  let { input = $bindable(''), onkeydown = $bindable(() => {}), onhandleSend = $bindable(() => {}) } = $props();

  let textarea;

  // Auto-resize textarea
  $effect(() => {
    if (textarea && input !== undefined) {
      textarea.style.height = 'auto';
      textarea.style.height = textarea.scrollHeight + 'px';
    }
  });
</script>

<div class="input-wrapper">
  <textarea
    bind:this={textarea}
    id="message-input"
    bind:value={input}
    onkeydown={onkeydown}
    autofocus
    placeholder=" "
    rows="1"
  ></textarea>
  <label for="message-input">Type your message (Shift+Enter for new line)</label>
  <Icon name="send" size="1.5rem" className="send-button" onclick={onhandleSend} />
</div>
