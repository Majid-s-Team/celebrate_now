const express = require('express');
const http = require('http');
const cors = require('cors');
const bodyParser = require('body-parser');
const { Server } = require('socket.io');

// Express app setup
const app = express();
const server = http.createServer(app);

// Middlewares
app.use(cors());
app.use(bodyParser.json());

// Socket.IO setup
const io = new Server(server, {
    cors: {
        origin: "*", // Allow all origins (change to your frontend URL in production)
        methods: ["GET", "POST"]
    }
});

// Client connected
io.on('connection', (socket) => {
    console.log(`✅ Client connected: ${socket.id}`);

    socket.on('disconnect', () => {
        console.log(`❌ Client disconnected: ${socket.id}`);
    });
});

// Laravel will call this route to send real-time messages
app.post('/broadcast', (req, res) => {
    const { event, data } = req.body;

    console.log(`📢 Broadcasting event "${event}":`, data);

    // Broadcast to all clients
    io.emit(event, data);

    res.sendStatus(200);
});

// Start server
const PORT = 6001;
server.listen(PORT, () => {
    console.log(`🚀 Socket.IO server running on http://localhost:${PORT}`);
});
