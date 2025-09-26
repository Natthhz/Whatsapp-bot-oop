import { getAllBotConfigs } from './config/bot-config.js';
import Bot from './bot.js';
import Logger from '../utils/logger.js';
import MessageWorker from '../worker/messageworker.js';

class BotManager {
    constructor(databaseService) {
        this.databaseService = databaseService;
        this.bots = new Map();
        this.logger = new Logger('BotManager');
        this.messageWorker = null;
    }

    async startAllBots() {
        const configs = getAllBotConfigs();
        
        for (const config of configs) {
            try {
                await this.startBot(config.id);
                await new Promise(resolve => setTimeout(resolve, 2000));
            } catch (error) {
                this.logger.error(`Failed to start ${config.name}:`, error);
            }
        }

        await this.startMessageWorker();
    }

    async startMessageWorker() {
        if (this.messageWorker) {
            this.logger.warn('Message worker is already running');
            return;
        }

        this.messageWorker = new MessageWorker(this.databaseService, this);
        await this.messageWorker.start();
        this.logger.info('Message worker started');
    }

    async stopMessageWorker() {
        if (this.messageWorker) {
            this.messageWorker.stop();
            this.messageWorker = null;
            this.logger.info('Message worker stopped');
        }
    }

    get workerStatus() {
        return this.messageWorker ? this.messageWorker.getStatus() : null;
    }

    async startBot(botId) {
        if (this.bots.has(botId)) {
            this.logger.warn(`Bot ${botId} is already running`);
            return this.bots.get(botId);
        }

        const configs = getAllBotConfigs();
        const config = configs.find(c => c.id === botId);
        
        if (!config) {
            throw new Error(`Bot configuration not found for ID: ${botId}`);
        }

        this.logger.info(`Starting bot: ${config.name}`);
        
        const bot = new Bot(config, this.databaseService);
        await bot.start();
        
        this.bots.set(botId, bot);
        this.logger.info(`Bot ${config.name} started successfully`);
        
        return bot;
    }

    async stopBot(botId) {
        const bot = this.bots.get(botId);
        if (!bot) {
            this.logger.warn(`Bot ${botId} not found or not running`);
            return;
        }

        await bot.stop();
        this.bots.delete(botId);
        this.logger.info(`Bot ${botId} stopped`);
    }

    async stopAllBots() {
        const stopPromises = Array.from(this.bots.keys()).map(botId => this.stopBot(botId));
        await Promise.all(stopPromises);
        
        await this.stopMessageWorker();
        
        this.logger.info('All bots stopped');
    }

    getBot(botId) {
        try {
            const bot = this.bots.get(botId);
            return bot || null;
        } catch (error) {
            this.logger.error(`Error getting bot ${botId}:`, error);
            return null;
        }
    }

    getAllBots() {
        return Array.from(this.bots.values());
    }

    getBotStatus(botId) {
        const bot = this.bots.get(botId);
        return bot ? bot.getStatus() : null;
    }

    isConnected(botId) {
        try {
            const bot = this.getBot(botId);
            if (!bot) {
                this.logger.debug(`Bot ${botId} not found in manager`);
                return false;
            }

            const hasSocket = bot.sock && typeof bot.sock.sendMessage === 'function';
            const hasUser = bot.sock && bot.sock.user;
            const statusConnected = bot.status === 'connected';
            
            const isConnected = hasSocket && hasUser && statusConnected;
            
            this.logger.debug(`Bot ${botId} connection check:`, {
                hasSocket,
                hasUser,
                statusConnected,
                actualStatus: bot.status,
                isConnected
            });
            
            return isConnected;
            
        } catch (error) {
            this.logger.error(`Error checking connection for bot ${botId}:`, error);
            return false;
        }
    }

    getConnectedBots() {
        const connectedBots = [];
        
        for (const [botId, bot] of this.bots) {
            if (this.isConnected(botId)) {
                connectedBots.push(bot);
            }
        }
        
        return connectedBots;
    }

    getAllBotStatuses() {
        const configs = getAllBotConfigs();
        return configs.map(config => {
            const bot = this.bots.get(config.id);
            const isConnected = this.isConnected(config.id);
            
            if (bot) {
                const status = bot.getStatus();
                return {
                    ...config,
                    status: {
                        ...status,
                        isConnected,
                        hasSocket: !!(bot.sock),
                        hasUser: !!(bot.sock && bot.sock.user)
                    }
                };
            } else {
                return {
                    ...config,
                    status: { 
                        id: config.id, 
                        name: config.name, 
                        status: 'disconnected',
                        qrDataUrl: null,
                        reconnectAttempts: 0,
                        allowedNumbers: config.allowedNumbers,
                        isConnected: false,
                        hasSocket: false,
                        hasUser: false
                    }
                };
            }
        });
    }

