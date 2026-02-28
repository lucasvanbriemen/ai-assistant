<script>
  import { gfmPlugin } from 'svelte-exmarkdown/gfm';
  import Markdown from 'svelte-exmarkdown';
  import '../../scss/components/message.scss';

  let { role, text, isLast = false } = $props();
</script>

<div class="message" class:user={role === 'user'} class:assistant={role === 'assistant'}>
  <Markdown md={text} plugins={[gfmPlugin]} />

  {#if isLast && role === "assistant"}
    <div class="skeleton-word"></div>
  {/if}
</div>