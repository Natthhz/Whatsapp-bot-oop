import baileys from '@whiskeysockets/baileys';
import qrcode from 'qrcode-terminal';
import qrcodelib from 'qrcode';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

import { BOT_STATUS, MEDIA_TYPES, MESSAGE_TYPES } from './config/constants.js';
import BotController from '../controllers/botcontroller.js';
import Message from '../models/message.js';
import Logger from '../utils/logger.js';
import { formatPhoneNumber, isAllowedNumber } from '../utils/helpers.js';
import Targets from '../models/target.js';

const { makeWASocket, useMultiFileAuthState, DisconnectReason, downloadMediaMessage } = baileys;

class Bot {
    constructor(config, databaseService) {
        this.config = config;
        this.databaseService = databaseService;
        this.sock = null;
        this.status = BOT_STATUS.DISCONNECTED;
        this.qrDataUrl = null;
        this.reconnectAttempts = 0;
        this.messageModel = new Message(databaseService, config.id);
        this.targetsModel = new Targets(databaseService, config.id);
        this.botController = new BotController(this);
        this.logger = new Logger(`Bot-${config.id}`);
        this.connectionChecks = new Map(); 
        this.messageQueue = [];
        this.isProcessingMessages = false;
        this.rateLimiter = new Map();
        this.sessionErrorCount = 0;
        this.maxSessionErrors = 5;
        this.lastHealthCheck = null;
        this.ensureDirectories();
        this.startMessageProcessor();
    }

    ensureDirectories() {
        const __filename = fileURLToPath(import.meta.url);
        const __dirname = path.dirname(__filename);
        const projectRoot = path.join(__dirname, '..', '..');

        const authDir = path.join(projectRoot, this.config.authDir);
        const receivedDir = path.join(projectRoot, process.env.RECEIVED_FILES_DIR || 'data/received_files');
        const sentDir = path.join(projectRoot, process.env.SENT_FILES_DIR || 'data/sent_files');

        [authDir, receivedDir, sentDir].forEach(dir => {
            if (!fs.existsSync(dir)) {
                fs.mkdirSync(dir, { recursive: true });
            }
        });
    }

    startMessageProcessor() {
        setInterval(() => {
            if (!this.isProcessingMessages && this.messageQueue.length > 0) {
                this.processMessageQueue();
            }
        }, 500);
    }

    async processMessageQueue() {
        if (this.isProcessingMessages || this.messageQueue.length === 0) {
            return;
        }

        this.isProcessingMessages = true;

        try {
            const batch = this.messageQueue.splice(0, 5);

            for (const messageData of batch) {
                try {
                    await this.processQueuedMessage(messageData);
                } catch (error) {
                    this.logger.error('Error processing queued message:', error);
                }
            }
        } finally {
            this.isProcessingMessages = false;
        }
    }

    async processQueuedMessage(messageData) {
        const { msg, messageText, senderName, phoneNumber, senderJid, chatJid, groupName } = messageData;

        try {
            if (this.isRateLimited(senderJid)) {
                this.logger.warn(`Rate limited message from ${senderJid}`);
                return;
            }
            const response = await this.botController.processCommand(messageText, {
                senderName,
                phoneNumber,
                senderJid,
                chatJid,
                groupName,
                sock: this.sock
            });

            if (response && response.text) {
                await this.sendMessageSafely(chatJid, response.text);
                this.logger.info(`[BOT RESPONSE] to ${senderName}: ${response.text.substring(0, 50)}...`);

                await this.logResponse(
                    response.text,
                    senderName,
                    phoneNumber,
                    senderJid,
                    chatJid,
                    groupName,
                    response.context,
                    messageText
                );
            }

        } catch (error) {
            this.logger.error('Error in processQueuedMessage:', error);
        }
    }

    isRateLimited(senderJid) {
        const now = Date.now();
        const limit = this.rateLimiter.get(senderJid) || [];

        const recentMessages = limit.filter(timestamp => now - timestamp < 60000);

        if (recentMessages.length >= 10) {
            return true;
        }

        recentMessages.push(now);
        this.rateLimiter.set(senderJid, recentMessages);
        return false;
    }

