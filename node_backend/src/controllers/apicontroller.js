import { getBotConfig } from '../bots/config/bot-config.js';
import { HTTP_STATUS, DEFAULT_LIMITS } from '../bots/config/constants.js';
import Message from '../models/message.js';
import Logger from '../utils/logger.js';

class ApiController {
    constructor(databaseService, botManager) {
        this.databaseService = databaseService;
        this.botManager = botManager;
        this.logger = new Logger('ApiController');
    }

    getHealth(req, res) {
        try {
            res.json({ 
                status: 'OK', 
                timestamp: new Date().toISOString(),
                uptime: process.uptime(),
                memory: process.memoryUsage()
            });
        } catch (error) {
            this.logger.error('Error in health check:', error);
            res.status(HTTP_STATUS.INTERNAL_SERVER_ERROR).json({ error: error.message });
        }
    }

    async getAllBots(req, res) {
        try {
            const botsInfo = this.botManager.getAllBotStatuses();
            res.json(botsInfo);
        } catch (error) {
            this.logger.error('Error getting all bots:', error);
            res.status(HTTP_STATUS.INTERNAL_SERVER_ERROR).json({ error: 'Internal server error' });
        }
    }

    async getBotInfo(req, res) {
        try {
            const { botId } = req.params;
            const botStatus = this.botManager.getBotStatus(botId);
            
            if (!botStatus) {
                return res.status(HTTP_STATUS.NOT_FOUND).json({ error: 'Bot not found' });
            }

            res.json(botStatus);
        } catch (error) {
            this.logger.error('Error getting bot info:', error);
            res.status(HTTP_STATUS.INTERNAL_SERVER_ERROR).json({ error: 'Internal server error' });
        }
    }

    async restartBot(req, res) {
        try {
            const { botId } = req.params;
            await this.botManager.restartBot(botId);
            
            res.json({ 
                success: true, 
                message: `Bot ${botId} restarted successfully`
            });
        } catch (error) {
            this.logger.error('Error restarting bot:', error);
            res.status(HTTP_STATUS.INTERNAL_SERVER_ERROR).json({ 
                success: false, 
                error: error.message 
            });
        }
    }

    async startBot(req, res) {
        try {
            const { botId } = req.params;
            const bot = await this.botManager.startBot(botId);
            
            res.json({ 
                success: true, 
                message: `Bot ${botId} started successfully`,
                status: bot.getStatus()
            });
        } catch (error) {
            this.logger.error('Error starting bot:', error);
            res.status(HTTP_STATUS.INTERNAL_SERVER_ERROR).json({ 
                success: false, 
                error: error.message 
            });
        }
    }

    async stopBot(req, res) {
        try {
            const { botId } = req.params;
            await this.botManager.stopBot(botId);
            
            res.json({ 
                success: true, 
                message: `Bot ${botId} stopped successfully`
            });
        } catch (error) {
            this.logger.error('Error stopping bot:', error);
            res.status(HTTP_STATUS.INTERNAL_SERVER_ERROR).json({ 
                success: false, 
                error: error.message 
            });
        }
    }

    async getIncomingMessages(req, res) {
        try {
            const { botId } = req.params;
            const limit = parseInt(req.query.limit) || DEFAULT_LIMITS.MESSAGES;
            const { status } = req.query;
            
            const messageModel = new Message(this.databaseService, botId);
            const messages = await messageModel.getIncoming(limit, status);
            
            res.json({
                success: true,
                data: messages,
                lastUpdated: new Date().toISOString(),
                bot: botId
            });
        } catch (error) {
            this.logger.error('Error getting incoming messages:', error);
            res.status(HTTP_STATUS.INTERNAL_SERVER_ERROR).json({ 
                success: false,
                error: 'Internal server error' 
            });
        }
    }

    async getOutgoingMessages(req, res) {
        try {
            const { botId } = req.params;
            const limit = parseInt(req.query.limit) || DEFAULT_LIMITS.MESSAGES;
            const { status } = req.query;
            
            const messageModel = new Message(this.databaseService, botId);
            const messages = await messageModel.getOutgoing(limit, status);
            
            res.json({
                success: true,
                data: messages,
                lastUpdated: new Date().toISOString(),
                bot: botId
            });
        } catch (error) {
            this.logger.error('Error getting outgoing messages:', error);
            res.status(HTTP_STATUS.INTERNAL_SERVER_ERROR).json({ 
                success: false,
                error: 'Internal server error' 
            });
        }
    }

