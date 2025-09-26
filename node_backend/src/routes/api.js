import express from 'express';
import ApiController from '../controllers/apicontroller.js';

const router = express.Router();

export default function createApiRoutes(databaseService, botManager) {
    const apiController = new ApiController(databaseService, botManager);

    // Health check
    router.get('/health', (req, res) => apiController.getHealth(req, res));

    // Bot management
    router.get('/bots', (req, res) => apiController.getAllBots(req, res));
    router.get('/bots/:botId', (req, res) => apiController.getBotInfo(req, res));
    router.post('/bots/:botId/restart', (req, res) => apiController.restartBot(req, res));
    router.post('/bots/:botId/start', (req, res) => apiController.startBot(req, res));
    router.post('/bots/:botId/stop', (req, res) => apiController.stopBot(req, res));

    // Messages - Endpoint baru untuk pesan masuk/keluar
    router.get('/:botId/messages/incoming', (req, res) => apiController.getIncomingMessages(req, res));
    router.get('/:botId/messages/outgoing', (req, res) => apiController.getOutgoingMessages(req, res));
    router.post('/:botId/messages/send', (req, res) => apiController.sendMessage(req, res));
    router.put('/:botId/messages/:messageId/status', (req, res) => apiController.updateMessageStatus(req, res));

    // Stats
    router.get('/:botId/stats', (req, res) => apiController.getBotStats(req, res));

    // QR Code
    router.get('/:botId/qr', (req, res) => apiController.getQRCode(req, res));

    // Stats
    router.get('/:botId/stats', (req, res) => apiController.getBotStats(req, res));

    // QR Code
    router.get('/:botId/qr', (req, res) => apiController.getQRCode(req, res));

    // New Worker-related endpoints
    router.post('/:botId/messages/receive', (req, res) => apiController.receiveMessage(req, res));
    router.post('/:botId/messages/:messageId/retry', (req, res) => apiController.retryMessage(req, res));
    router.put('/:botId/messages/:messageId/delivery-status', (req, res) => apiController.updateDeliveryStatus(req, res));
    router.get('/worker/status', (req, res) => apiController.getWorkerStatus(req, res));
    router.post('/worker/process-now', (req, res) => apiController.processMessagesNow(req, res));

    

    return router;
}