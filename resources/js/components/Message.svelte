<script>
  import { gfmPlugin } from 'svelte-exmarkdown/gfm';
  import Markdown from 'svelte-exmarkdown';
  import { untrack, onMount } from 'svelte';
  import '../../scss/components/message.scss';

  let { role, text, isLast = false } = $props();

  let visibleCount = $state(0);
  let visibleText = $state('');

  let words = [];

  $effect(() => {
    words = text.split(/\s+/);
  });

  onMount(() => {
    setInterval(updateVisibleText, 50);
    updateVisibleText();
  });

  function updateVisibleText() {
    if (visibleCount < words.length && isLast && role === 'assistant') {
      visibleCount++;
      visibleText = words.slice(0, visibleCount).join(' ');
    } else {
      visibleCount = words.length;
      visibleText = text;
    }
  };

  function showLoader() {
    return isLast && role === 'assistant' && visibleCount < words.length;
  }

</script>

<div class="message" class:user={role === 'user'} class:assistant={role === 'assistant'}>
  <Markdown md={visibleText} plugins={[gfmPlugin]} />

  {#if showLoader()}
    <div class="skeleton-word"></div>
  {/if}
</div>