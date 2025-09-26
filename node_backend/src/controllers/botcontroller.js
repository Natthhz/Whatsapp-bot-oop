// enhanced-botcontroller.js
import { BOT_STATUS } from '../bots/config/constants.js';
import Logger from '../utils/logger.js';
import Targets from '../models/target.js';
import Message from '../models/message.js';

class BotController {
    constructor(bot) {
        this.bot = bot;
        this.logger = new Logger('EnhancedBotController');
        this.targetsModel = new Targets(bot.databaseService, bot.config.id);
        this.targetCache = new Map();
        this.cacheTimeout = 30000; 
    }

    async getTargetJid(senderJid) {
        const cached = this.targetCache.get(senderJid);
        if (cached && (Date.now() - cached.timestamp) < this.cacheTimeout) {
            this.logger.debug(`Using cached target for ${senderJid}: ${cached.targetJid}`);
            return cached.targetJid;
        }

        try {
            this.logger.debug(`Querying target for: { senderJid: '${senderJid}', botId: '${this.bot.config.id}' }`);
            const targetJid = await this.targetsModel.getTargetBySender(senderJid);

            if (targetJid) {
                this.targetCache.set(senderJid, {
                    targetJid,
                    timestamp: Date.now()
                });
                this.logger.debug(`Found target for ${senderJid}: ${targetJid}`);
            } else {
                this.logger.debug(`No target found for sender: ${senderJid}`);
            }

            return targetJid;
        } catch (error) {
            this.logger.error('Error getting target JID:', error);
            return null;
        }
    }

    async getGroupsText() {
        try {
            if (!this.bot.sock) {
                return "❌ Bot tidak terhubung.";
            }

            const groups = await this.bot.sock.groupFetchAllParticipating();
            const groupList = Object.values(groups)
                .map(group => `• ${group.subject} (${group.participants.length} anggota)`)
                .join('\n');

            return groupList || "Tidak ada grup yang ditemukan.";
        } catch (error) {
            this.logger.error('Error getting groups:', error);
            return "❌ Gagal mengambil daftar grup.";
        }
    }

    async getTargetJids(senderJid) {
        const cached = this.targetCache.get(senderJid);
        if (cached && (Date.now() - cached.timestamp) < this.cacheTimeout) {
            this.logger.debug(`Using cached targets for ${senderJid}: ${cached.targetJids.length} targets`);
            return cached.targetJids;
        }

        try {
            this.logger.debug(`Querying targets for: { senderJid: '${senderJid}', botId: '${this.bot.config.id}' }`);
            const targetJids = await this.targetsModel.getTargetsBySender(senderJid);

            if (targetJids && targetJids.length > 0) {
                this.targetCache.set(senderJid, {
                    targetJids,
                    timestamp: Date.now()
                });
                this.logger.debug(`Found ${targetJids.length} targets for ${senderJid}`);
            } else {
                this.logger.debug(`No targets found for sender: ${senderJid}`);
            }

            return targetJids || [];
        } catch (error) {
            this.logger.error('Error getting target JIDs:', error);
            return [];
        }
    }

    isConnectionReady() {
        return this.bot &&
            this.bot.sock &&
            this.bot.status === BOT_STATUS.CONNECTED &&
            this.bot.sock.user;
    }

    async sendMessageWithCircuitBreaker(targetJid, message, timeoutMs) {
        return new Promise((resolve) => {
            const timeoutId = setTimeout(() => {
                resolve({ success: false, error: 'Timeout' });
            }, timeoutMs);

            const cleanup = () => {
                clearTimeout(timeoutId);
            };

            if (!this.bot || !this.bot.sock) {
                cleanup();
                resolve({ success: false, error: 'Bot or socket not available' });
                return;
            }

            this.bot.sock.sendMessage(targetJid, { text: message })
                .then((result) => {
                    cleanup();
                    resolve({ success: true, result });
                })
                .catch((error) => {
                    cleanup();
                    resolve({ success: false, error: error.message });
                });
        });
    }
}

export default BotController;