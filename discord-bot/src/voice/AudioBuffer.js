import { EventEmitter } from 'events';
import { spawn } from 'child_process';
import ffmpegPath from 'ffmpeg-static';
import { createLogger } from '../logger.js';

const log = createLogger('audio-buffer');

// Silence detection: an Opus frame of ~3 bytes or less is silence
const SILENCE_FRAME_THRESHOLD = 3;

export class AudioBuffer extends EventEmitter {
    constructor(audioConfig) {
        super();
        this.silenceThresholdMs = audioConfig.silenceThresholdMs;
        this.maxSegmentMs = audioConfig.maxSegmentMs;
        this.minSegmentMs = audioConfig.minSegmentMs;

        this.opusPackets = [];
        this.segmentStartTime = null;
        this.lastAudioTime = null;
        this.silenceTimer = null;
        this.hasSpeech = false;
    }

    push(opusPacket) {
        const now = Date.now();
        const isSilence = opusPacket.length <= SILENCE_FRAME_THRESHOLD;

        if (!isSilence) {
            this.hasSpeech = true;
            this.lastAudioTime = now;

            if (!this.segmentStartTime) {
                this.segmentStartTime = now;
            }

            // Store raw Opus packet — let ffmpeg decode later
            this.opusPackets.push(Buffer.from(opusPacket));

            this._resetSilenceTimer();

            const segmentDuration = now - this.segmentStartTime;
            if (segmentDuration >= this.maxSegmentMs) {
                log.debug('Max segment duration reached, flushing');
                this._flush();
            }
        } else if (this.hasSpeech) {
            this._resetSilenceTimer();
        }
    }

    _resetSilenceTimer() {
        if (this.silenceTimer) {
            clearTimeout(this.silenceTimer);
        }

        this.silenceTimer = setTimeout(() => {
            if (this.hasSpeech) {
                this._flush();
            }
        }, this.silenceThresholdMs);
    }

    _flush() {
        if (this.silenceTimer) {
            clearTimeout(this.silenceTimer);
            this.silenceTimer = null;
        }

        if (this.opusPackets.length === 0) {
            this._reset();
            return;
        }

        const durationMs = this.segmentStartTime ? Date.now() - this.segmentStartTime : 0;

        if (durationMs < this.minSegmentMs) {
            log.debug(`Segment too short (${durationMs}ms), discarding`);
            this._reset();
            return;
        }

        const packets = this.opusPackets;
        this._reset();

        log.info(`Segment: ${durationMs}ms, ${packets.length} opus packets`);

        // Convert raw Opus packets → WAV using ffmpeg with Opus decoding
        this._opusToWav(packets).then((wavBuffer) => {
            if (wavBuffer && wavBuffer.length > 44) {
                this.emit('segment', wavBuffer, durationMs);
            }
        }).catch((err) => {
            log.error('Opus to WAV conversion failed:', err);
        });
    }

    _reset() {
        this.opusPackets = [];
        this.segmentStartTime = null;
        this.lastAudioTime = null;
        this.hasSpeech = false;
    }

    _opusToWav(opusPackets) {
        return new Promise((resolve, reject) => {
            // Build an OggS container with Opus data so ffmpeg can decode it.
            // Each Discord Opus packet is a single Opus frame (20ms at 48kHz).
            const oggBuffer = this._buildOggOpus(opusPackets);

            const ffmpeg = spawn(ffmpegPath, [
                '-f', 'ogg',             // Input: Ogg/Opus container
                '-i', 'pipe:0',
                '-ar', '16000',          // Whisper expects 16kHz
                '-ac', '1',              // Mono
                '-f', 'wav',
                'pipe:1',
            ], {
                stdio: ['pipe', 'pipe', 'pipe'],
            });

            const chunks = [];
            ffmpeg.stdout.on('data', (chunk) => chunks.push(chunk));
            ffmpeg.stderr.on('data', () => {});

            ffmpeg.on('close', (code) => {
                if (code === 0) {
                    resolve(Buffer.concat(chunks));
                } else {
                    reject(new Error(`ffmpeg exited with code ${code}`));
                }
            });

            ffmpeg.on('error', reject);
            ffmpeg.stdin.write(oggBuffer);
            ffmpeg.stdin.end();
        });
    }

