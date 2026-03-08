import { EndBehaviorType } from '@discordjs/voice';
import { EventEmitter } from 'events';

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
                this.activeStreams.delete(userId);
            });

            opusStream.on('error', (err) => {
                this.activeStreams.delete(userId);
            });
        });

    }

    stop() {
        for (const [userId, stream] of this.activeStreams) {
            stream.destroy();
        }
        this.activeStreams.clear();
    }
}
