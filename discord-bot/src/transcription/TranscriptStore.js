import initSqlJs from 'sql.js';
import fs from 'fs';
import { fileURLToPath } from 'url';
import path from 'path';
import { createLogger } from '../logger.js';

const log = createLogger('transcript-store');

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const DB_PATH = path.resolve(__dirname, '../../data/transcripts.sqlite');

export class TranscriptStore {
    constructor(dbPath = DB_PATH) {
        this.dbPath = dbPath;
        this.db = null;
        this._ready = this._init(dbPath);
    }

    async _init(dbPath) {
        const SQL = await initSqlJs();

        // Load existing DB or create new
        if (fs.existsSync(dbPath)) {
            const fileBuffer = fs.readFileSync(dbPath);
            this.db = new SQL.Database(fileBuffer);
        } else {
            this.db = new SQL.Database();
        }

        this._migrate();
        log.info(`Database opened at ${dbPath}`);
    }

    async ready() {
        await this._ready;
    }

    _migrate() {
        this.db.run(`
            CREATE TABLE IF NOT EXISTS sessions (
                id TEXT PRIMARY KEY,
                guild_id TEXT NOT NULL,
                channel_id TEXT NOT NULL,
                started_at TEXT NOT NULL,
                ended_at TEXT,
                participant_ids TEXT
            )
        `);

        this.db.run(`
            CREATE TABLE IF NOT EXISTS transcripts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                guild_id TEXT NOT NULL,
                channel_id TEXT NOT NULL,
                speaker TEXT NOT NULL DEFAULT 'room',
                text TEXT NOT NULL,
                language TEXT,
                confidence REAL,
                audio_duration_ms INTEGER,
                started_at TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                session_id TEXT NOT NULL
            )
        `);

        this.db.run(`
            CREATE TABLE IF NOT EXISTS commands (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                session_id TEXT NOT NULL,
                trigger_type TEXT NOT NULL,
                trigger_text TEXT NOT NULL,
                context_text TEXT,
                response_text TEXT,
                requested_by TEXT NOT NULL DEFAULT 'room',
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                status TEXT DEFAULT 'pending'
            )
        `);

        this.db.run(`CREATE INDEX IF NOT EXISTS idx_transcripts_channel_time ON transcripts(channel_id, started_at)`);
        this.db.run(`CREATE INDEX IF NOT EXISTS idx_transcripts_session ON transcripts(session_id)`);
    }

    _save() {
        const data = this.db.export();
        const buffer = Buffer.from(data);
        fs.writeFileSync(this.dbPath, buffer);
    }

    createSession(id, guildId, channelId) {
        this.db.run(
            `INSERT INTO sessions (id, guild_id, channel_id, started_at) VALUES (?, ?, ?, datetime('now'))`,
            [id, guildId, channelId],
        );
        this._save();
        log.info(`Session created: ${id}`);
    }

    endSession(id) {
        this.db.run(`UPDATE sessions SET ended_at = datetime('now') WHERE id = ?`, [id]);
        this._save();
        log.info(`Session ended: ${id}`);
    }

    addTranscript({ guildId, channelId, speaker, text, language, confidence, audioDurationMs, startedAt, sessionId }) {
        this.db.run(
            `INSERT INTO transcripts (guild_id, channel_id, speaker, text, language, confidence, audio_duration_ms, started_at, session_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`,
            [guildId, channelId, speaker, text, language, confidence, audioDurationMs, startedAt, sessionId],
        );
        this._save();
    }

    addCommand({ sessionId, triggerType, triggerText, contextText, requestedBy }) {
        this.db.run(
            `INSERT INTO commands (session_id, trigger_type, trigger_text, context_text, requested_by, status)
             VALUES (?, ?, ?, ?, ?, 'pending')`,
            [sessionId, triggerType, triggerText, contextText, requestedBy],
        );
        this._save();
    }

    updateCommandResponse(triggerText, responseText) {
        // sql.js doesn't support ORDER BY in UPDATE, so find the row first
        const rows = this.db.exec(
            `SELECT id FROM commands WHERE trigger_text = ? AND status = 'pending' ORDER BY id DESC LIMIT 1`,
            [triggerText],
        );
        if (rows.length > 0 && rows[0].values.length > 0) {
            const id = rows[0].values[0][0];
            this.db.run(`UPDATE commands SET response_text = ?, status = 'completed' WHERE id = ?`, [responseText, id]);
            this._save();
        }
    }

    getRecentTranscripts(channelId, minutes = 10) {
        const results = this.db.exec(
            `SELECT * FROM transcripts WHERE channel_id = ? AND started_at >= datetime('now', ?) ORDER BY started_at ASC`,
            [channelId, `-${minutes} minutes`],
        );
        if (results.length === 0) return [];
        const columns = results[0].columns;
        return results[0].values.map((row) => {
            const obj = {};
            columns.forEach((col, i) => { obj[col] = row[i]; });
            return obj;
        });
    }

    close() {
        if (this.db) {
            this._save();
            this.db.close();
        }
    }
}
