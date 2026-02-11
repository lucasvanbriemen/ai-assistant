<script>
  let { input = $bindable('') } = $props();

  const EXAMPLE_LIMIT = 5;

  const examples = [
    'What is the capital of France?',
    'What is the capital of Germany?',
    'What is the capital of Italy?',
    'What is the capital of Spain?',
    'What is the capital of the United States?',
    'What is the capital of Canada?',
    'What is the capital of Australia?',
    'What is the capital of Japan?',
    'What is the capital of China?',
    'What is the capital of Russia?',
    'What is the capital of India?',
    'What is the capital of Brazil?',
  ];

  function getRandomExamples() {
    const shuffled = examples.sort(() => 0.5 - Math.random());
    return shuffled.slice(0, EXAMPLE_LIMIT);
  }

</script>

{#each getRandomExamples() as example}
  <button onclick={() => input = example}>{example}</button>
{/each}