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

const LARAVEL_API_URL = "https://celebratenow.retrocubedev.com";

let onlineUsers = new Map(); // user_id -> socket
let activeChats = new Map(); // user_id -> Set<with_user_id>

// ------------------ Helpers ------------------
const setActiveChat = (user_id, with_user_id) => {
  if (!activeChats.has(user_id)) activeChats.set(user_id, new Set());
  activeChats.get(user_id).add(with_user_id);
};

const closeChat = (user_id, with_user_id) => {
  if (activeChats.has(user_id)) {
    activeChats.get(user_id).delete(with_user_id);
    if (activeChats.get(user_id).size === 0) activeChats.delete(user_id);
  }
};

const dumpOnline = () =>
  [...onlineUsers.entries()].map(([id, s]) => ({ id, socketId: s?.id, connected: !!s?.connected }));

// ------------------ SOCKET CONNECTION ------------------
io.on("connection", (socket) => {
  console.log(" New WebSocket connection:", socket.id);

  // ------------------ REGISTER ------------------
  socket.on("register", async (rawData) => {
    const data = typeof rawData === "string" ? JSON.parse(rawData) : rawData;
    const { user_id } = data || {};
    if (!user_id) {
      socket.emit("error", { message: "user_id is required" });
      socket.disconnect();
      return;
    }

    // Replace old socket if same user reconnects
    if (onlineUsers.has(user_id)) {
      const old = onlineUsers.get(user_id);
      if (old && old.id !== socket.id) {
        try {
          old.disconnect(true);
          console.log(`Replaced old socket for user ${user_id}`);
        } catch (e) {
          console.log("Old disconnect error:", e.message);
        }
      }
    }

    socket.userId = user_id;
    onlineUsers.set(user_id, socket);
    console.log(`User ${user_id} registered → socket ${socket.id}`);
    socket.emit("registered", { user_id });

    try {
      const unseen = await axios.get(`${LARAVEL_API_URL}/api/socket/messages/unseen/${user_id}`);
      if (unseen.data?.data?.length)
        socket.emit("receive_message", unseen.data.data);
      const inbox = await axios.get(`${LARAVEL_API_URL}/api/socket/messages/inbox/${user_id}`);
      if (inbox.data?.data?.length)
        socket.emit("inbox_list", inbox.data.data);
    } catch (err) {
      console.log("Failed to fetch unseen/inbox:", err.message);
    }
  });

  // ------------------ GET CHAT HISTORY ------------------
  socket.on("get_chat_history", async (rawData) => {
    const data = typeof rawData === "string" ? JSON.parse(rawData) : rawData;
    const { user_id, with_user_id } = data || {};

    console.log("get_chat_history called by:", user_id, "with", with_user_id);
    if (!user_id || !with_user_id) return;

    try {
      const res = await axios.get(
        `${LARAVEL_API_URL}/api/socket/messages/history/${user_id}/${with_user_id}`
      );
      const chatHistory = res.data?.data || [];
      socket.emit("chat_history", chatHistory);
      console.log(`Sent chat history (${chatHistory.length}) to user ${user_id}`);

      // Find unseen messages where current user is receiver
      const unseen = chatHistory.filter(
        (m) => m.receiver_id === user_id && m.status !== "read"
      );
      const unseenIds = unseen.map((m) => m.id);
      console.log(`Unseen for ${user_id}:`, unseenIds);

      if (unseenIds.length) {
        await axios.post(`${LARAVEL_API_URL}/api/socket/messages/seen`, {
          message_ids: unseenIds,
        });
        console.log(`Marked as read in Laravel:`, unseenIds);

        const updated = unseen.map((m) => ({
          id: m.id,
          status: "read",
          sender_id: m.sender_id,
          receiver_id: m.receiver_id,
          message : m.message,
        }));

        // Emit locally to receiver
        socket.emit("status_update", updated);
        console.log(`status_update → receiver ${user_id}`);

        // Emit to sender real-time (most important part)
        const uniqueSenders = [...new Set(unseen.map((m) => m.sender_id))];
        for (const senderId of uniqueSenders) {
          const senderSocket = onlineUsers.get(senderId);
          if (senderSocket && senderSocket.connected) {
            senderSocket.emit("status_update", updated.filter((u) => u.sender_id === senderId));
            console.log(`Real-time status_update → sender ${senderId}`);
          } else {
            console.log(`Sender ${senderId} offline (status_update skipped)`);
          }
        }
      } else {
        console.log(`No unseen messages for ${user_id}`);
      }

      // mark active
      setActiveChat(user_id, with_user_id);
      console.log(`Active chat set: ${user_id} ↔ ${with_user_id}`);
    } catch (err) {
      console.log("get_chat_history error:", err.message);
    }
  });

  // ------------------ SEND MESSAGE ------------------
  socket.on("send_message", async (rawData) => {
    const data = typeof rawData === "string" ? JSON.parse(rawData) : rawData;
    const { sender_id, receiver_id, message, message_type = "text", media_url } = data || {};
    if (!sender_id || !receiver_id || (!message && !media_url)) return;

    console.log(`send_message: ${sender_id} -> ${receiver_id}`);

    try {
      const payload = { sender_id, receiver_id, message_type, message, media_url };
      const res = await axios.post(`${LARAVEL_API_URL}/api/socket/messages`, payload);
      const saved = res.data?.data;
      if (!saved) return;

      const receiverSocket = onlineUsers.get(receiver_id);
      const receiverActive = activeChats.get(receiver_id);


      if (receiverActive && receiverActive.has(sender_id)) {
        saved.status = "read";
        await axios.post(`${LARAVEL_API_URL}/api/socket/messages/seen`, {
          message_ids: [saved.id],
        });

        const statusPayload = [{ id: saved.id, status: "read", sender_id, receiver_id }];
        socket.emit("status_update", statusPayload);
        if (receiverSocket) receiverSocket.emit("status_update", statusPayload);
      } else {
        saved.status = "sent";
      }

      if (receiverSocket) {
        receiverSocket.emit("receive_message", saved);
        console.log(`Delivered message to ${receiver_id}`);
      }

      socket.emit("message_sent", saved);
      console.log(`message_sent ack to ${sender_id}`);
    } catch (err) {
      console.log("send_message error:", err.message);
    }
  });

  // ------------------ CHAT CLOSE ------------------
  socket.on("chat_close", (rawData) => {
    const data = typeof rawData === "string" ? JSON.parse(rawData) : rawData;
    const { user_id, with_user_id } = data || {};
    closeChat(user_id, with_user_id);
    console.log(`Chat closed: ${user_id} ↔ ${with_user_id}`);
  });

  // ------------------ DISCONNECT ------------------
  socket.on("disconnect", () => {
    if (socket.userId && onlineUsers.get(socket.userId)?.id === socket.id) {
      onlineUsers.delete(socket.userId);
      activeChats.delete(socket.userId);
      console.log(`User ${socket.userId} disconnected`);
    }
  });
});

const PORT = 5292;
server.listen(PORT, () =>
  console.log(`WebSocket server running on port ${PORT}`)
);