    async start() {
        try {
            this.logger.info(`Starting ${this.config.name}...`);
            this.status = BOT_STATUS.CONNECTING;

            const { state, saveCreds } = await useMultiFileAuthState(this.config.authDir);

            this.sock = makeWASocket({
                auth: state,
                printQRInTerminal: false,
                browser: ['Bot', 'Chrome', '1.0.0'],
                connectTimeoutMs: 60000, 
                defaultQueryTimeoutMs: 30000, 
                keepAliveIntervalMs: 30000, 
                markOnlineOnConnect: true,
                syncFullHistory: false,
                generateHighQualityLinkPreview: false,
                patchMessageBeforeSending: (message) => {
                    return message;
                },
                shouldIgnoreJid: (jid) => {
                    return jid === 'status@broadcast' || jid.includes('broadcast');
                },
                retryRequestDelayMs: 2000,
                maxMsgRetryCount: 3,
                appStateMacVerification: {
                    patch: true,
                    snapshot: true
                }
            });

            this.sock.ev.on('creds.update', saveCreds);
            this.setupEventHandlers();
            this.setupDeliveryTracking();


            return this.sock;
        } catch (error) {
            this.logger.error(`Error starting ${this.config.name}:`, error);
            this.status = BOT_STATUS.ERROR;
            throw error;
        }
    }

    setupEventHandlers() {
        this.sock.ev.on('connection.update', async ({ connection, lastDisconnect, qr }) => {
            await this.handleConnectionUpdate(connection, lastDisconnect, qr);
        });

        this.sock.ev.on('messages.upsert', async (m) => {
            await this.handleMessage(m);
        });

        this.sock.ev.on('CB:call', async (data) => {
            this.logger.info('Incoming call detected:', data);
        });

        this.sock.ev.on('CB:ib,,dirty', async (data) => {
            this.logger.debug('Dirty state update:', data);
        });

        this.sock.ev.on('presence.update', async (data) => {
            this.logger.debug('Presence update received');
        });
    }

    async handleConnectionUpdate(connection, lastDisconnect, qr) {
        if (qr) {
            this.logger.info(`Generating QR code for ${this.config.name}...`);
            qrcode.generate(qr, { small: true });

            try {
                this.qrDataUrl = await qrcodelib.toDataURL(qr);
                this.status = BOT_STATUS.WAITING_QR;
                this.logger.info(`Open http://localhost:${this.config.port || 3000} to scan QR code`);
            } catch (error) {
                this.logger.error('Error generating QR data URL:', error);
            }
        }

        if (connection === 'open') {
            this.logger.info(`${this.config.name} connected successfully!`);
            this.qrDataUrl = null;
            this.status = BOT_STATUS.CONNECTED;
            this.reconnectAttempts = 0;
            this.sessionErrorCount = 0;
            this.lastHealthCheck = Date.now();

            try {
                await this.sock.sendPresenceUpdate('available');
                this.logger.debug('Initial presence sent');
            } catch (error) {
                this.logger.warn('Failed to send initial presence:', error);
            }

        } else if (connection === 'close') {
            const shouldReconnect = this.shouldAttemptReconnect(lastDisconnect);
            this.logger.info(`${this.config.name} disconnected:`, lastDisconnect?.error?.message);

            if (shouldReconnect && this.config.autoReconnect && this.reconnectAttempts < this.config.maxReconnectAttempts) {
                this.reconnectAttempts++;
                const delay = Math.min(this.config.reconnectInterval * this.reconnectAttempts, 30000);

                this.logger.info(`Reconnecting ${this.config.name} in ${delay}ms... (attempt ${this.reconnectAttempts})`);

                setTimeout(() => {
                    this.start().catch(error => {
                        this.logger.error(`Failed to reconnect ${this.config.name}:`, error);
                    });
                }, delay);
            } else {
                this.logger.info(`${this.config.name} will not reconnect`);
                this.qrDataUrl = null;
                this.status = BOT_STATUS.DISCONNECTED;
            }
        } else if (connection === 'connecting') {
            this.logger.info(`${this.config.name} is connecting...`);
            this.status = BOT_STATUS.CONNECTING;
        }
    }

