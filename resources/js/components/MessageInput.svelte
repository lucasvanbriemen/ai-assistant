<script>
  import Icon from '@/components/Icon.svelte';
  import '@styles/MessageInput.scss';

  let { input = $bindable(''), onkeydown = $bindable(() => {}), onhandleSend = $bindable(() => {}), disabled = false } = $props();

  let textarea;

  $effect(() => {
    textarea.style.height = 'auto';
    textarea.style.height = textarea.scrollHeight + 'px';
  });
</script>

<div class="input-wrapper" class:disabled>
  <textarea bind:this={textarea} bind:value={input} onkeydown={onkeydown} autofocus placeholder=" " rows="1" disabled={disabled}></textarea>
  <label for="message-input">{disabled ? 'AI is thinking...' : 'Ask me anything...'}</label>
  <Icon name="send" size="1.5rem" className="send-button" onclick={disabled ? null : onhandleSend} />
</div>
