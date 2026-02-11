<script>
  import '@styles/MessageInput.scss';

  let { input = $bindable(''), handleKeydown = $bindable(() => {}) } = $props();
</script>


<textarea bind:value={input} onkeydown={handleKeydown} rows="2" autofocus></textarea>
