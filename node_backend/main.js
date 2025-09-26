import BotManager from './src/bots/botmanager.js';
import { initializeDatabase } from './src/services/databaseservice.js';
import createApiRoutes from './src/routes/api.js';
import express from 'express';
import cors from 'cors';
import Logger from './src/utils/logger.js';

let botManager;
let dbService;
const logger = new Logger('Server');

async function main() {
    try {
        console.log("  Starting WhatsApp Bot OOP System...");
        logger.info('Starting server initialization');

        dbService = await initializeDatabase();
        console.log("  MySQL Database initialized");
        logger.info('Database initialized');

        botManager = new BotManager(dbService);
        console.log("  Bot Manager initialized");

        await botManager.startAllBots();
        console.log("  All bots started");
        logger.info('All bots started');

        const app = express();
        
        app.use(cors());
        app.use(express.json({ limit: '50mb' }));
        app.use(express.urlencoded({ extended: true, limit: '50mb' }));

        app.use((req, res, next) => {
            logger.info(`${req.method} ${req.path}`);
            next();
        });

        const apiRoutes = createApiRoutes(dbService, botManager);
        app.use('/api', apiRoutes);

        app.get('/health', (req, res) => {
            res.json({
                status: 'healthy',
                timestamp: new Date().toISOString(),
                bots: botManager.getAllBotStatuses().length,
                worker: botManager.workerStatus
            });
        });

        app.get('/', (req, res) => {
            res.json({ 
                message: 'WhatsApp Bot API Server', 
                status: 'running',
                timestamp: new Date().toISOString(),
                endpoints: {
                    health: '/health',
                    bots: '/api/bots',
                    messages: '/api/:botId/messages',
                    stats: '/api/:botId/stats'
                }
            });
        });

        app.use((error, req, res, next) => {
            console.error('Unhandled error:', error);
            logger.error('Unhandled error:', error);
            res.status(500).json({ 
                success: false, 
                error: 'Internal server error',
                message: error.message 
            });
        });

        app.use('*', (req, res) => {
            res.status(404).json({ 
                success: false, 
                error: 'Endpoint not found',
                path: req.originalUrl 
            });
        });

        const PORT = process.env.PORT || 3000;
        const server = app.listen(PORT, '0.0.0.0', () => {
            console.log(`  API Server running on http://0.0.0.0:${PORT}`);
            console.log(`  API endpoints available at http://0.0.0.0:${PORT}/api`);
            console.log(`  Health check: http://0.0.0.0:${PORT}/health`);
            logger.info(`Server running on port ${PORT}`);
            logger.info(`Health check: http://localhost:${PORT}/health`);
            logger.info(`API base: http://localhost:${PORT}/api`);
        });

        server.on('error', (error) => {
            console.error('Server error:', error);
            logger.error('Server error:', error);
            if (error.code === 'EADDRINUSE') {
                console.log(`Port ${PORT} is already in use, trying ${Number(PORT) + 1}...`);
                logger.warn(`Port ${PORT} is already in use, trying ${Number(PORT) + 1}`);
                server.listen(Number(PORT) + 1);
            }
        });

        console.log("  System started successfully");
        logger.info('System started successfully');

    } catch (error) {
        console.error("  Error starting system:", error);
        logger.error('Error starting system:', error);
        setTimeout(main, 10000);
    }
}

process.on('SIGINT', async () => {
    console.log('\n  Shutting down bot system gracefully...');
    logger.info('SIGINT received, shutting down gracefully');
    
    try {
        if (botManager) {
            await botManager.stopAllBots();
            console.log('  All bots stopped');
            logger.info('All bots stopped');
        }
        
        if (dbService) {
            await dbService.close();
            console.log('  Database connection closed');
            logger.info('Database connection closed');
        }
        
        console.log('  System shutdown complete');
        logger.info('System shutdown complete');
        process.exit(0);
    } catch (error) {
        console.error('Error during shutdown:', error);
        logger.error('Error during shutdown:', error);
        process.exit(1);
    }
});

process.on('SIGTERM', async () => {
    console.log('\n  Received SIGTERM, shutting down gracefully...');
    logger.info('SIGTERM received, shutting down gracefully');
    
    try {
        if (botManager) {
            await botManager.stopAllBots();
            logger.info('All bots stopped');
        }
        
        if (dbService) {
            await dbService.close();
            logger.info('Database connection closed');
        }
        
        process.exit(0);
    } catch (error) {
        console.error('Error during SIGTERM shutdown:', error);
        logger.error('Error during SIGTERM shutdown:', error);
        process.exit(1);
    }
});

process.on('uncaughtException', (error) => {
    console.error('Uncaught Exception:', error);
    logger.error('Uncaught Exception:', error);
    setTimeout(main, 5000);
});

process.on('unhandledRejection', (reason, promise) => {
    console.error('Unhandled Rejection at:', promise, 'reason:', reason);
    logger.error('Unhandled Rejection at:', promise, 'reason:', reason);
});

main();

export default main;