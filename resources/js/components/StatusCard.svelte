<script>
  import statusCard from '@/lib/status-card.js';
  import Logo from './Logo.svelte';

  let greeting = $state(statusCard.getRandomGreeting());
 
</script>

<div class="status-card">
  <Logo size={280} state="thinking" />
  <h1>{greeting}</h1>
</div>