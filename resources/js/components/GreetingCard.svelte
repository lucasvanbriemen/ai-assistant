<script>
  import { onMount } from 'svelte';

  const GREETING_OPTIONS = [
    "Hey",
    "Hello",
    "Hi",
    "Greetings",
    "Welcome",
    "Howdy",
    "Salutations",
  ];

  const HELP_OPTIONS = [
    "What can I do for you?",
    "How can I help you?",
    "Can I assist you?",
    "You need help?",
    "Shoot your question!",
    "I'm here to help!",
    "Only for you!",
  ];

  const INTRO_OPTIONS = [
    "Prime speaking!",
    "This is Prime",
    "At your service",
    "Prime is here!",
    "Guess whos back?",
  ];

  const POSSABLE_NAMES = [
    "Lucas",
    "King",
    "Sir",
    "My lord",
  ];

  let greeting = $state('Hello, welcome to my Svelte app!');

  // We generate a greeting card text
  // This can consist of the following:
  // - A greeting
  // - A name of the user
  // - How can i help text
  // - Prime intro

  function formatGreeting() {
    const randomGreeting = GREETING_OPTIONS[Math.floor(Math.random() * GREETING_OPTIONS.length)];
    const randomHelp = HELP_OPTIONS[Math.floor(Math.random() * HELP_OPTIONS.length)];
    const randomIntro = INTRO_OPTIONS[Math.floor(Math.random() * INTRO_OPTIONS.length)];
    const randomName = POSSABLE_NAMES[Math.floor(Math.random() * POSSABLE_NAMES.length)];
    greeting = `${randomGreeting}, ${randomName}! ${randomIntro} ${randomHelp}`;
  }

  onMount(() => {
    formatGreeting();
  });
</script>

<div class="greeting-card">
  <h1>{greeting}</h1>
</div>