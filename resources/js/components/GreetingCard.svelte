<script>
  import AtomLogo from '@/components/AtomLogo.svelte';
  import { buildGreetingMessage } from '@/utils/greetingBuilder.js';
  import '@styles/GreetingCard.scss';

  let rotateX = $state(0);
  let rotateY = $state(0);
  let cardElement = $state(null);
  let isHovering = $state(false);

  const username = "Lucas";
  const displayMessage = buildGreetingMessage(username);

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

  <div class="greeting-card" class:hovering={isHovering} bind:this={cardElement} onmouseenter={handleMouseEnter} onmousemove={handleMouseMove} onmouseleave={handleMouseLeave} style="transform: perspective(1000px) rotateX({rotateX}deg) rotateY({rotateY}deg);">
    <AtomLogo size={350} />

    <div class="ai-identity">
      <div class="ai-name">Prime</div>
      <div class="ai-acronym">Personal Responsive Intelligent Manager for Everything</div>
    </div>

    <h2 class="greeting-message">{displayMessage}</h2>
  </div>
</div>