import BaseModel from './basemodel.js';
import { MESSAGE_TYPES } from '../bots/config/constants.js';
import { pool, execute, getConnection, tableSchemas } from '../bots/config/database.js';

class Message extends BaseModel {
    constructor(databaseService, botId) {
        super(databaseService, botId);
        this.tableName = 'incoming_messages';
        this.outgoingTableName = 'outgoing_messages';
    }

    // Simpan pesan masuk
  async createIncoming(messageData) {
        const data = {
            sender_jid: messageData.senderJid,
            bot_id: messageData.botId || this.botId, // Simpan bot_id untuk filtering
            message: messageData.message,
            sender_name: messageData.senderName,
            sender_phone: messageData.senderPhone,
            status: 'unread'
        };

        const query = `
            INSERT INTO ${this.tableName} 
            (sender_jid, bot_id, message, sender_name, sender_phone, status)
            VALUES (?, ?, ?, ?, ?, ?)
        `;
        
        return await this.db.query(query, [
            data.sender_jid, 
            data.bot_id, 
            data.message, 
            data.sender_name, 
            data.sender_phone, 
            data.status
        ]);
    }

    async createOutgoing(messageData) {
        const query = `
            INSERT INTO ${this.outgoingTableName} 
            (target_jid, bot_id, message, status, reply_to)
            VALUES (?, ?, ?, ?, ?)
        `;
        
        return await this.db.query(query, [
            messageData.targetJid,
            messageData.botId || this.botId, // Simpan bot_id untuk filtering
            messageData.message,
            messageData.status || 'pending',
            messageData.replyTo || null
        ]);
    }

    // In message.js, replace the getIncoming method:
async getIncoming(limit = 100, status = null) {
    let query = `
        SELECT * FROM ${this.tableName}
        WHERE bot_id = ?
    `;
    let params = [this.botId];
    
    if (status) {
        query += ' AND status = ?';
        params.push(status);
    }
    
    query += ' ORDER BY created_at DESC LIMIT ?';
    params.push(limit);
    
    return await this.db.query(query, params);
}

// Update method getOutgoing di models/message.js

async getOutgoing(limit = 100, status = null) {
    let query = `
        SELECT 
            om.*,
            im.sender_name,
            im.sender_phone,
            CASE 
                WHEN om.reply_to IS NOT NULL THEN CONCAT(om.target_jid, ' (Reply)')
                ELSE om.target_jid 
            END as display_target
        FROM ${this.outgoingTableName} om
        LEFT JOIN ${this.tableName} im ON om.reply_to = im.id
        WHERE om.bot_id = ?
    `;
    let params = [this.botId];
    
    if (status) {
        query += ' AND om.status = ?';
        params.push(status);
    }
    
    query += ' ORDER BY om.created_at DESC LIMIT ?';
    params.push(parseInt(limit));
    
    return await this.db.query(query, params);
}

// Tambahkan method untuk update WhatsApp message ID
async updateWhatsAppMessageId(messageId, whatsappMessageId) {
    const query = `
        UPDATE ${this.outgoingTableName} 
        SET whatsapp_message_id = ?, updated_at = NOW() 
        WHERE id = ? AND bot_id = ?
    `;
    return await this.db.query(query, [whatsappMessageId, messageId, this.botId]);
}

// Tambahkan method untuk update delivery status
async updateDeliveryStatus(messageId, deliveryStatus) {
    const query = `
        UPDATE ${this.outgoingTableName} 
        SET delivery_status = ?, updated_at = NOW() 
        WHERE id = ? AND bot_id = ?
    `;
    return await this.db.query(query, [deliveryStatus, messageId, this.botId]);
}

// Tambahkan method untuk update error message
async updateErrorMessage(messageId, errorMessage) {
    const query = `
        UPDATE ${this.outgoingTableName} 
        SET error_message = ?, updated_at = NOW() 
        WHERE id = ? AND bot_id = ?
    `;
    return await this.db.query(query, [errorMessage, messageId, this.botId]);
}

    async updateIncomingStatus(messageId, status) {
        const query = `
            UPDATE ${this.tableName} 
            SET status = ? 
            WHERE id = ? AND bot_id = ?  
        `;
        return await this.db.query(query, [status, messageId, this.botId]);
    }

    async updateOutgoingStatus(messageId, status) {
        const query = `
            UPDATE ${this.outgoingTableName} 
            SET status = ? 
            WHERE id = ? AND bot_id = ?  
        `;
        return await this.db.query(query, [status, messageId, this.botId]);
    }

    async deleteIncoming(messageId) {
        // Pastikan hanya menghapus data bot ini
        return await this.delete(this.tableName, { id: messageId, bot_id: this.botId });
    }

    async deleteOutgoing(messageId) {
        // Pastikan hanya menghapus data bot ini
        return await this.delete(this.outgoingTableName, { id: messageId, bot_id: this.botId });
    }

    async getStats() {
        try {
            const incomingCount = await this.db.query(
                `SELECT COUNT(*) as count FROM ${this.tableName} WHERE bot_id = ?`,
                [this.botId]
            );
            
            const outgoingCount = await this.db.query(
                `SELECT COUNT(*) as count FROM ${this.outgoingTableName} WHERE bot_id = ?`,
                [this.botId]
            );
            
            const unreadCount = await this.db.query(
                `SELECT COUNT(*) as count FROM ${this.tableName} WHERE bot_id = ? AND status = 'unread'`,
                [this.botId]
            );
            
            return {
                total_incoming: incomingCount[0].count,
                total_outgoing: outgoingCount[0].count,
                unread_messages: unreadCount[0].count
            };
        } catch (error) {
            console.error('Error getting message stats:', error);
            throw error;
        }
    }


    async create(messageData) {
         return await this.createIncoming({
            senderJid: messageData.sender_jid || messageData.sender?.jid,
            message: messageData.message,
            senderName: messageData.sender_name || messageData.sender?.name,
            senderPhone: messageData.sender_phone || messageData.sender?.phone
        });
    }

    async getRecent(limit = 100) {
        return await this.getIncoming(limit);
    }

    async getByType(type, limit = 50) {
         if (type === 'incoming') {
            return await this.getIncoming(limit);
        }
        return [];
    }

    async deleteById(messageId) {
        return await this.deleteIncoming(messageId);
    }

    async delete(messageId) {
        return await this.deleteIncoming(messageId);
    }

    async deleteByCondition(condition) {
         if (condition.type && condition.value) {
            return await this.delete(this.tableName, { [condition.type]: condition.value });
        }
        return 0;
    }

    async clearAll() {
        return await this.truncate(this.tableName);
    }
}

export default Message;