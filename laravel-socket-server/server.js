const express = require('express');
const http = require('http');
const socketIo = require('socket.io');
const axios = require('axios');  // HTTP client to call Laravel APIs
const bodyParser = require('body-parser');

const app = express();
const server = http.createServer(app);
const io = socketIo(server, { cors: { origin: "*" } });

io.on('connection', (socket) => {
  console.log('Client connected:', socket.id);

  socket.on('start_conversation', async (data) => {
    try {
      const response = await axios.get(`http://localhost/api/start-conversation/${data.receiverId}`, {
        headers: { Authorization: `Bearer ${data.token}` }
      });
      socket.emit('start_conversation_response', response.data);
    } catch (err) {
      socket.emit('error', { message: err.message });
    }
  });

  socket.on('send_message', async (data) => {
    try {
      const response = await axios.post(`http://localhost/api/send-message`, data.payload, {
        headers: { Authorization: `Bearer ${data.token}` }
      });
      socket.emit('send_message_response', response.data);
    } catch (err) {
      socket.emit('error', { message: err.message });
    }
  });

  socket.on('get_messages', async (data) => {
    try {
      const response = await axios.get(`http://localhost/api/get-messages/${data.conversationId}`, {
        headers: { Authorization: `Bearer ${data.token}` }
      });
      socket.emit('get_messages_response', response.data);
    } catch (err) {
      socket.emit('error', { message: err.message });
    }
  });

  socket.on('mark_as_seen', async (data) => {
    try {
      const response = await axios.post(`http://localhost/api/mark-as-seen/${data.conversationId}`, {}, {
        headers: { Authorization: `Bearer ${data.token}` }
      });
      socket.emit('mark_as_seen_response', response.data);
    } catch (err) {
      socket.emit('error', { message: err.message });
    }
  });

  socket.on('disconnect', () => {
    console.log('Client disconnected:', socket.id);
  });
});

server.listen(6001, () => {
  console.log('Socket.io server running on port 6001');
});
