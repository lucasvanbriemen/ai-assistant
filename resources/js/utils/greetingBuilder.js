/**
 * Get the current time-based greeting
 */
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

// Simple greeting options
const greetingOptions = [
  'Hey',
  'Hello',
  'Hi',
  'Welcome',
  'Greetings',
];

// Help messages
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

// Prime introduction variations
const primeIntros = [
  "I'm Prime.",
  "Prime here.",
  "This is Prime.",
  "Prime at your service.",
];

export function buildGreetingMessage(username) {
  const rand = Math.random();
  const timeGreeting = getTimeGreeting();
  const helpMsg = helpMessages[Math.floor(Math.random() * helpMessages.length)];
  const primeIntro = primeIntros[Math.floor(Math.random() * primeIntros.length)];

  // Randomly decide if we include Prime's name (40% chance)
  const includePrime = Math.random() < 0.4;
  const primeText = includePrime ? `${primeIntro} ` : '';

  // 30% - Time-based greeting with username
  if (rand < 0.3) {
    return `${timeGreeting}, ${username}! ${primeText}${helpMsg}`;
  }
  // 20% - Simple greeting with username
  else if (rand < 0.5) {
    const greeting = greetingOptions[Math.floor(Math.random() * greetingOptions.length)];
    return `${greeting}, ${username}! ${primeText}${helpMsg}`;
  }
  // 15% - Time-based greeting only
  else if (rand < 0.65) {
    return `${timeGreeting}! ${primeText}${helpMsg}`;
  }
  // 35% - Just the help message (with Prime intro more likely here)
  else {
    const soloIntro = Math.random() < 0.6 ? `${primeIntro} ` : '';
    return `${soloIntro}${helpMsg}`;
  }
}
