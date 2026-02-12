<script>
  import AtomLogo from '@/components/AtomLogo.svelte';
  import '@styles/GreetingCard.scss';

  let rotateX = $state(0);
  let rotateY = $state(0);
  let cardElement = $state(null);
  let isHovering = $state(false);

  function getUsername() {
    return "Lucas"
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

  // Message builder components
  const greetingOptions = [
    'Hey',
    'Hello',
    'Hi',
    'Welcome',
    'Greetings',
  ];

  const helpMessages = [
    "What can I help with?",
    "How can I assist you today?",
    "Ready to help with anything you need.",
    "What would you like to explore?",
    "I'm here to assist you.",
    "How may I help you?",
    "What can I do for you?",
    "Ready when you are.",
    "Let's get started!",
    "What's on your mind?",
    "How can I make your day easier?",
    "I'm all ears. What do you need?",
  ];

  const primeIntros = [
    "I'm Prime.",
    "Prime here.",
    "This is Prime.",
    "Prime at your service.",
  ];

  // Randomly build a message
  function buildGreetingMessage() {
    const rand = Math.random();
    const username = getUsername();
    const timeGreeting = getTimeGreeting();
    const helpMsg = helpMessages[Math.floor(Math.random() * helpMessages.length)];

    // 30% - Time-based greeting with username
    if (rand < 0.3) {
      return `${timeGreeting}, ${username}! ${helpMsg}`;
    }
    // 20% - Simple greeting with username
    else if (rand < 0.5) {
      const greeting = greetingOptions[Math.floor(Math.random() * greetingOptions.length)];
      return `${greeting}, ${username}! ${helpMsg}`;
    }
    // 15% - Time-based greeting only
    else if (rand < 0.65) {
      return `${timeGreeting}! ${helpMsg}`;
    }
    // 35% - Just the help message
    else {
      return helpMsg;
    }
  }

  // Build the message on mount
  const displayMessage = buildGreetingMessage();

  function handleMouseMove(e) {
    if (!cardElement) return;

    const rect = cardElement.getBoundingClientRect();
    const centerX = rect.left + rect.width / 2;
    const centerY = rect.top + rect.height / 2;

    // Calculate mouse position relative to card center (normalized -1 to 1)
    const mouseX = (e.clientX - centerX) / (rect.width / 2);
    const mouseY = (e.clientY - centerY) / (rect.height / 2);

    // Apply deadzone to prevent flickering near center (10% deadzone)
    const deadzone = 0.15;
    const adjustedX = Math.abs(mouseX) < deadzone ? 0 : mouseX;
    const adjustedY = Math.abs(mouseY) < deadzone ? 0 : mouseY;

    // Map to rotation angles
    const maxRotation = 2.5;
    rotateY = adjustedX * maxRotation;
    rotateX = -adjustedY * maxRotation;
  }

  function handleMouseEnter() {
    isHovering = true;
  }

  function handleMouseLeave() {
    isHovering = false;
    // Reset to neutral position when mouse leaves
    rotateX = 0;
    rotateY = 0;
  }
</script>

<div class="greeting-card-wrapper">
  <!-- Rotating gradient background behind the card -->
  <div class="gradient-shadow"></div>

  <div
    class="greeting-card"
    class:hovering={isHovering}
    bind:this={cardElement}
    onmouseenter={handleMouseEnter}
    onmousemove={handleMouseMove}
    onmouseleave={handleMouseLeave}
    aria-role="we"
    style="transform: perspective(1000px) rotateX({rotateX}deg) rotateY({rotateY}deg);"
  >
    <AtomLogo size={350} />

    <h2 class="greeting-message">{displayMessage}</h2>
  </div>
</div>