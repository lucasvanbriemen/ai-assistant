<script>
  import statusCard from '@/lib/status-card.js';
  import Logo from './Logo.svelte';
  import '../../scss/components/status-card.scss';

  let greeting = $state(statusCard.getRandomGreeting());

  let { status } = $props();
 
</script>

<div class="status-card-wrapper">
  <div class="status-card">
    <Logo size={280} state={status} />
    <h1>{greeting}</h1>
  </div>
</div>