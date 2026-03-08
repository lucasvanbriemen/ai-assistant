# Prime Discord Voice Bot

Voice assistant that sits in a Discord voice channel, transcribes speech, and responds to "Hey Prime" commands via AI.

## Prerequisites

- **Node.js** 22+
- **Laravel** backend running (the main svelte-test app)
- **Discord bot** created at https://discord.com/developers/applications
- **OpenAI API key** for Whisper (speech-to-text) and TTS (text-to-speech)

## Discord Bot Setup

1. Go to https://discord.com/developers/applications → **New Application**
2. **Bot** tab → **Reset Token** → copy the token
3. Enable under **Privileged Gateway Intents**:
   - Server Members Intent
   - Message Content Intent
4. **OAuth2** → **URL Generator** → select `bot` scope with permissions:
   - Connect, Speak, Use Voice Activity
   - Read Messages/View Channels, Send Messages, Embed Links
5. Open the generated URL → invite bot to your server

### Getting IDs

Enable **Developer Mode** in Discord (Settings → Advanced), then:

- Right-click your **server** → Copy Server ID → `DISCORD_GUILD_ID`
- Right-click a **voice channel** → Copy Channel ID → `DISCORD_VOICE_CHANNEL_ID`
- Right-click a **text channel** → Copy Channel ID → `DISCORD_TEXT_CHANNEL_ID`

## Installation

```bash
cd discord-bot
npm install --legacy-peer-deps
```

## Configuration

Copy the example env file and fill in your values:

```bash
cp .env.example .env
```

Edit `.env`:

```env
DISCORD_TOKEN=your-bot-token
DISCORD_GUILD_ID=your-server-id
DISCORD_VOICE_CHANNEL_ID=your-voice-channel-id
DISCORD_TEXT_CHANNEL_ID=your-text-channel-id
OPENAI_API_KEY=sk-your-openai-key
LARAVEL_API_URL=http://127.0.0.1:8000
LARAVEL_AGENT_TOKEN=13November.2006
```

## Running

### 1. Start Laravel (in a separate terminal)

```bash
cd C:\Users\vanbr\svelte-test
php artisan serve
```

### 2. Start the bot

```bash
cd C:\Users\vanbr\svelte-test\discord-bot
node src/index.js
```

You should see:

```
Logged in as Prime#0179
Joined voice channel: General
Audio pipeline active — listening for speech
```

### 3. Talk to Prime

Join the same voice channel in Discord and say:

- **"Hey Prime, what is two plus two?"** — inline command
- **"Prime, summarize the last 5 minutes"** — deferred command (uses transcript context)

Prime responds with both **voice** (TTS in the voice channel) and **text** (in the configured text channel).

## Running with pm2 (persistent)

```bash
npm install -g pm2
pm2 start src/index.js --name prime-bot
pm2 save
```

To restart after changes:

```bash
pm2 restart prime-bot
```

View logs:

```bash
pm2 logs prime-bot
```

## Wake Words

| Pattern | Example |
|---------|---------|
| "Hey Prime, ..." | "Hey Prime, what time is it?" |
| "Hee Prime, ..." | "Hee Prime, hoe laat is het?" |
| "Prime, ..." (at sentence start) | "Prime, summarize the last 5 minutes" |

## Cost

- **Whisper STT**: ~$0.18/hour (30 min actual speech)
- **Claude API**: Free (via Anthropic OAuth token)
- **OpenAI TTS**: ~$0.01/hour
- **Total**: ~$0.19/hour

## Troubleshooting

| Problem | Fix |
|---------|-----|
| Bot won't join voice channel | Make sure `@snazzah/davey` is installed (`npm ls @snazzah/davey`) |
| No transcriptions | Check you're in the same voice channel as the bot |
| Wrong transcriptions | Whisper struggles with background noise — speak clearly |
| "ECONNREFUSED" errors | Laravel isn't running — start `php artisan serve` |
| "Unauthorized" from API | Check `LARAVEL_AGENT_TOKEN` matches `AGENT_TOKEN` in Laravel's `.env` |
