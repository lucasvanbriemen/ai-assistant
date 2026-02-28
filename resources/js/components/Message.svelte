<script>
  import { gfmPlugin } from 'svelte-exmarkdown/gfm';
  import Markdown from 'svelte-exmarkdown';
  import { onMount } from 'svelte';
  import '../../scss/components/message.scss';

  let { role, text, isLast = false } = $props();

  let visibleCharCount = $state(0);
  let visibleText = $state('');

  onMount(() => {
    const interval = setInterval(updateVisibleText, 20);
    updateVisibleText();
    return () => clearInterval(interval);
  });

  function updateVisibleText() {
    if (visibleCharCount < text.length && isLast && role === 'assistant') {
      visibleCharCount = Math.min(visibleCharCount + 2, text.length);
      visibleText = text.slice(0, visibleCharCount);
    } else {
      visibleCharCount = text.length;
      visibleText = text;
    }
  }

  function showLoader() {
    return isLast && role === 'assistant' && visibleCharCount < text.length;
  }

</script>

<div class="message" class:user={role === 'user'} class:assistant={role === 'assistant'} class:loading={showLoader()}>
  <Markdown md={visibleText} plugins={[gfmPlugin]} />

  {#if showLoader()}
    <span class="skeleton-word"></span>
  {/if}
</div>