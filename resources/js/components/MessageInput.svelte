<script>
  import Icon from '@/components/Icon.svelte';
  import '@styles/MessageInput.scss';

  let { input = $bindable(''), handleKeydown = $bindable(() => {}), onhandleSend = $bindable(() => {}) } = $props();
</script>

<div class="input-wrapper">
  <input bind:value={input} onkeydown={handleKeydown} rows="2" autofocus />
  <Icon name="send" size="1.5rem" className="send-button" onclick={onhandleSend} />
</div>
