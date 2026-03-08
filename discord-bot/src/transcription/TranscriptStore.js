import config from '../config.js';
import fs from 'fs';
import initSqlJs from 'sql.js';
import path from 'path';

export class TranscriptStore {
    constructor() {
        this.db = null;
        this.driver = config.database.connection;
        this.pool = null;
        this._ready = this._init();
    }

    async _init() {
        if (this.driver === 'sqlite') {
            await this._initSqlite();
        } else {
            await this._initMysql();
        }
    }

    async _initSqlite() {
        // For SQLite, DB_DATABASE is the path relative to Laravel root, or we use the default
        const dbName = config.database.database;
        const dbPath = path.isAbsolute(dbName)
            ? dbName
            : path.resolve(config.database.laravelRoot, 'database', dbName === 'laravel' ? 'database.sqlite' : dbName);

        const SQL = await initSqlJs();

        if (fs.existsSync(dbPath)) {
            const fileBuffer = fs.readFileSync(dbPath);
            this.db = new SQL.Database(fileBuffer);
        } else {
            throw new Error(`Laravel database not found at ${dbPath}. Run "php artisan migrate" first.`);
        }

        this._dbPath = dbPath;
    }

    async _initMysql() {
        const mysql = await import('mysql2/promise');
        this.pool = mysql.createPool({
            host: config.database.host,
            port: config.database.port,
            database: config.database.database,
            user: config.database.username,
            password: config.database.password,
            waitForConnections: true,
            connectionLimit: 5,
        });
    }

    async ready() {
        await this._ready;
    }

    // --- Query helpers ---

    async _run(sql, params = []) {
        if (this.driver === 'sqlite') {
            this.db.run(sql, params);
            this._save();
        } else {
            await this.pool.execute(sql, params);
        }
    }

    async _query(sql, params = []) {
        if (this.driver === 'sqlite') {
            const results = this.db.exec(sql, params);
            if (results.length === 0) return [];
            const columns = results[0].columns;
            return results[0].values.map((row) => {
                const obj = {};
                columns.forEach((col, i) => { obj[col] = row[i]; });
                return obj;
            });
        } else {
            const [rows] = await this.pool.execute(sql, params);
            return rows;
        }
    }

    _now() {
        return this.driver === 'sqlite' ? "datetime('now')" : 'NOW()';
    }

    _minutesAgo(minutes) {
        return this.driver === 'sqlite'
            ? `datetime('now', '-${minutes} minutes')`
            : `DATE_SUB(NOW(), INTERVAL ${minutes} MINUTE)`;
    }

    _save() {
        if (this.driver === 'sqlite' && this.db) {
            const data = this.db.export();
            fs.writeFileSync(this._dbPath, Buffer.from(data));
        }
    }

    // --- Public methods ---

    async createSession(id) {
        await this._run(
            `INSERT INTO voice_sessions (id, started_at) VALUES (?, ${this._now()})`,
            [id],
        );
    }

    async endSession(id) {
        await this._run(`UPDATE voice_sessions SET ended_at = ${this._now()} WHERE id = ?`, [id]);
    }

    async addTranscript({ text, language, confidence, audioDurationMs, startedAt, sessionId }) {
        await this._run(
            `INSERT INTO voice_transcripts (text, language, confidence, audio_duration_ms, started_at, session_id)
             VALUES (?, ?, ?, ?, ?, ?)`,
            [text, language, confidence, audioDurationMs, startedAt, sessionId],
        );
    }

    async addCommand({ sessionId, triggerType, triggerText, contextText }) {
        await this._run(
            `INSERT INTO voice_commands (session_id, trigger_type, trigger_text, context_text, status)
             VALUES (?, ?, ?, ?, 'pending')`,
            [sessionId, triggerType, triggerText, contextText],
        );
    }

    async updateCommandResponse(triggerText, responseText) {
        if (this.driver === 'sqlite') {
            const rows = this.db.exec(
                `SELECT id FROM voice_commands WHERE trigger_text = ? AND status = 'pending' ORDER BY id DESC LIMIT 1`,
                [triggerText],
            );
            if (rows.length > 0 && rows[0].values.length > 0) {
                const id = rows[0].values[0][0];
                this.db.run(`UPDATE voice_commands SET response_text = ?, status = 'completed' WHERE id = ?`, [responseText, id]);
                this._save();
            }
        } else {
            await this.pool.execute(
                `UPDATE voice_commands SET response_text = ?, status = 'completed' WHERE trigger_text = ? AND status = 'pending' ORDER BY id DESC LIMIT 1`,
                [responseText, triggerText],
            );
        }
    }

    async getRecentTranscripts(minutes = 10) {
        return this._query(
            `SELECT * FROM voice_transcripts WHERE started_at >= ${this._minutesAgo(minutes)} ORDER BY started_at ASC`,
        );
    }

    close() {
        if (this.driver === 'sqlite' && this.db) {
            this._save();
            this.db.close();
        } else if (this.pool) {
            this.pool.end();
        }
    }
}
