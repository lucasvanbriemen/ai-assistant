<script>
  import  '@styles/InitalExamples.scss';

  let { input = $bindable('') } = $props();

  const EXAMPLE_LIMIT = 3;

  const examples = [
    'Give me an overview of my emails today and if i need to do anything.',
    'What movie is planed for tonight?',
    'Where do i need to go tonight?',
    'Do i need to prepare anything for tonight?',
    'What movies are playing tonight that i havent yet seen?'
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