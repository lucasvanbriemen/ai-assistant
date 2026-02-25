<script>
  import statusCard from '@/lib/status-card.js';

  let greeting = $state(statusCard.getRandomGreeting());
 
</script>

<div class="status-card">
  <h1>{greeting}</h1>
</div>