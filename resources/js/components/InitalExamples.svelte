<script>
  import  '@styles/InitalExamples.scss';

  let { input = $bindable('') } = $props();

  const EXAMPLE_LIMIT = 5;

  const examples = [
    'Give me an overview of my emails today and if i need to do anything.',
    'What movie is planed for tonight?',
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

<div class="examples">
  {#each getRandomExamples() as example}
    <button onclick={() => input = example}>{example}</button>
  {/each}
</div>