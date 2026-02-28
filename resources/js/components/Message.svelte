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

</script>

<div class="message" class:user={role === 'user'} class:assistant={role === 'assistant'}>
  <Markdown md={visibleText} plugins={[gfmPlugin]} />

  {#if isLast && role === "assistant"}
    <div class="skeleton-word"></div>
  {/if}
</div>