<script>
  import Icon from '@/components/Icon.svelte';
  import '@styles/MessageInput.scss';

  let { input = $bindable(''), onkeydown = $bindable(() => {}), onhandleSend = $bindable(() => {}) } = $props();

  let textarea;

  $effect(() => {
    textarea.style.height = 'auto';
    textarea.style.height = textarea.scrollHeight + 'px';
  });
</script>

<div class="input-wrapper">
  <textarea bind:this={textarea} bind:value={input} onkeydown={onkeydown} autofocus placeholder=" " rows="1"></textarea>
  <label for="message-input">Ask me anything...</label>
  <Icon name="send" size="1.5rem" className="send-button" onclick={onhandleSend} />
</div>
