<script>
  import ChatBot from '../components/ChatBot.svelte';
</script>

<div class="chat-page">
  <div class="chat-container">
    <ChatBot />
  </div>
</div>

<style>
  .chat-page {
    display: flex;
    flex-direction: column;
    height: 100vh;
    padding: 20px;
    background: #f5f5f5;
  }

  .chat-container {
    flex: 1;
    max-width: 900px;
    margin: 0 auto;
    width: 100%;
  }
</style>
