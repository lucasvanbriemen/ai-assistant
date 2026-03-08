import { EventEmitter } from 'events';
import { EndBehaviorType } from '@discordjs/voice';
import { createLogger } from '../logger.js';

const log = createLogger('audio-receiver');

export class AudioReceiver extends EventEmitter {
    constructor(connection) {
        super();
        this.connection = connection;
        this.receiver = connection.receiver;
        this.activeStreams = new Map();
    }

    start() {
        // Listen for any user speaking in the channel
        this.receiver.speaking.on('start', (userId) => {
            if (this.activeStreams.has(userId)) return;

            log.debug(`User ${userId} started speaking`);

            const opusStream = this.receiver.subscribe(userId, {
                end: {
                    behavior: EndBehaviorType.AfterSilence,
                    duration: 100, // Short — AudioBuffer handles actual segmentation
                },
            });

            this.activeStreams.set(userId, opusStream);

            opusStream.on('data', (packet) => {
                this.emit('audio', packet);
            });

            opusStream.on('end', () => {
                log.debug(`User ${userId} stream ended`);
                this.activeStreams.delete(userId);
            });

            opusStream.on('error', (err) => {
                log.error(`Audio stream error for user ${userId}:`, err);
                this.activeStreams.delete(userId);
            });
        });

        log.info('Audio receiver started — listening for voice activity');
    }

    stop() {
        for (const [userId, stream] of this.activeStreams) {
            stream.destroy();
        }
        this.activeStreams.clear();
        log.info('Audio receiver stopped');
    }
}
