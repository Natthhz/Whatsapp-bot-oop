import dotenv from 'dotenv';
import mysql from 'mysql2/promise';

dotenv.config();

export const databaseConfig = {
    host: process.env.DB_HOST || 'localhost',
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_NAME || 'whatsapp_bot3',
    port: parseInt(process.env.DB_PORT) || 3306,
    waitForConnections: true,
    connectionLimit: parseInt(process.env.DB_CONNECTION_LIMIT) || 10,
    queueLimit: parseInt(process.env.DB_QUEUE_LIMIT) || 0,
    acquireTimeout: 60000,
    timeout: 60000,
    reconnect: true,
    charset: 'utf8mb4'
};

export const pool = mysql.createPool(databaseConfig);

export const getConnection = () => pool.getConnection();
export const execute = (sql, params) => pool.execute(sql, params);

export const tableSchemas = {
    incoming_messages: `
        CREATE TABLE IF NOT EXISTS incoming_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sender_jid VARCHAR(255) NOT NULL,
            bot_id VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            sender_name VARCHAR(255),
            sender_phone VARCHAR(50),
            status ENUM('unread', 'read', 'replied', 'processed') DEFAULT 'unread',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_sender_bot (sender_jid, bot_id),
            INDEX idx_status (status),
            INDEX idx_bot_id (bot_id)
        )
    `,
   outgoing_messages: `
    CREATE TABLE IF NOT EXISTS outgoing_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        target_jid VARCHAR(255) NOT NULL,
        bot_id VARCHAR(50) NOT NULL,
        message TEXT NOT NULL,
        status ENUM('pending', 'sent', 'failed', 'processed') DEFAULT 'pending',
        delivery_status ENUM('sent', 'delivered', 'read') NULL,
        retry_count INT DEFAULT 0,
        reply_to INT NULL,
        whatsapp_message_id VARCHAR(255) NULL,
        error_message TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_target_bot (target_jid, bot_id),
        INDEX idx_status (status),
        INDEX idx_delivery_status (delivery_status),
        INDEX idx_retry_count (retry_count),
        INDEX idx_reply_to (reply_to),
        INDEX idx_bot_id (bot_id),
        INDEX idx_whatsapp_message_id (whatsapp_message_id)
    )
`,
    stats: `
        CREATE TABLE IF NOT EXISTS stats (
            id INT PRIMARY KEY DEFAULT 1,
            total_messages INT DEFAULT 0,
            last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            uptime TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    `,
    users: `
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            phone_number VARCHAR(50) UNIQUE,
            jid VARCHAR(255) UNIQUE,
            name VARCHAR(255),
            first_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            message_count INT DEFAULT 0,
            INDEX idx_phone (phone_number)
        
        )
    `,
 
    targets: `
        CREATE TABLE IF NOT EXISTS targets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sender_jid VARCHAR(255) NOT NULL,
            target_jid VARCHAR(255) NOT NULL,
            bot_id VARCHAR(50) NOT NULL,  
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_sender_target_bot (sender_jid, target_jid, bot_id),
            INDEX idx_sender (sender_jid),
            INDEX idx_target (target_jid),
            INDEX idx_bot (bot_id),
            INDEX idx_active (is_active)
        )
    `
};