    /**
     * Build a minimal Ogg/Opus container from raw Opus packets.
     * Ogg format: https://www.xiph.org/ogg/doc/framing.html
     * Opus in Ogg: https://tools.ietf.org/html/rfc7845
     */
    _buildOggOpus(opusPackets) {
        const pages = [];
        let granulePos = 0n;
        const serialNo = 0x50524D45; // "PRME"
        let pageSeq = 0;

        // Page 1: OpusHead header
        const opusHead = Buffer.alloc(19);
        opusHead.write('OpusHead', 0);          // Magic
        opusHead.writeUInt8(1, 8);               // Version
        opusHead.writeUInt8(2, 9);               // Channel count (stereo)
        opusHead.writeUInt16LE(0, 10);           // Pre-skip
        opusHead.writeUInt32LE(48000, 12);       // Sample rate
        opusHead.writeUInt16LE(0, 16);           // Output gain
        opusHead.writeUInt8(0, 18);              // Channel mapping family
        pages.push(this._buildOggPage(opusHead, serialNo, pageSeq++, 0n, 0x02)); // BOS flag

        // Page 2: OpusTags header
        const vendor = 'prime-bot';
        const tagsSize = 8 + 4 + vendor.length + 4;
        const opusTags = Buffer.alloc(tagsSize);
        opusTags.write('OpusTags', 0);
        opusTags.writeUInt32LE(vendor.length, 8);
        opusTags.write(vendor, 12);
        opusTags.writeUInt32LE(0, 12 + vendor.length); // No user comments
        pages.push(this._buildOggPage(opusTags, serialNo, pageSeq++, 0n, 0x00));

        // Data pages: each Opus packet becomes a segment in an Ogg page
        // Group up to 255 packets per page (Ogg max segments)
        const PACKETS_PER_PAGE = 50;
        for (let i = 0; i < opusPackets.length; i += PACKETS_PER_PAGE) {
            const batch = opusPackets.slice(i, i + PACKETS_PER_PAGE);
            const isLast = (i + PACKETS_PER_PAGE >= opusPackets.length);

            // Each packet is 20ms = 960 samples at 48kHz
            for (const pkt of batch) {
                granulePos += 960n;
            }

            pages.push(this._buildOggPageMulti(batch, serialNo, pageSeq++, granulePos, isLast ? 0x04 : 0x00));
        }

        return Buffer.concat(pages);
    }

    _buildOggPage(data, serialNo, pageSeq, granulePos, flags) {
        return this._buildOggPageMulti([data], serialNo, pageSeq, granulePos, flags);
    }

    _buildOggPageMulti(segments, serialNo, pageSeq, granulePos, flags) {
        const numSegments = segments.length;
        const segTable = Buffer.alloc(numSegments);
        let totalDataLen = 0;

        for (let i = 0; i < numSegments; i++) {
            // For simplicity, each segment must be < 255 bytes for single-byte lacing.
            // If larger, we need multi-byte lacing.
            const len = segments[i].length;
            if (len < 255) {
                segTable[i] = len;
            } else {
                // For segments >= 255 bytes, we need proper lacing
                // Build proper segment table
                return this._buildOggPageLaced(segments, serialNo, pageSeq, granulePos, flags);
            }
            totalDataLen += len;
        }

        // 27 byte header + segment table + data
        const header = Buffer.alloc(27);
        header.write('OggS', 0);                      // Capture pattern
        header.writeUInt8(0, 4);                       // Version
        header.writeUInt8(flags, 5);                   // Header type flags
        header.writeBigUInt64LE(granulePos, 6);        // Granule position
        header.writeUInt32LE(serialNo, 14);            // Serial number
        header.writeUInt32LE(pageSeq, 18);             // Page sequence
        header.writeUInt32LE(0, 22);                   // CRC (filled later)
        header.writeUInt8(numSegments, 26);            // Number of segments

        const dataBuffer = Buffer.concat(segments);
        const page = Buffer.concat([header, segTable, dataBuffer]);

        // Calculate CRC32
        const crc = this._oggCRC(page);
        page.writeUInt32LE(crc, 22);

        return page;
    }

    _buildOggPageLaced(segments, serialNo, pageSeq, granulePos, flags) {
        // Build proper segment table with lacing for segments >= 255 bytes
        const segTableParts = [];
        let totalDataLen = 0;

        for (const seg of segments) {
            let remaining = seg.length;
            while (remaining >= 255) {
                segTableParts.push(255);
                remaining -= 255;
            }
            segTableParts.push(remaining);
            totalDataLen += seg.length;
        }

        const numSegments = segTableParts.length;
        const segTable = Buffer.from(segTableParts);

        const header = Buffer.alloc(27);
        header.write('OggS', 0);
        header.writeUInt8(0, 4);
        header.writeUInt8(flags, 5);
        header.writeBigUInt64LE(granulePos, 6);
        header.writeUInt32LE(serialNo, 14);
        header.writeUInt32LE(pageSeq, 18);
        header.writeUInt32LE(0, 22);
        header.writeUInt8(numSegments, 26);

        const dataBuffer = Buffer.concat(segments);
        const page = Buffer.concat([header, segTable, dataBuffer]);

        const crc = this._oggCRC(page);
        page.writeUInt32LE(crc, 22);

        return page;
    }

    _oggCRC(data) {
        // OggS uses CRC-32 with polynomial 0x04C11DB7 (no reflection)
        let crc = 0;
        for (let i = 0; i < data.length; i++) {
            crc = ((crc << 8) ^ OGG_CRC_TABLE[((crc >>> 24) & 0xFF) ^ data[i]]) >>> 0;
        }
        return crc;
    }
}

// Precompute OggS CRC table
const OGG_CRC_TABLE = new Uint32Array(256);
for (let i = 0; i < 256; i++) {
    let r = i << 24;
    for (let j = 0; j < 8; j++) {
        r = (r & 0x80000000) ? ((r << 1) ^ 0x04C11DB7) : (r << 1);
        r = r >>> 0;
    }
    OGG_CRC_TABLE[i] = r;
}
