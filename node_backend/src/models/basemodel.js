import { getBotConfig } from '../bots/config/bot-config.js';
import Logger from '../utils/logger.js';

class BaseModel {
    constructor(databaseService, botId = 'bot1') {
        this.db = databaseService;
        this.botConfig = getBotConfig(botId);
        this.logger = new Logger(this.constructor.name);
        this.botId = botId;
        
        if (!botId) {
            throw new Error('botId is required for BaseModel');
        }
        
        this.botConfig = getBotConfig(botId);
        
        if (!this.botConfig) {
            throw new Error(`Bot configuration not found for botId: ${botId}`);
        }
    }

    getTableName(tableName) {
        return tableName; 
    }

    async create(tableName, data) {
        try {
            const columns = Object.keys(data).join(', ');
            const placeholders = Object.keys(data).map(() => '?').join(', ');
            const values = Object.values(data);

            const query = `INSERT INTO ${this.getTableName(tableName)} (${columns}) VALUES (${placeholders})`;
            const result = await this.db.query(query, values);
            
            this.logger.info(`Created record in ${tableName}`, { id: result.insertId });
            return result;
        } catch (error) {
            this.logger.error(`Error creating record in ${tableName}:`, error);
            throw error;
        }
    }

    async createOrUpdate(tableName, data, duplicateKeyUpdate = {}) {
        try {
            const columns = Object.keys(data).join(', ');
            const placeholders = Object.keys(data).map(() => '?').join(', ');
            const values = Object.values(data);

            let query = `INSERT INTO ${this.getTableName(tableName)} (${columns}) VALUES (${placeholders})`;
            
            if (Object.keys(duplicateKeyUpdate).length > 0) {
                const updateClauses = Object.keys(duplicateKeyUpdate)
                    .map(key => `${key} = VALUES(${key})`)
                    .join(', ');
                query += ` ON DUPLICATE KEY UPDATE ${updateClauses}`;
            }

            const result = await this.db.query(query, values);
            return result;
        } catch (error) {
            this.logger.error(`Error creating/updating record in ${tableName}:`, error);
            throw error;
        }
    }

    async findById(tableName, id, idColumn = 'id') {
        try {
            const query = `SELECT * FROM ${this.getTableName(tableName)} WHERE ${idColumn} = ?`;
            const rows = await this.db.query(query, [id]);
            return rows[0] || null;
        } catch (error) {
            this.logger.error(`Error finding record by ID in ${tableName}:`, error);
            throw error;
        }
    }

    async findMany(tableName, conditions = {}, limit = null, orderBy = 'created_at DESC') {
        try {
            let query = `SELECT * FROM ${this.getTableName(tableName)}`;
            const params = [];

            if (Object.keys(conditions).length > 0) {
                const whereClause = Object.keys(conditions)
                    .map(key => `${key} = ?`)
                    .join(' AND ');
                query += ` WHERE ${whereClause}`;
                params.push(...Object.values(conditions));
            }

            if (orderBy) {
                query += ` ORDER BY ${orderBy}`;
            }

            if (limit) {
                query += ` LIMIT ?`;
                params.push(limit);
            }

            const rows = await this.db.query(query, params);
            return rows;
        } catch (error) {
            this.logger.error(`Error finding records in ${tableName}:`, error);
            throw error;
        }
    }

    async update(tableName, conditions, data) {
        try {
            const setClauses = Object.keys(data)
                .map(key => `${key} = ?`)
                .join(', ');
            const whereClause = Object.keys(conditions)
                .map(key => `${key} = ?`)
                .join(' AND ');

            const query = `UPDATE ${this.getTableName(tableName)} SET ${setClauses} WHERE ${whereClause}`;
            const params = [...Object.values(data), ...Object.values(conditions)];

            const result = await this.db.query(query, params);
            return result;
        } catch (error) {
            this.logger.error(`Error updating record in ${tableName}:`, error);
            throw error;
        }
    }

    async delete(tableName, conditions) {
        try {
            const whereClause = Object.keys(conditions)
                .map(key => `${key} = ?`)
                .join(' AND ');

            const query = `DELETE FROM ${this.getTableName(tableName)} WHERE ${whereClause}`;
            const params = Object.values(conditions);

            const result = await this.db.query(query, params);
            this.logger.info(`Deleted from ${tableName}`, { affectedRows: result.affectedRows });
            return result;
        } catch (error) {
            this.logger.error(`Error deleting record from ${tableName}:`, error);
            throw error;
        }
    }

    async count(tableName, conditions = {}) {
        try {
            let query = `SELECT COUNT(*) as count FROM ${this.getTableName(tableName)}`;
            const params = [];

            if (Object.keys(conditions).length > 0) {
                const whereClause = Object.keys(conditions)
                    .map(key => `${key} = ?`)
                    .join(' AND ');
                query += ` WHERE ${whereClause}`;
                params.push(...Object.values(conditions));
            }

            const rows = await this.db.query(query, params);
            return rows[0].count;
        } catch (error) {
            this.logger.error(`Error counting records in ${tableName}:`, error);
            throw error;
        }
    }

    async truncate(tableName) {
        try {
            await this.db.query(`DELETE FROM ${this.getTableName(tableName)}`);
            await this.db.query(`ALTER TABLE ${this.getTableName(tableName)} AUTO_INCREMENT = 1`);
            this.logger.info(`Truncated table ${tableName}`);
        } catch (error) {
            this.logger.error(`Error truncating table ${tableName}:`, error);
            throw error;
        }
    }
}

export default BaseModel;