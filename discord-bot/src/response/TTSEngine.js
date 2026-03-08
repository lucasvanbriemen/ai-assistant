import OpenAI from 'openai';

export class TTSEngine {
    constructor(apiKey, voice = 'onyx') {
        this.openai = new OpenAI({ apiKey });
        this.voice = voice;
    }

    async synthesize(text) {
        if (!text || text.trim().length === 0) {
            return null;
        }

        // TTS has a 4096 character limit — truncate if needed
        const input = text.length > 4000 ? text.substring(0, 4000) + '...' : text;

        const response = await this.openai.audio.speech.create({
            model: 'tts-1',
            voice: this.voice,
            input,
            response_format: 'opus', // Native Discord format — no transcoding needed
        });

        const arrayBuffer = await response.arrayBuffer();
        const buffer = Buffer.from(arrayBuffer);

        return buffer;
    }
}