    getAllBotsDetailedStatus() {
        const statuses = [];
        
        for (const [botId, bot] of this.bots) {
            try {
                const isConnected = this.isConnected(botId);
                const botStatus = bot.getStatus();
                
                statuses.push({
                    id: botId,
                    name: bot.config.name,
                    status: bot.status,
                    qrDataUrl: bot.qrDataUrl,
                    reconnectAttempts: bot.reconnectAttempts,
                    port: bot.config.port,
                    allowedNumbers: bot.config.allowedNumbers,
                    isConnected,
                    hasSocket: !!(bot.sock),
                    hasUser: !!(bot.sock && bot.sock.user),
                    messageQueueLength: bot.messageQueue ? bot.messageQueue.length : 0,
                    sessionErrorCount: bot.sessionErrorCount || 0,
                    lastHealthCheck: bot.lastHealthCheck,
                    connectionChecks: bot.connectionChecks ? bot.connectionChecks.size : 0
                });
            } catch (error) {
                this.logger.error(`Error getting status for bot ${botId}:`, error);
                statuses.push({
                    id: botId,
                    name: bot.config ? bot.config.name : 'Unknown',
                    status: 'error',
                    error: error.message,
                    isConnected: false
                });
            }
        }
        
        return statuses;
    }

    getQrDataUrl(botId) {
        const bot = this.bots.get(botId);
        return bot ? bot.getStatus().qrDataUrl : null;
    }

    getConnectedBotsCount() {
        return Array.from(this.bots.values()).filter(bot => 
            this.isConnected(bot.config.id)
        ).length;
    }

    async restartBot(botId) {
        this.logger.info(`Restarting bot: ${botId}`);
        await this.stopBot(botId);
        await new Promise(resolve => setTimeout(resolve, 1000)); 
        return await this.startBot(botId);
    }

    async testBotConnection(botId) {
        const bot = this.getBot(botId);
        
        if (!bot) {
            return { 
                connected: false, 
                error: 'Bot not found',
                details: null
            };
        }
        
        const status = {
            connected: this.isConnected(botId),
            hasSocket: !!(bot.sock),
            hasUser: !!(bot.sock && bot.sock.user),
            status: bot.status,
            canSendMessage: typeof bot.sock?.sendMessage === 'function',
            lastHealthCheck: bot.lastHealthCheck,
            socketReadyState: bot.sock ? 'available' : 'unavailable'
        };
        
        if (status.connected && bot.sock) {
            try {
                await bot.sock.sendPresenceUpdate('available');
                status.pingTest = 'success';
                status.lastPing = new Date().toISOString();
            } catch (error) {
                status.pingTest = 'failed';
                status.pingError = error.message;
                status.connected = false; 
            }
        }
        
        return status;
    }

    async refreshAllConnections() {
        const results = [];
        
        for (const [botId, bot] of this.bots) {
            try {
                const connectionTest = await this.testBotConnection(botId);
                results.push({
                    botId,
                    name: bot.config.name,
                    ...connectionTest
                });
            } catch (error) {
                results.push({
                    botId,
                    name: bot.config.name,
                    connected: false,
                    error: error.message
                });
            }
        }
        
        return results;
    }

    async cleanupDeadConnections() {
        let cleanedUp = 0;
        
        for (const [botId, bot] of this.bots) {
            const isConnected = this.isConnected(botId);
            
            if (!isConnected && bot.status === 'connected') {
                this.logger.warn(`Bot ${botId} appears disconnected but status is connected, fixing...`);
                bot.status = 'disconnected';
                cleanedUp++;
            }
            
            if (bot.connectionChecks && bot.connectionChecks.size > 0) {
                const now = Date.now();
                for (const [checkId, timestamp] of bot.connectionChecks.entries()) {
                    if (now - timestamp > 60000) { // 1 minute old
                        bot.connectionChecks.delete(checkId);
                    }
                }
            }
        }
        
        this.logger.info(`Cleaned up ${cleanedUp} dead connections`);
        return cleanedUp;
    }

    getStats() {
        const totalBots = this.bots.size;
        const connectedBots = this.getConnectedBotsCount();
        const messageWorkerRunning = !!(this.messageWorker && this.messageWorker.isRunning);
        
        let totalMessageQueue = 0;
        let totalConnectionChecks = 0;
        
        for (const [botId, bot] of this.bots) {
            totalMessageQueue += bot.messageQueue ? bot.messageQueue.length : 0;
            totalConnectionChecks += bot.connectionChecks ? bot.connectionChecks.size : 0;
        }
        
        return {
            totalBots,
            connectedBots,
            disconnectedBots: totalBots - connectedBots,
            messageWorkerRunning,
            totalMessageQueue,
            totalConnectionChecks,
            uptime: process.uptime(),
            timestamp: new Date().toISOString()
        };
    }
}

export default BotManager;