    shouldAttemptReconnect(lastDisconnect) {
        if (!lastDisconnect?.error) return true;

        const statusCode = lastDisconnect.error.output?.statusCode;
        const errorMessage = lastDisconnect.error.message?.toLowerCase() || '';

        if (statusCode === DisconnectReason.loggedOut) {
            this.logger.info('Logged out, QR scan required');
            return false;
        }

        if (statusCode === DisconnectReason.forbidden) {
            this.logger.error('Account banned/forbidden');
            return false;
        }

        if (errorMessage.includes('session') || errorMessage.includes('decrypt')) {
            this.sessionErrorCount++;
            this.logger.warn(`Session error count: ${this.sessionErrorCount}/${this.maxSessionErrors}`);

            if (this.sessionErrorCount >= this.maxSessionErrors) {
                this.logger.error('Too many session errors, stopping reconnections');
                return false;
            }
        }

        return true;
    }

   async handleMessage(messageUpdate) {
        const msg = messageUpdate.messages[0];
        if (!msg.message || msg.key.fromMe) return;

        const senderJid = msg.key.participant || msg.key.remoteJid;
        const chatJid = msg.key.remoteJid;
        const senderName = msg.pushName || "Unknown";
        const phoneNumber = formatPhoneNumber(senderJid);

        if (!isAllowedNumber(senderJid, this.config.allowedNumbers)) {
            this.logger.warn(`Access denied from: ${senderName} (${phoneNumber || senderJid})`);
            return;
        }

        let groupName = null;
        if (chatJid.includes('@g.us')) {
            try {
                const groupMetadata = await this.sock.groupMetadata(chatJid);
                groupName = groupMetadata.subject;
            } catch (error) {
                this.logger.error('Error getting group metadata:', error);
            }
        }

        const mediaType = Object.keys(msg.message).find(key =>
            Object.values(MEDIA_TYPES).includes(key)
        );

        if (mediaType) {
            await this.handleMediaMessage(msg, mediaType, senderName, phoneNumber, senderJid, chatJid, groupName);
            return;
        }

        const messageText = msg.message.conversation || msg.message.extendedTextMessage?.text || "";

        if (messageText.trim()) {
            await this.saveIncomingMessage(msg, messageText, senderName, phoneNumber, senderJid, chatJid, groupName);

            this.messageQueue.push({
                msg,
                messageText,
                senderName,
                phoneNumber,
                senderJid,
                chatJid,
                groupName
            });

            this.logger.info(`[USER MESSAGE] ${senderName}: ${messageText}`);
        }
    }

     async saveIncomingMessage(msg, messageText, senderName, phoneNumber, senderJid, chatJid, groupName) {
        try {
            await this.messageModel.createIncoming({
                senderJid: senderJid,
                message: messageText,
                senderName: senderName,
                senderPhone: phoneNumber,
                botId: this.config.id
            });
        } catch (error) {
            this.logger.warn('Failed to save incoming message to DB:', error);
        }
    }

    shouldAttemptReconnect(lastDisconnect) {
        if (!lastDisconnect?.error) return true;

        const statusCode = lastDisconnect.error.output?.statusCode;
        const errorMessage = lastDisconnect.error.message?.toLowerCase() || '';

        if (statusCode === DisconnectReason.loggedOut) {
            this.logger.info('Logged out, QR scan required');
            return false;
        }

        if (statusCode === DisconnectReason.forbidden) {
            this.logger.error('Account banned/forbidden');
            return false;
        }

        if (errorMessage.includes('session') || errorMessage.includes('decrypt') || errorMessage.includes('stale')) {
            this.sessionErrorCount++;
            this.logger.warn(`Session error count: ${this.sessionErrorCount}/${this.maxSessionErrors}`);

            if (errorMessage.includes('stale')) {
                this.logger.info('Cleaning up stale session...');
            }

            if (this.sessionErrorCount >= this.maxSessionErrors) {
                this.logger.error('Too many session errors, stopping reconnections');
                return false;
            }

            return true;
        }

        return true;
    }

    async isConnectionHealthy() {
        if (this.status !== BOT_STATUS.CONNECTED || !this.sock || !this.sock.user) {
            return false;
        }

        try {
            const now = Date.now();

            if (this.lastHealthCheck && (now - this.lastHealthCheck) < 10000) {
                return true;
            }

            const startTime = Date.now();
            await this.sock.sendPresenceUpdate('available');
            const responseTime = Date.now() - startTime;

            this.lastHealthCheck = now;

            const isHealthy = responseTime < 8000; 

            if (!isHealthy) {
                this.logger.warn(`Connection health check slow: ${responseTime}ms`);
            }

            return isHealthy;

        } catch (error) {
            this.logger.warn('Connection health check failed:', error.message);
            return false;
        }
    }

