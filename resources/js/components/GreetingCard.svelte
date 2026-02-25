<script>
  import { onMount } from 'svelte';

  let greeting = $state('Hello, welcome to my Svelte app!');

  function formatGreeting() {
    greeting = 'Hello, welcome to my Svelte app!!!';
  }

  onMount(() => {
    formatGreeting();
  });
</script>

<div class="greeting-card">
  <h1>{greeting}</h1>
</div>