    async sendMessage(req, res) {
        try {
            const { botId } = req.params;
            const { targetJid, message, replyTo } = req.body;
            
            if (!targetJid || !message) {
                return res.status(HTTP_STATUS.BAD_REQUEST).json({ 
                    success: false, 
                    error: 'targetJid and message are required' 
                });
            }
            
            const bot = this.botManager.getBot(botId);
            if (!bot) {
                return res.status(HTTP_STATUS.NOT_FOUND).json({ 
                    success: false, 
                    error: 'Bot not found' 
                });
            }
            
            const messageModel = new Message(this.databaseService, botId);
            const result = await messageModel.createOutgoing({
                targetJid,
                message,
                status: 'pending',
                replyTo
            });
            
            try {
                await bot.sendMessage(targetJid, message);
                
                await messageModel.updateOutgoingStatus(result.insertId, 'sent');
                
                res.json({ 
                    success: true, 
                    message: 'Message sent successfully',
                    messageId: result.insertId
                });
            } catch (sendError) {
                await messageModel.updateOutgoingStatus(result.insertId, 'failed');
                
                res.status(HTTP_STATUS.INTERNAL_SERVER_ERROR).json({ 
                    success: false, 
                    error: 'Failed to send message: ' + sendError.message,
                    messageId: result.insertId
                });
            }
        } catch (error) {
            this.logger.error('Error sending message:', error);
            res.status(HTTP_STATUS.INTERNAL_SERVER_ERROR).json({ 
                success: false, 
                error: error.message 
            });
        }
    }

    async updateMessageStatus(req, res) {
        try {
            const { botId, messageId } = req.params;
            const { type, status } = req.body;
            
            if (!['incoming', 'outgoing'].includes(type)) {
                return res.status(HTTP_STATUS.BAD_REQUEST).json({ 
                    success: false, 
                    error: 'Type must be "incoming" or "outgoing"' 
                });
            }
            
            const messageModel = new Message(this.databaseService, botId);
            
            if (type === 'incoming') {
                if (!['unread', 'read', 'replied'].includes(status)) {
                    return res.status(HTTP_STATUS.BAD_REQUEST).json({ 
                        success: false, 
                        error: 'Status must be "unread", "read", or "replied"' 
                    });
                }
                await messageModel.updateIncomingStatus(messageId, status);
            } else {
                if (!['pending', 'sent', 'failed'].includes(status)) {
                    return res.status(HTTP_STATUS.BAD_REQUEST).json({ 
                        success: false, 
                        error: 'Status must be "pending", "sent", or "failed"' 
                    });
                }
                await messageModel.updateOutgoingStatus(messageId, status);
            }
            
            res.json({ 
                success: true, 
                message: 'Message status updated successfully'
            });
        } catch (error) {
            this.logger.error('Error updating message status:', error);
            res.status(HTTP_STATUS.INTERNAL_SERVER_ERROR).json({ 
                success: false, 
                error: error.message 
            });
        }
    }

    async deleteMessage(req, res) {
        try {
            const { botId, messageId } = req.params;
            const messageModel = new Message(this.databaseService, botId);
            
            const success = await messageModel.delete(messageId);
            
            res.json({ 
                success, 
                message: success ? 'Message deleted successfully' : 'Message not found'
            });
        } catch (error) {
            this.logger.error('Error deleting message:', error);
            res.status(HTTP_STATUS.INTERNAL_SERVER_ERROR).json({ error: error.message });
        }
    }

    async deleteMessagesByCondition(req, res) {
        try {
            const { botId } = req.params;
            const { condition } = req.body;
            
            const messageModel = new Message(this.databaseService, botId);
            const deletedCount = await messageModel.deleteByCondition(condition);
            
            res.json({ 
                success: true, 
                message: `${deletedCount} messages deleted successfully`
            });
        } catch (error) {
            this.logger.error('Error deleting messages by condition:', error);
            res.status(HTTP_STATUS.INTERNAL_SERVER_ERROR).json({ error: error.message });
        }
    }

    async clearAllMessages(req, res) {
        try {
            const { botId } = req.params;
            const messageModel = new Message(this.databaseService, botId);
            await messageModel.clearAll();
            
            res.json({
                success: true,
                message: `All messages cleared from ${botId}`
            });
        } catch (error) {
            this.logger.error('Error clearing all messages:', error);
            res.status(HTTP_STATUS.INTERNAL_SERVER_ERROR).json({
                success: false,
                error: error.message
            });
        }
    }

