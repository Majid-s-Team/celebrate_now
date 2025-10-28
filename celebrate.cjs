const express = require("express");
const http = require("http");
const { Server } = require("socket.io");
const axios = require("axios");
const cors = require("cors");
const fs = require("fs");
const path = require("path");
const FormData = require("form-data");

const app = express();
app.use(cors());
app.use(express.json());

const server = http.createServer(app);
const io = new Server(server, { cors: { origin: "*" } });

const LARAVEL_API_URL = "https://modmarket.retrocubedev.com";

let onlineUsers = new Map();

io.on("connection", (socket) => {
  console.log(" New WebSocket connection established");

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
        console.log(` Delivered unseen messages to ${user_id}`);
      }
      const inboxRes = await axios.get(`${LARAVEL_API_URL}/api/socket/messages/inbox/${user_id}`);
      if (inboxRes.data?.data?.length > 0) {
        socket.emit("inbox_list", inboxRes.data.data);
        console.log(` Sent inbox list to ${user_id}`);
      }
    } catch (err) {
      console.log(" Failed to load unseen messages:", err.message);
    }
  });

  socket.on("send_message", async (rawData) => {
    let data = typeof rawData === "string" ? JSON.parse(rawData) : rawData;
    const { sender_id, receiver_id, vehicle_ad_id, message, message_type = "text", media_url } = data;

    if (!sender_id || !receiver_id || (!message && !media_url)) {
      socket.emit("error", {
        message: "sender_id, receiver_id, and either message or media_url are required",
      });
      return;
    }

    try {
      const payload = { sender_id, receiver_id, message_type };
      if (vehicle_ad_id) payload.vehicle_ad_id = vehicle_ad_id;
      if (message) payload.message = message;
      if (media_url) payload.media_url = media_url;

      const response = await axios.post(`${LARAVEL_API_URL}/api/socket/messages`, payload);
      const savedMessage = response.data?.data;

      if (!savedMessage) {
        socket.emit("error", { message: "Invalid response from Laravel API" });
        return;
      }

      const receiverSocket = onlineUsers.get(receiver_id);

      receiverSocket
        ? receiverSocket.emit("receive_message", savedMessage)
        : console.log(` Receiver ${receiver_id} offline — message saved`);

      socket.emit("message_sent", savedMessage);

      const updateData = {
        chat_with_id: receiver_id,
        last_message: savedMessage.message,
        message_type: savedMessage.message_type,
        media_url: savedMessage.media_url,
        time: savedMessage.created_at,
        date: savedMessage.created_at,
      };

      socket.emit("update_inbox", updateData);
      receiverSocket
        ? receiverSocket.emit("update_inbox", {
            ...updateData,
            chat_with_id: sender_id,
          })
        : null;

      console.log(` Message sent from ${sender_id} to ${receiver_id}`);
    } catch (err) {
      console.log(" Laravel API save failed:", err.message);
      socket.emit("error", { message: "Failed to send message" });
    }
  });

  socket.on("get_chat_history", async (rawData) => {
    let data = typeof rawData === "string" ? JSON.parse(rawData) : rawData;
    const { user_id, with_user_id, vehicle_ad_id } = data;

    if (!user_id || !with_user_id) {
      socket.emit("error", { message: "user_id and with_user_id are required" });
      return;
    }

    try {
      const response = await axios.get(
        `${LARAVEL_API_URL}/api/socket/messages/history/${user_id}/${with_user_id}`
      );

      const chatHistory = response.data.data;
      socket.emit("chat_history", chatHistory);

      const unseenIds = chatHistory
        .filter((m) => m.receiver_id === user_id && m.status !== "read")
        .map((m) => m.id);

      if (unseenIds.length > 0) {
        await axios.post(`${LARAVEL_API_URL}/api/socket/messages/seen`, { message_ids: unseenIds });
        console.log(` Auto-marked ${unseenIds.length} messages as seen for ${user_id}`);
      }
    } catch (err) {
      console.log(" Failed to fetch chat history:", err.message);
      socket.emit("error", { message: "Failed to load chat history" });
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
      console.log(` Marked seen: ${message_ids.join(", ")}`);
    } catch (err) {
      console.log(" Mark seen failed:", err.message);
    }
  });

  socket.on("disconnect", () => {
    if (socket.userId) {
      onlineUsers.delete(socket.userId);
      console.log(` User ${socket.userId} disconnected`);
    }
  });
});

const PORT = 4000;
server.listen(PORT, () => console.log(` WebSocket server running on port ${PORT}`));
