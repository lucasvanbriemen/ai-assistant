<script>
  import statusCard from '@/lib/status-card.js';
  import Logo from './Logo.svelte';
  import '../../scss/components/status-card.scss';

  let greeting = $state(statusCard.getRandomGreeting());
  let state = $state('normal');
 
</script>

<div class="status-card">
  <button onclick={() => {state = state == 'thinking' ? 'normal' : 'thinking'}}>{state}</button>
  <Logo size={280} state={state} />
  <h1>{greeting}</h1>
</div>