import Logger from '../utils/logger.js';
import Message from '../models/message.js';

class MessageWorker {
    constructor(databaseService, botManager) {
        this.databaseService = databaseService;
        this.botManager = botManager;
        this.logger = new Logger('MessageWorker');
        this.isRunning = false;
        this.processInterval = 2000; 
        this.maxRetries = 3;
        this.intervalId = null;
    }

    async start() {
        if (this.isRunning) {
            this.logger.warn('Worker is already running');
            return;
        }

        this.isRunning = true;
        this.logger.info('Starting message worker...');
        
        this.intervalId = setInterval(() => {
            this.processMessages().catch(error => {
                this.logger.error('Error in message processing:', error);
            });
        }, this.processInterval);

        this.logger.info('Message worker started successfully');
    }

    stop() {
        if (!this.isRunning) {
            this.logger.warn('Worker is not running');
            return;
        }

        this.isRunning = false;
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
        }
        
        this.logger.info('Message worker stopped');
    }

    async processMessages() {
        try {
            await this.processIncomingMessages();
            
            await this.processOutgoingMessages();
            
        } catch (error) {
            this.logger.error('Error processing messages:', error);
        }
    }

    async processIncomingMessages() {
        try {
            const activeBots = this.botManager.getAllBots();
            
            for (const bot of activeBots) {
                if (!this.isConnected(bot.config.id)) {
                    continue;
                }

                const messageModel = new Message(this.databaseService, bot.config.id);
                
                const incomingMessages = await messageModel.getIncoming(10, 'unread');
                
                for (const message of incomingMessages) {
                    await this.processIncomingMessage(message, bot.config.id);
                }
            }
        } catch (error) {
            this.logger.error('Error processing incoming messages:', error);
        }
    }

    async processIncomingMessage(message, botId) {
        try {
            const messageModel = new Message(this.databaseService, botId);
            
            await messageModel.updateIncomingStatus(message.id, 'read');
            
            const autoResponse = this.generateAutoResponse(message);
            
            if (autoResponse) {
                const outgoingData = {
                    targetJid: message.sender_jid,
                    message: autoResponse,
                    status: 'pending',
                    replyTo: message.id,
                    botId: botId
                };
                
                await messageModel.createOutgoing(outgoingData);
                this.logger.info(`Auto-response queued for message ${message.id}`);
            }
            
            await messageModel.updateIncomingStatus(message.id, 'processed');
            
        } catch (error) {
            this.logger.error(`Error processing incoming message ${message.id}:`, error);
        }
    }

    async processOutgoingMessages() {
        try {
            const activeBots = this.botManager.getAllBots();
            
            for (const bot of activeBots) {
                if (!this.isConnected(bot.config.id)) {
                    this.logger.debug(`Bot ${bot.config.id} not connected, skipping outgoing messages`);
                    continue;
                }

                const messageModel = new Message(this.databaseService, bot.config.id);
                
                const outgoingMessages = await this.databaseService.query(`
                    SELECT * FROM outgoing_messages 
                    WHERE bot_id = ? 
                    AND (status = 'pending' OR (status = 'failed' AND retry_count < ?))
                    ORDER BY created_at ASC 
                    LIMIT 5
                `, [bot.config.id, this.maxRetries]);
                
                for (const message of outgoingMessages) {
                    await this.processOutgoingMessage(message, bot);
                }
            }
        } catch (error) {
            this.logger.error('Error processing outgoing messages:', error);
        }
    }

    isConnected(botId) {
        try {
            const bot = this.botManager.getBot(botId);
            if (!bot) {
                this.logger.debug(`Bot ${botId} not found in manager`);
                return false;
            }

            const hasSocket = bot.sock && bot.sock.user;
            const statusConnected = bot.status === 'connected';
            const socketWorks = bot.sock && typeof bot.sock.sendMessage === 'function';
            
            const isConnected = hasSocket && statusConnected && socketWorks;
            
            this.logger.debug(`Bot ${botId} connection check:`, {
                hasSocket,
                statusConnected,
                socketWorks,
                actualStatus: bot.status,
                isConnected
            });
            
            return isConnected;
            
        } catch (error) {
            this.logger.error(`Error checking connection for bot ${botId}:`, error);
            return false;
        }
    }

    async processOutgoingMessage(message, bot) {
        const messageModel = new Message(this.databaseService, bot.config.id);
        
        try {
            if (!this.isConnected(bot.config.id)) {
                this.logger.warn(`Bot ${bot.config.id} not connected during send, skipping message ${message.id}`);
                
                await this.databaseService.query(`
                    UPDATE outgoing_messages 
                    SET retry_count = retry_count + 1, updated_at = NOW() 
                    WHERE id = ?
                `, [message.id]);
                
                if (message.retry_count + 1 >= this.maxRetries) {
                    await messageModel.updateOutgoingStatus(message.id, 'failed');
                    this.logger.warn(`Message ${message.id} failed - bot not connected (max retries reached)`);
                }
                
                return;
            }
            
            await this.databaseService.query(`
                UPDATE outgoing_messages 
                SET retry_count = retry_count + 1, updated_at = NOW() 
                WHERE id = ?
            `, [message.id]);
            
            let success = false;
            try {
                const formattedJid = message.target_jid.includes('@') 
                    ? message.target_jid 
                    : `${message.target_jid}@s.whatsapp.net`;
                
                this.logger.info(`Sending message ${message.id} to ${formattedJid}`);
                
                const result = await bot.sock.sendMessage(formattedJid, { 
                    text: message.message 
                });
                
                success = !!result;
                
            } catch (sendError) {
                this.logger.error(`Send message failed for message ${message.id}:`, {
                    error: sendError.message,
                    botId: bot.config.id,
                    targetJid: message.target_jid
                });
                success = false;
            }
            
            if (success) {
                await messageModel.updateOutgoingStatus(message.id, 'sent');
                
                await this.databaseService.query(`
                    UPDATE outgoing_messages 
                    SET delivery_status = 'sent', updated_at = NOW() 
                    WHERE id = ?
                `, [message.id]);
                
                this.logger.info(`Message ${message.id} sent successfully`);
            } else {
                if (message.retry_count + 1 >= this.maxRetries) {
                    await messageModel.updateOutgoingStatus(message.id, 'failed');
                    this.logger.warn(`Message ${message.id} failed after ${this.maxRetries} retries`);
                } else {
                    this.logger.warn(`Message ${message.id} failed, will retry (${message.retry_count + 1}/${this.maxRetries})`);
                }
            }
            
        } catch (error) {
            this.logger.error(`Error processing outgoing message ${message.id}:`, error);
            
            if (message.retry_count >= this.maxRetries - 1) {
                await messageModel.updateOutgoingStatus(message.id, 'failed');
            }
        }
    }

    generateAutoResponse(message) {
        const responses = [
            "Pesan Anda telah kami terima dan sedang kami proses. Terima kasih telah menghubungi kami.",
            "Halo! Pesan Anda telah masuk ke sistem kami. Tim kami akan segera menangani permintaan Anda.",
            "Terima kasih telah menghubungi kami. Pesan Anda telah diterima dan akan diproses secepatnya."
        ];
        
        return responses[Math.floor(Math.random() * responses.length)];
    }

    async processNow() {
        this.logger.info('Manual message processing triggered');
        await this.processMessages();
    }

    getStatus() {
        return {
            isRunning: this.isRunning,
            processInterval: this.processInterval,
            maxRetries: this.maxRetries,
            lastProcessed: new Date().toISOString()
        };
    }

    async updateDeliveryStatus(messageId, botId, status) {
        try {
            await this.databaseService.query(`
                UPDATE outgoing_messages 
                SET delivery_status = ?, updated_at = NOW() 
                WHERE id = ? AND bot_id = ?
            `, [status, messageId, botId]);
            
            this.logger.info(`Delivery status updated: Message ${messageId} -> ${status}`);
        } catch (error) {
            this.logger.error(`Error updating delivery status for message ${messageId}:`, error);
        }
    }
}

export default MessageWorker;