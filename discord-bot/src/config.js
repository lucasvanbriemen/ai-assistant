import 'dotenv/config';

const required = [
    'DISCORD_TOKEN',
    'DISCORD_GUILD_ID',
    'DISCORD_VOICE_CHANNEL_ID',
    'DISCORD_TEXT_CHANNEL_ID',
    'OPENAI_API_KEY',
];

for (const key of required) {
    if (!process.env[key]) {
        throw new Error(`Missing required environment variable: ${key}`);
    }
}

const config = {
    discord: {
        token: process.env.DISCORD_TOKEN,
        guildId: process.env.DISCORD_GUILD_ID,
        voiceChannelId: process.env.DISCORD_VOICE_CHANNEL_ID,
        textChannelId: process.env.DISCORD_TEXT_CHANNEL_ID,
    },
    openai: {
        apiKey: process.env.OPENAI_API_KEY,
    },
    laravel: {
        apiUrl: process.env.LARAVEL_API_URL || 'http://localhost:8000',
        agentToken: process.env.LARAVEL_AGENT_TOKEN || '',
    },
    audio: {
        silenceThresholdMs: parseInt(process.env.SILENCE_THRESHOLD_MS || '1500', 10),
        maxSegmentMs: parseInt(process.env.MAX_SEGMENT_MS || '30000', 10),
        minSegmentMs: 500,
    },
    transcript: {
        bufferMinutes: parseInt(process.env.TRANSCRIPT_BUFFER_MINUTES || '10', 10),
    },
    tts: {
        voice: process.env.TTS_VOICE || 'onyx',
    },
    logLevel: process.env.LOG_LEVEL || 'info',
};

export default config;
