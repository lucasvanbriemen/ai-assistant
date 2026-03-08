import winston from 'winston';
import config from './config.js';

const baseLogger = winston.createLogger({
    level: config.logLevel,
    format: winston.format.combine(
        winston.format.timestamp({ format: 'YYYY-MM-DD HH:mm:ss' }),
        winston.format.printf(({ timestamp, level, message, module, ...rest }) => {
            const mod = module ? `[${module}]` : '';
            const extra = Object.keys(rest).length ? ' ' + JSON.stringify(rest) : '';
            return `${timestamp} ${level.toUpperCase()} ${mod} ${message}${extra}`;
        }),
    ),
    transports: [
        new winston.transports.Console(),
        new winston.transports.File({
            filename: new URL('../data/bot.log', import.meta.url).pathname.replace(/^\/([A-Z]:)/, '$1'),
            maxsize: 5 * 1024 * 1024,
            maxFiles: 3,
        }),
    ],
});

export function createLogger(moduleName) {
    return baseLogger.child({ module: moduleName });
}
