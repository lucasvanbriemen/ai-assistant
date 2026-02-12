<script>
  import '@styles/ToolExecution.scss';

  let { tools } = $props();
</script>

{#if tools.length > 0}
  <div class="tool-execution-container">
    <div class="tool-execution-badge">
      <div class="spinner"></div>
      <span>Executing: {tools.join(', ')}</span>
    </div>
  </div>
{/if}
