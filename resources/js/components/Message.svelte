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
    setInterval(() => {
      if (visibleCount < words.length) {
        visibleCount++;
        visibleText = words.slice(0, visibleCount).join(' ');
      }
    }, 50);
  });
</script>

<div class="message" class:user={role === 'user'} class:assistant={role === 'assistant'}>
  <Markdown md={visibleText} plugins={[gfmPlugin]} />

  {#if isLast && role === "assistant"}
    <div class="skeleton-word"></div>
  {/if}
</div>