<script>
  import { gfmPlugin } from 'svelte-exmarkdown/gfm';
  import Markdown from 'svelte-exmarkdown';
  import { untrack } from 'svelte';
  import '../../scss/components/message.scss';

  let { role, text, isLast = false } = $props();

  let visibleCount = $state(0);
  let visibleText = $state('');

  let words = [];
  let interval;

  $effect(() => {
    const current = text.split(/\s+/);

    // update backing word list without resetting progress
    words = current;

    // if no interval running, start one
    if (!interval) {
      interval = setInterval(() => {
        if (visibleCount < words.length) {
          visibleCount++;
          untrack(() => {
            visibleText = words.slice(0, visibleCount).join(' ');
          });
        }
      }, 50);
    }
  });
</script>

<div class="message" class:user={role === 'user'} class:assistant={role === 'assistant'}>
  <Markdown md={visibleText} plugins={[gfmPlugin]} />

  {#if isLast && role === "assistant"}
    <div class="skeleton-word"></div>
  {/if}
</div>