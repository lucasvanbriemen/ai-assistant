import {
    joinVoiceChannel,
    VoiceConnectionStatus,
    entersState,
    createAudioPlayer,
    createAudioResource,
    AudioPlayerStatus,
    StreamType,
} from '@discordjs/voice';
import { createLogger } from '../logger.js';

const log = createLogger('voice-manager');

export class VoiceManager {
    constructor(voiceChannel) {
        this.voiceChannel = voiceChannel;
        this.connection = null;
        this.player = createAudioPlayer();
        this.isReady = false;
    }

    async join() {
        this.isReady = false;

        this.connection = joinVoiceChannel({
            channelId: this.voiceChannel.id,
            guildId: this.voiceChannel.guild.id,
            adapterCreator: this.voiceChannel.guild.voiceAdapterCreator,
            selfDeaf: false,
            selfMute: false,
        });

        // Log all state changes for debugging
        this.connection.on('stateChange', (oldState, newState) => {
            log.info(`Voice connection: ${oldState.status} -> ${newState.status}`);
        });

        // Only handle disconnects after initial connection succeeds
        this.connection.on(VoiceConnectionStatus.Disconnected, async () => {
            if (!this.isReady) return; // Don't interfere with initial connection
            try {
                log.warn('Voice connection disconnected, attempting reconnect...');
                await Promise.race([
                    entersState(this.connection, VoiceConnectionStatus.Signalling, 5_000),
                    entersState(this.connection, VoiceConnectionStatus.Connecting, 5_000),
                ]);
            } catch {
                log.warn('Reconnect failed, destroying and rejoining...');
                this.connection.destroy();
                await this.join();
            }
        });

        this.connection.on(VoiceConnectionStatus.Destroyed, () => {
            log.info('Voice connection destroyed');
            this.isReady = false;
        });

        try {
            await entersState(this.connection, VoiceConnectionStatus.Ready, 30_000);
            this.isReady = true;
            log.info(`Joined voice channel: ${this.voiceChannel.name}`);
        } catch (err) {
            log.error(`Voice join failed. Destroying connection.`);
            this.connection.destroy();
            throw new Error(`Failed to join voice channel: ${err.message}`);
        }

        this.connection.subscribe(this.player);

        return this.connection;
    }

    async playAudio(audioBuffer) {
        return new Promise((resolve, reject) => {
            const resource = createAudioResource(audioBuffer, {
                inputType: StreamType.OggOpus,
            });

            this.player.play(resource);

            this.player.once(AudioPlayerStatus.Idle, resolve);
            this.player.once('error', reject);
        });
    }

    leave() {
        if (this.connection) {
            this.connection.destroy();
            this.connection = null;
            log.info('Left voice channel');
        }
    }
}