    async sendMessageSafely(chatJid, text, retries = 3) {
        for (let attempt = 0; attempt < retries; attempt++) {
            try {
                if (!this.sock || this.status !== BOT_STATUS.CONNECTED) {
                    throw new Error('Bot not connected');
                }

                await this.sock.sendMessage(chatJid, { text });
                return true;

            } catch (error) {
                this.logger.warn(`Send attempt ${attempt + 1} failed: ${error.message}`);

                if (attempt < retries - 1) {
                    await this.delay(1000 * (attempt + 1)); 
                }
            }
        }

        this.logger.error(`Failed to send message after ${retries} attempts`);
        return false;
    }

async sendMessage(jid, message) {
    try {
        if (!this.sock || this.status !== 'connected') {
            throw new Error(`Bot is not connected (status: ${this.status})`);
        }

        if (!this.sock.user) {
            throw new Error('Bot socket not authenticated');
        }

        const formattedJid = jid.includes('@') ? jid : `${jid}@s.whatsapp.net`;
        
        try {
            await this.sock.sendPresenceUpdate('available');
        } catch (presenceError) {
            this.logger.warn('Failed to update presence before sending:', presenceError.message);
        }
        
        const result = await this.sock.sendMessage(formattedJid, { text: message });
        
        this.logger.info(`Message sent to ${formattedJid}:`, message.substring(0, 100));
        return result;
        
    } catch (error) {
        this.logger.error(`Failed to send message to ${jid}:`, {
            error: error.message,
            status: this.status,
            hasSocket: !!this.sock,
            hasUser: !!(this.sock && this.sock.user)
        });
        throw error;
    }
}

setupDeliveryTracking() {
    if (!this.sock) return;

    this.sock.ev.on('messages.receipt-update', async (receipt) => {
        try {
            for (const { key, receipt: receiptInfo } of receipt) {
                if (receiptInfo.readTimestamp || receiptInfo.receiptTimestamp) {
                    const status = receiptInfo.readTimestamp ? 'read' : 'delivered';
                    
                    if (this.botManager && this.botManager.messageWorker) {
                        await this.botManager.messageWorker.updateDeliveryStatus(
                            key.id, 
                            this.config.id, 
                            status
                        );
                    }
                    
                    this.logger.info(`Message ${key.id} ${status} by ${key.remoteJid}`);
                }
            }
        } catch (error) {
            this.logger.error('Error handling delivery receipt:', error);
        }
    });
}



    async logResponse(responseText, senderName, phoneNumber, senderJid, chatJid, groupName, context, originalCommand) {
        try {
            await this.messageModel.create({
                id: Date.now().toString(),
                type: MESSAGE_TYPES.BOT_RESPONSE,
                message: responseText,
                recipient: {
                    name: senderName,
                    phone: phoneNumber,
                    jid: senderJid,
                    chatJid: chatJid,
                    groupName: groupName
                },
                context: context,
                originalCommand: originalCommand
            });
        } catch (error) {
            this.logger.warn('Failed to log response:', error);
        }
    }

    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    getStatus() {
        return {
            id: this.config.id,
            name: this.config.name,
            status: this.status,
            qrDataUrl: this.qrDataUrl,
            reconnectAttempts: this.reconnectAttempts,
            allowedNumbers: this.config.allowedNumbers,
            sessionErrorCount: this.sessionErrorCount,
            messageQueueLength: this.messageQueue.length,
            connectionChecks: this.connectionChecks.size,
            lastHealthCheck: this.lastHealthCheck
        };
    }

   async stop() {
    this.logger.info(`Stopping ${this.config.name}...`);

    // kalau memang botController dipakai untuk sesuatu,
    // pastikan cek dulu method yang tersedia
    if (this.botController && typeof this.botController.stop === 'function') {
        await this.botController.stop();
    }

    this.connectionChecks.clear();
    this.messageQueue.length = 0;
    this.rateLimiter.clear();

    if (this.sock) {
        try {
            await this.sock.logout();
        } catch (error) {
            this.logger.warn('Error during logout:', error);
        }

        this.sock.end();
        this.sock = null;
    }

    this.status = BOT_STATUS.DISCONNECTED;
    this.logger.info(`${this.config.name} stopped`);
}

}

export default Bot;