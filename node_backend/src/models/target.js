import BaseModel from './basemodel.js';

class Targets extends BaseModel {
    constructor(databaseService, botId) {
        super(databaseService, botId);
        this.tableName = 'targets';
    }

    async getTargetsBySender(senderJid) {
        try {
            if (!senderJid) {
                throw new Error('senderJid is required');
            }
            
            if (!this.botId) {
                console.error('ERROR: botId is not set in Targets model');
                return [];
            }
            
            console.log('Querying targets for:', { senderJid, botId: this.botId });
            
            const query = `SELECT target_jid FROM ${this.tableName} WHERE sender_jid = ? AND bot_id = ? AND is_active = TRUE`;
            const results = await this.db.query(query, [senderJid, this.botId]);
            
            return results.map(row => row.target_jid);
        } catch (error) {
            console.error('Error getting targets by sender:', error);
            throw error;
        }
    }
    
    async addTarget(sender, target) {
        try {
            const existingQuery = `SELECT id FROM ${this.tableName} WHERE sender_jid = ? AND target_jid = ? AND bot_id = ?`;
            const existing = await this.db.query(existingQuery, [sender, target, this.botId]);
            
            if (existing.length > 0) {
                const updateQuery = `UPDATE ${this.tableName} SET is_active = TRUE, updated_at = NOW() WHERE id = ?`;
                await this.db.query(updateQuery, [existing[0].id]);
                return { success: true, message: 'Target reactivated' };
            } else {
                const insertQuery = `INSERT INTO ${this.tableName} (sender_jid, target_jid, bot_id, is_active, created_at) VALUES (?, ?, ?, TRUE, NOW())`;
                await this.db.query(insertQuery, [sender, target, this.botId]);
                return { success: true, message: 'Target added' };
            }
        } catch (error) {
            console.error('Error adding target:', error);
            throw error;
        }
    }

    async removeTarget(sender, target) {
        try {
            const query = `UPDATE ${this.tableName} SET is_active = FALSE, updated_at = NOW() WHERE sender_jid = ? AND target_jid = ? AND bot_id = ?`;
            const result = await this.db.query(query, [sender, target, this.botId]);
            
            return result.affectedRows > 0;
        } catch (error) {
            console.error('Error removing target:', error);
            throw error;
        }
    }

    async removeAllTargets(sender) {
        try {
            const query = `UPDATE ${this.tableName} SET is_active = FALSE, updated_at = NOW() WHERE sender_jid = ? AND bot_id = ?`;
            const result = await this.db.query(query, [sender, this.botId]);
            
            return result.affectedRows;
        } catch (error) {
            console.error('Error removing all targets:', error);
            throw error;
        }
    }

    async getAllTargets() {
        try {
            const query = `SELECT sender_jid, target_jid FROM ${this.tableName} WHERE is_active = TRUE AND bot_id = ?`;
            return await this.db.query(query, [this.botId]);
        } catch (error) {
            console.error('Error getting all targets:', error);
            return [];
        }
    }

    async getTargetStats() {
        try {
            const query = `
                SELECT 
                    sender_jid, 
                    COUNT(*) as target_count 
                FROM ${this.tableName} 
                WHERE is_active = TRUE AND bot_id = ?
                GROUP BY sender_jid
            `;
            return await this.db.query(query, [this.botId]);
        } catch (error) {
            console.error('Error getting target stats:', error);
            return [];
        }
    }

}

export default Targets;