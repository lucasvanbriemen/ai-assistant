<script>
  import { gfmPlugin } from 'svelte-exmarkdown/gfm';
  import Markdown from 'svelte-exmarkdown';
  import '../../scss/components/message.scss';

  let { role, text } = $props();
</script>

<div class="message" class:user={role === 'user'} class:assistant={role === 'assistant'}>
  <Markdown md={text} plugins={[gfmPlugin]} />
</div>