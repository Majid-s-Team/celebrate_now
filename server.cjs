const express = require("express");
const http = require("http");
const { Server } = require("socket.io");
const axios = require("axios");
const cors = require("cors");

const app = express();
app.use(cors());
app.use(express.json());

const server = http.createServer(app);
const io = new Server(server, { cors: { origin: "*" } });

let onlineUsers = new Map();
const LARAVEL_API_URL = "http://127.0.0.1:8000";

io.on("connection", (socket) => {
  console.log("New WebSocket connection established");

  socket.on("register", async (rawData) => {
    let data = typeof rawData === "string" ? JSON.parse(rawData) : rawData;
    const { user_id } = data;

    if (!user_id) {
      socket.emit("error", { message: "user_id is required" });
      socket.disconnect();
      return;
    }

    socket.userId = user_id;
    onlineUsers.set(user_id, socket);
    console.log(`User ${user_id} registered`);
    socket.emit("registered", { user_id });

    try {
      const res = await axios.get(`${LARAVEL_API_URL}/api/socket/messages/unseen/${user_id}`);
      if (res.data?.data?.length > 0) {
        socket.emit("receive_message", res.data.data);
        console.log(`Delivered unseen messages to ${user_id}`);
      }
    } catch (err) {
      console.log("Failed to load unseen messages:", err.message);
    }
  });

  socket.on("send_message", async (rawData) => {
    let data = typeof rawData === "string" ? JSON.parse(rawData) : rawData;
    const { sender_id, receiver_id, message } = data;

    if (!sender_id || !receiver_id || !message) {
      socket.emit("error", { message: "sender_id, receiver_id, and message are required" });
      return;
    }

    try {
      const response = await axios.post(`${LARAVEL_API_URL}/api/socket/messages`, {
        sender_id,
        receiver_id,
        message,
      });

      const savedMessage = response.data?.data;
      if (!savedMessage) {
        socket.emit("error", { message: "Invalid response from Laravel API" });
        return;
      }

      const receiverSocket = onlineUsers.get(receiver_id);
      if (receiverSocket) {
        receiverSocket.emit("receive_message", savedMessage);
        console.log(`Delivered to ${receiver_id}`);
      } else {
        console.log(`Receiver ${receiver_id} offline — message saved`);
      }

      socket.emit("message_sent", savedMessage);
    } catch (err) {
      console.log("Laravel API save failed:", err.message);
      socket.emit("error", { message: "Failed to send message" });
    }
  });

  socket.on("mark_seen", async (rawData) => {
    let data = typeof rawData === "string" ? JSON.parse(rawData) : rawData;
    const { message_ids } = data;

    if (!message_ids?.length) {
      socket.emit("error", { message: "message_ids are required" });
      return;
    }

    try {
      await axios.post(`${LARAVEL_API_URL}/api/socket/messages/seen`, { message_ids });
      console.log(`Marked seen: ${message_ids.join(", ")}`);
    } catch (err) {
      console.log("Mark seen failed:", err.message);
    }
  });

  socket.on("get_chat_history", async (rawData) => {
    let data = typeof rawData === "string" ? JSON.parse(rawData) : rawData;
    const { user_id, with_user_id } = data;

    if (!user_id || !with_user_id) {
      socket.emit("error", { message: "user_id and with_user_id are required" });
      return;
    }

    try {
      const response = await axios.get(
        `${LARAVEL_API_URL}/api/socket/messages/history/${user_id}/${with_user_id}`
      );

      socket.emit("chat_history", response.data.data);
      console.log(`Chat history sent to ${user_id}`);
    } catch (err) {
      console.log("Failed to fetch chat history:", err.message);
      socket.emit("error", { message: "Failed to load chat history" });
    }
  });

  socket.on("disconnect", () => {
    if (socket.userId) {
      onlineUsers.delete(socket.userId);
      console.log(`User ${socket.userId} disconnected`);
    }
  });
});

const PORT = 4000;
server.listen(PORT, () => console.log(`WebSocket server running on port ${PORT}`));
