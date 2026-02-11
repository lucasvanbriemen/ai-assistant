<script>
  import AtomLogo from '@/components/AtomLogo.svelte';
  import '@styles/GreetingCard.scss';

  let rotateX = $state(0);
  let rotateY = $state(0);
  let cardElement = $state(null);
  let isHovering = $state(false);

  const possibleMessages = [
    "What can I help with?",
    "How can I assist you today?",
    "How do you need help?",
    "What can you do for me?",
    "Can you help me with anything?",
    "I need some assistance, how can I help?",
    "I'm in need of some help, what can you do?",
    "Can you assist me with anything?",
    "I need some help, what can you do?",
    "What can you help me with?",
    "How can I assist you?",
  ];

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
    style="transform: perspective(1000px) rotateX({rotateX}deg) rotateY({rotateY}deg);"
  >
    <AtomLogo size={350} />

    <h2>{possibleMessages[Math.floor(Math.random() * possibleMessages.length)]}</h2>
  </div>
</div>