    async getBotStats(req, res) {
        try {
            const { botId } = req.params;
            const botConfig = getBotConfig(botId);
            
            const messageModel = new Message(this.databaseService, botId);
            
            const messageStats = await messageModel.getStats();
            
            res.json({
                botId,
                botName: botConfig.name,
                allowedNumbers: botConfig.allowedNumbers,
                connected: this.isConnected,
                messageStats,
                lastUpdated: new Date().toISOString()
            });
        } catch (error) {
            this.logger.error('Error getting bot stats:', error);
            res.status(HTTP_STATUS.INTERNAL_SERVER_ERROR).json({ error: 'Internal server error' });
        }
    }

    async getQRCode(req, res) {
        try {
            const { botId } = req.params;
            const qrDataUrl = this.botManager.getQrDataUrl(botId);
            
            if (!qrDataUrl) {
                return res.status(HTTP_STATUS.NOT_FOUND).json({ error: 'QR code not available' });
            }
            
            res.json({ qrDataUrl });
        } catch (error) {
            this.logger.error('Error getting QR code:', error);
            res.status(HTTP_STATUS.INTERNAL_SERVER_ERROR).json({ error: error.message });
        }
    }

async receiveMessage(req, res) {
    try {
        const { botId } = req.params;
        const { senderJid, message, senderName, senderPhone } = req.body;

        if (!senderJid || !message) {
            return res.status(400).json({
                success: false,
                error: 'senderJid and message are required'
            });
        }

        const messageModel = new Message(this.databaseService, botId);
        
        const result = await messageModel.createIncoming({
            senderJid,
            message,
            senderName: senderName || 'Unknown',
            senderPhone: senderPhone || senderJid.split('@')[0],
            botId
        });

        this.logger.info(`Message received via API for bot ${botId}:`, { senderJid, message });

        res.json({
            success: true,
            data: {
                messageId: result.insertId,
                status: 'received',
                message: 'Message added to processing queue'
            }
        });

    } catch (error) {
        this.logger.error('Error receiving message:', error);
        res.status(500).json({
            success: false,
            error: error.message
        });
    }
}

async retryMessage(req, res) {
    try {
        const { botId, messageId } = req.params;
        
        await this.databaseService.query(
            'UPDATE outgoing_messages SET status = ?, retry_count = 0, updated_at = NOW() WHERE id = ? AND bot_id = ?',
            ['pending', messageId, botId]
        );

        res.json({
            success: true,
            message: 'Message queued for retry'
        });

    } catch (error) {
        this.logger.error('Error retrying message:', error);
        res.status(500).json({
            success: false,
            error: error.message
        });
    }
}

async getWorkerStatus(req, res) {
    try {
        let workerStatus;
        if (typeof this.botManager.getWorkerStatus === 'function') {
            workerStatus = this.botManager.getWorkerStatus();
        } else if (this.botManager.workerStatus) {
            workerStatus = this.botManager.workerStatus;
        } else {
            workerStatus = { 
                isRunning: false,
                processInterval: 2000,
                maxRetries: 3,
                lastProcessed: null
            };
        }
        
        res.json({
            success: true,
            data: workerStatus
        });
    } catch (error) {
        this.logger.error('Error getting worker status:', error);
        res.status(500).json({
            success: false,
            error: error.message
        });
    }
}

async processMessagesNow(req, res) {
    try {
        if (this.botManager.messageWorker) {
            await this.botManager.messageWorker.processNow();
            
            res.json({
                success: true,
                message: 'Message processing triggered'
            });
        } else {
            res.status(503).json({
                success: false,
                error: 'Message worker not available'
            });
        }

    } catch (error) {
        this.logger.error('Error processing messages:', error);
        res.status(500).json({
            success: false,
            error: error.message
        });
    }
}

async updateDeliveryStatus(req, res) {
    try {
        const { botId, messageId } = req.params;
        const { status } = req.body; 

        if (!['delivered', 'read'].includes(status)) {
            return res.status(400).json({
                success: false,
                error: 'Invalid status. Must be "delivered" or "read"'
            });
        }

        await this.databaseService.query(
            'UPDATE outgoing_messages SET delivery_status = ?, updated_at = NOW() WHERE id = ? AND bot_id = ?',
            [status, messageId, botId]
        );

        res.json({
            success: true,
            message: `Delivery status updated to ${status}`
        });

    } catch (error) {
        this.logger.error('Error updating delivery status:', error);
        res.status(500).json({
            success: false,
            error: error.message
        });
    }
}
}

export default ApiController;