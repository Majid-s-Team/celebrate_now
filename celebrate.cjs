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

// const LARAVEL_API_URL = "https://celebratenow.retrocubedev.com";
const LARAVEL_API_URL = "http://127.0.0.1:8000";

let onlineUsers = new Map();
let activeChats = new Map();
let registeredUsers = new Set();

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
    registeredUsers.add(user_id);
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
    if (!user_id || !with_user_id) return;

    try {
      const res = await axios.get(
        `${LARAVEL_API_URL}/api/socket/messages/history/${user_id}/${with_user_id}`
      );
      const chatHistory = res.data?.data || [];
      socket.emit("chat_history", chatHistory);

      const unseen = chatHistory.filter(
        (m) => m.receiver_id === user_id && m.status !== "read"
      );
      const unseenIds = unseen.map((m) => m.id);

      if (unseenIds.length) {
        await axios.post(`${LARAVEL_API_URL}/api/socket/messages/seen`, {
          message_ids: unseenIds,
        });

        const updated = unseen.map((m) => ({
          id: m.id,
          status: "read",
          sender_id: m.sender_id,
          receiver_id: m.receiver_id,
          message: m.message,
        }));

        socket.emit("status_update", updated);
        const uniqueSenders = [...new Set(unseen.map((m) => m.sender_id))];
        for (const senderId of uniqueSenders) {
          const senderSocket = onlineUsers.get(senderId);
          if (senderSocket) senderSocket.emit("status_update", updated);
        }
      }

      setActiveChat(user_id, with_user_id);
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

      // --- Determine message status ---
      if (receiverActive && receiverActive.has(sender_id)) {
        // Chat open → mark as read
        saved.status = "read";
        await axios.post(`${LARAVEL_API_URL}/api/socket/messages/seen`, {
          message_ids: [saved.id],
        });
      } else if (registeredUsers.has(receiver_id)) {
        // User registered → delivered
        saved.status = "delivered";
        await axios.post(`${LARAVEL_API_URL}/api/socket/messages/delivered`, {
          message_ids: [saved.id],
        });
      } else {
        // Offline → sent
        saved.status = "sent";
      }

      const statusPayload = [{ id: saved.id, status: saved.status, sender_id, receiver_id }];
      socket.emit("status_update", statusPayload);
      if (receiverSocket) receiverSocket.emit("status_update", statusPayload);

      if (receiverSocket) receiverSocket.emit("receive_message", saved);
      socket.emit("message_sent", saved);
    } catch (err) {
      console.log("send_message error:", err.message);
    }
  });

  // ------------------ CHAT CLOSE ------------------
  socket.on("chat_close", (rawData) => {
    const data = typeof rawData === "string" ? JSON.parse(rawData) : rawData;
    const { user_id, with_user_id } = data || {};
    closeChat(user_id, with_user_id);
  });

  // ============================================================
  // ------------------ GROUP CHAT SECTION ----------------------
  // ============================================================

  socket.on("create_group", async (rawData) => {
    try {
      const data = typeof rawData === "string" ? JSON.parse(rawData) : rawData;
      const { name, created_by,description, members = [] } = data;

      if (!name || !created_by) {
        socket.emit("error", { message: "Both name and created_by are required" });
        return;
      }

      const res = await axios.post(`${LARAVEL_API_URL}/api/groups/create`, {
        name,
        created_by,
        description,
        members,
      });

      socket.emit("group_created", res.data.data);
    } catch (err) {
      console.error("create_group error:", err.response?.data || err.message);
      socket.emit("error", { message: "Failed to create group" });
    }
  });

  socket.on("add_member", async (rawData) => {
    try {
      const data = typeof rawData === "string" ? JSON.parse(rawData) : rawData;
      const { group_id, added_by, members } = data;

      if (!group_id || !added_by || !Array.isArray(members) || members.length === 0) {
        socket.emit("error", { message: "Invalid add member request" });
        return;
      }

      const res = await axios.post(
        `${LARAVEL_API_URL}/api/groups/${group_id}/add-member`,
        { added_by, members },
        { headers: { "Content-Type": "application/json" } }
      );

      socket.emit("member_added", res.data.data);
    } catch (err) {
      console.error("add_member error:", err.response?.data || err.message);
      socket.emit("error", { message: "Failed to add members" });
    }
  });

  socket.on("remove_member", async (rawData) => {
    try {
      const data = typeof rawData === "string" ? JSON.parse(rawData) : rawData;
      const { group_id, removed_by, user_id } = data;

      if (!group_id || !removed_by || !user_id) {
        socket.emit("error", { message: "Invalid remove member request" });
        return;
      }

      const res = await axios.post(
        `${LARAVEL_API_URL}/api/groups/${group_id}/remove-member`,
        { removed_by, user_id },
        { headers: { "Content-Type": "application/json" } }
      );

      socket.emit("member_removed", res.data.data);
    } catch (err) {
      console.error("remove_member error:", err.response?.data || err.message);
      socket.emit("error", { message: "Failed to remove member" });
    }
  });

  // ✅ UPDATE GROUP EVENT (added safely)
  socket.on("update_group", async (rawData) => {
    try {
      const data = typeof rawData === "string" ? JSON.parse(rawData) : rawData;
      const { group_id, name, description } = data;

      if (!group_id || !name) {
        socket.emit("error", { message: "group_id and name are required" });
        return;
      }

      const res = await axios.post(
        `${LARAVEL_API_URL}/api/groups/update/${group_id}`,
        { name, description },
        { headers: { "Content-Type": "application/json" } }
      );

      const updatedGroup = res.data.data;
      socket.emit("group_updated", updatedGroup);

      const membersRes = await axios.get(`${LARAVEL_API_URL}/api/groups/${group_id}/members`);
      const members = membersRes.data.data;

      for (const m of members) {
        const memberSocket = onlineUsers.get(m.user_id);
        if (memberSocket) {
          memberSocket.emit("group_updated", updatedGroup);
        }
      }

      console.log(`Group ${group_id} updated successfully`);
    } catch (err) {
      console.error("update_group error:", err.response?.data || err.message);
      socket.emit("error", { message: "Failed to update group" });
    }
  });

  socket.on("send_group_message", async (rawData) => {
    try {
      const data = typeof rawData === "string" ? JSON.parse(rawData) : rawData;
      const { group_id, sender_id, message, message_type = "text", media_url } = data;

      if (!group_id || !sender_id || (!message && !media_url)) {
        socket.emit("error", { message: "Invalid group message payload" });
        return;
      }

      const res = await axios.post(`${LARAVEL_API_URL}/api/groups/message`, {
        group_id,
        sender_id,
        message,
        message_type,
        media_url,
      });

console.log(`${LARAVEL_API_URL}/api/groups/message`);


      const msg = res.data.data;
      if (!msg) {
        socket.emit("error", { message: "Message not saved" });
        return;
      }

      const membersRes = await axios.get(`${LARAVEL_API_URL}/api/groups/${group_id}/members`);
      const members = membersRes.data.data;

      for (const m of members) {
        const memberSocket = onlineUsers.get(m.user_id);
        if (memberSocket) {
          memberSocket.emit("receive_group_message", msg);
        }
      }

      console.log(`Group ${group_id}: message sent by ${sender_id}`);
    } catch (err) {
      console.error("send_group_message error:", err.message);
      socket.emit("error", { message: "Failed to send group message" });
    }
  });

  socket.on("get_group_history", async (rawData) => {
    try {
      const data = typeof rawData === "string" ? JSON.parse(rawData) : rawData;
      const { group_id, user_id } = data;

      if (!group_id || !user_id) {
        socket.emit("error", { message: "group_id and user_id are required" });
        return;
      }

      const res = await axios.get(`${LARAVEL_API_URL}/api/groups/history/${group_id}/${user_id}`);
      const messages = res.data.data;
      socket.emit("group_history", messages);

      console.log(`Sent group history for group ${group_id} to user ${user_id}`);
    } catch (err) {
      console.error("get_group_history error:", err.message);
      socket.emit("error", { message: "Failed to fetch group history" });
    }
  });

  // ------------------ DISCONNECT ------------------
  socket.on("disconnect", () => {
    if (socket.userId && onlineUsers.get(socket.userId)?.id === socket.id) {
      onlineUsers.delete(socket.userId);
      activeChats.delete(socket.userId);
      registeredUsers.delete(socket.userId);
      console.log(`User ${socket.userId} disconnected`);
    }
  });
});

const PORT = 5292;
server.listen(PORT, () =>
  console.log(`WebSocket server running on port ${PORT}`)
);
