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
    "Lucas,",
    "King,",
    "Sir,",
    "My lord,",
  ];

  let greeting = $state('');

  // We generate a greeting card text
  // This can consist of the following:
  // - A greeting
  // - A name of the user
  // - How can i help text
  // - Prime intro

  function formatGreeting() {
    const randomGreeting = GREETING_OPTIONS[Math.floor(Math.random() * GREETING_OPTIONS.length)];
    const timeBaseGreeting = getTimeGreeting();
    const randomName = POSSABLE_NAMES[Math.floor(Math.random() * POSSABLE_NAMES.length)];
    const randomHelp = HELP_OPTIONS[Math.floor(Math.random() * HELP_OPTIONS.length)];
    const randomIntro = INTRO_OPTIONS[Math.floor(Math.random() * INTRO_OPTIONS.length)];

    const rand = Math.random();
    // 30% - Time-based greeting with username
    if (rand < 0.3) {
      return `${timeBaseGreeting}, ${randomName}! ${randomIntro}${randomHelp}`;
    }
    // 20% - Simple greeting with username
    else if (rand < 0.5) {
      return `${randomGreeting}, ${randomName}! ${randomIntro}${randomHelp}`;
    }
    // 15% - Time-based greeting only
    else if (rand < 0.65) {
      return `${timeBaseGreeting}! ${randomIntro}${randomHelp}`;
    }
    // 35% - Just the help message (with Prime intro more likely here)
    else {
      const soloIntro = Math.random() < 0.6 ? `${randomIntro} ` : '';
      return `${soloIntro}${randomHelp}`;
    }
  }

  function getTimeGreeting() {
    const hour = new Date().getHours();

    if (hour >= 5 && hour < 12) {
      return 'Good morning';
    } else if (hour >= 12 && hour < 17) {
      return 'Good afternoon';
    } else if (hour >= 17 && hour < 22) {
      return 'Good evening';
    } else {
      return 'Good night';
    }
  }

  onMount(() => {
    greeting = formatGreeting();
  });
</script>

<div class="greeting-card">
  <h1>{greeting}</h1>
</div>