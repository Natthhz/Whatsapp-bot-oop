// File: src/routes/api-server.js (atau mungkin main api.js)
import express from 'express';
import cors from 'cors';
import createApiRoutes from './api.js'; // Import dari file routes

async function startAPIServer(app, botManager, dbService) {
    try {
        // Middleware
        app.use(cors());
        app.use(express.json());

        // Create API routes
        const apiRoutes = createApiRoutes(dbService, botManager);
        app.use('/api', apiRoutes);

        // ... server configuration ...
    } catch (error) {
        console.error('Failed to start API server:', error);
        throw error;
    }
}

export default startAPIServer;