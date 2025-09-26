import mysql from 'mysql2/promise';
import { databaseConfig, tableSchemas } from '../bots/config/database.js';
import { BOT_CONFIGS } from '../bots/config/bot-config.js';
import Logger from '../utils/logger.js';

class DatabaseService {
    constructor() {
        this.pool = null;
        this.logger = new Logger('DatabaseService');
    }

    async initialize() {
        try {
            this.pool = mysql.createPool(databaseConfig);
            const connection = await this.pool.getConnection();
            this.logger.info('MySQL connected successfully');
            connection.release();
            
            await this.createTables();
            return true;
        } catch (error) {
            this.logger.error('MySQL connection failed:', error.message);
            throw error;
        }
    }

async createTables() {
    try {
        for (const [tableName, schema] of Object.entries(tableSchemas)) {
            await this.pool.execute(schema);
        }

        this.logger.info('Database tables initialized successfully');
    } catch (error) {
        this.logger.error('Error creating tables:', error);
        throw error;
    }
}

   async query(sql, params = []) {
        if (!this.pool) {
            throw new Error('Database not initialized');
        }
        
        try {
             const [rows] = await this.pool.query(sql, params);
            return rows;
        } catch (error) {
            this.logger.error('Database query failed:', { sql, params, error: error.message });
            throw error;
        }
    }

    async transaction(callback) {
        const connection = await this.pool.getConnection();
        
        try {
            await connection.beginTransaction();
            const result = await callback(connection);
            await connection.commit();
            return result;
        } catch (error) {
            await connection.rollback();
            throw error;
        } finally {
            connection.release();
        }
    }

    async healthCheck() {
        try {
            await this.pool.execute('SELECT 1');
            return { status: 'healthy', timestamp: new Date().toISOString() };
        } catch (error) {
            return { 
                status: 'unhealthy', 
                error: error.message, 
                timestamp: new Date().toISOString() 
            };
        }
    }

    async close() {
        if (this.pool) {
            await this.pool.end();
            this.logger.info('MySQL pool closed');
        }
    }

    getPool() {
        return this.pool;
    }
}

 const dbService = new DatabaseService();

 
export async function initializeDatabase() {
    await dbService.initialize();
    return dbService; // kembalikan instance kalau butuh dipakai lagi
}

export default DatabaseService;
export { dbService };
