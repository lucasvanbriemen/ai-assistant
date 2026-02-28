<script>
  import { gfmPlugin } from 'svelte-exmarkdown/gfm';
  import Markdown from 'svelte-exmarkdown';

  let { role, text } = $props();
</script>

<h2>{role}</h2>
<Markdown md={text} plugins={[gfmPlugin]} />
<br>