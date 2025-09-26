import winston from 'winston';
import path from 'path';
import fs from 'fs';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.join(__dirname, '..', '..');
const logsDir = path.join(projectRoot, 'logs');

if (!fs.existsSync(logsDir)) {
    fs.mkdirSync(logsDir, { recursive: true });
}

class Logger {
    constructor(label = 'App') {
        this.label = label;
        this.logger = winston.createLogger({
            level: process.env.LOG_LEVEL || 'info',
            format: winston.format.combine(
                winston.format.timestamp({
                    format: 'YYYY-MM-DD HH:mm:ss'
                }),
                winston.format.errors({ stack: true }),
                winston.format.label({ label }),
                winston.format.printf(({ level, message, label, timestamp, stack }) => {
                    return `${timestamp} [${label}] ${level.toUpperCase()}: ${stack || message}`;
                })
            ),
            defaultMeta: { service: 'whatsapp-bot' },
            transports: [
                new winston.transports.File({
                    filename: path.join(logsDir, 'error.log'),
                    level: 'error',
                    maxsize: 5242880,  
                    maxFiles: 5,
                }),
                new winston.transports.File({
                    filename: path.join(logsDir, 'combined.log'),
                    maxsize: 5242880, 
                    maxFiles: 10,
                }),
                new winston.transports.Console({
                    format: winston.format.combine(
                        winston.format.colorize(),
                        winston.format.simple()
                    )
                })
            ]
        });

        // if (process.env.NODE_ENV !== 'production') {
        //     this.logger.add(new winston.transports.Console({
        //         format: winston.format.simple()
        //     }));
        // }
    }

    info(message, meta = {}) {
        this.logger.info(message, meta);
    }

    error(message, error = null) {
        if (error && error.stack) {
            this.logger.error(`${message}: ${error.message}`, { stack: error.stack });
        } else {
            this.logger.error(message, error);
        }
    }

    warn(message, meta = {}) {
        this.logger.warn(message, meta);
    }

    debug(message, meta = {}) {
        this.logger.debug(message, meta);
    }

    verbose(message, meta = {}) {
        this.logger.verbose(message, meta);
    }
}

export default Logger;