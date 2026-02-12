<script>
  import { onMount } from 'svelte';

  let { name = 'plus', size = '1rem', className = '', onclick = () => {} } = $props();
  let svgRaw = $state('');

  // Load all SVGs from resources/svgs as raw strings (bundled by Vite)
  const ICON_PREFIX = '../../svgs/';
  const icons = import.meta.glob('../../svgs/*.svg', { as: 'raw', eager: true });

  onMount(() => {
    let key = `${ICON_PREFIX}${name}.svg`;
    svgRaw = icons[key];
  });

  function handleClick() {
    onclick?.();
  }

</script>

<span class={`icon ${className}`} onclick={handleClick} style={`--icon-size:${size}`} >
  {#if svgRaw}
    {@html svgRaw}
  {/if}
</span>

<style>
  .icon :global(svg) {
    width: var(--icon-size);
    height: var(--icon-size);
    display: inline-block;
    vertical-align: text-bottom;
    fill: currentColor;
    stroke: currentColor;
  }
</style>
