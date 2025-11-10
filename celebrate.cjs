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

const LARAVEL_API_URL = "http://127.0.0.1:8000";

let onlineUsers = new Map();
let activeChats = new Map();
let activeGroupChats = new Map(); // Track open group chats
let registeredUsers = new Set();

// ------------------ Helpers ------------------
const setActiveChat = (user_id, with_user_id) => {
  if (!activeChats.has(user_id)) activeChats.set(user_id, new Set());
  activeChats.get(user_id).add(with_user_id);
  console.log(`[DEBUG] Active chat set: ${user_id} <-> ${with_user_id}`);
};

const closeChat = (user_id, with_user_id) => {
  if (activeChats.has(user_id)) {
    activeChats.get(user_id).delete(with_user_id);
    if (activeChats.get(user_id).size === 0) activeChats.delete(user_id);
    console.log(`[DEBUG] Active chat closed: ${user_id} <-> ${with_user_id}`);
  }
};

// ------------------ SOCKET CONNECTION ------------------
io.on("connection", (socket) => {
  console.log("[DEBUG] New WebSocket connection:", socket.id);

  // ------------------ REGISTER ------------------
  socket.on("register", async (rawData) => {
    const data = typeof rawData === "string" ? JSON.parse(rawData) : rawData;
    const { user_id } = data || {};
    console.log("[DEBUG] register called with:", data);

    if (!user_id) {
      socket.emit("error", { message: "user_id is required" });
      socket.disconnect();
      return;
    }

    if (onlineUsers.has(user_id)) {
      const old = onlineUsers.get(user_id);
      if (old && old.id !== socket.id) {
        try {
          old.disconnect(true);
          console.log(`[DEBUG] Replaced old socket for user ${user_id}`);
        } catch (e) {
          console.log("[ERROR] Old disconnect error:", e.message);
        }
      }
    }

    socket.userId = user_id;
    onlineUsers.set(user_id, socket);
    registeredUsers.add(user_id);
    console.log(`[DEBUG] User ${user_id} registered → socket ${socket.id}`);
    socket.emit("registered", { user_id });

    try {
      const unseen = await axios.get(`${LARAVEL_API_URL}/api/socket/messages/unseen/${user_id}`);
      console.log("[DEBUG] unseen messages fetched:", unseen.data?.data?.length);

      if (unseen.data?.data?.length) socket.emit("receive_message", unseen.data.data);

      const inboxRes = await axios.get(`${LARAVEL_API_URL}/api/socket/messages/inbox/${user_id}`);
      const inboxData = inboxRes.data?.data?.map(item => ({ ...item, is_group: false })) || [];

      const groupRes = await axios.get(`${LARAVEL_API_URL}/api/groups/user/${user_id}`);
      const groupData = groupRes.data?.data?.map(g => ({ ...g, is_group: true })) || [];

      const combinedInbox = [...inboxData, ...groupData];
      if (combinedInbox.length) socket.emit("inbox_list", combinedInbox);

      console.log(`[DEBUG] Sent inbox + group list to ${user_id}`);
    } catch (err) {
      console.log("[ERROR] Failed to fetch unseen/inbox/group_list:", err.message);
    }
  });

  // ------------------ GET CHAT HISTORY ------------------
  socket.on("get_chat_history", async (rawData) => {
    try {
      console.log("[DEBUG] get_chat_history called with:", rawData);
      const data = typeof rawData === "string" ? JSON.parse(rawData) : rawData;
      const { user_id, with_user_id } = data || {};
      if (!user_id || !with_user_id) return;

      const res = await axios.get(`${LARAVEL_API_URL}/api/socket/messages/history/${user_id}/${with_user_id}`);
      const chatHistory = res.data?.data || [];
      console.log(`[DEBUG] chat_history fetched: ${chatHistory.length} messages`);
      socket.emit("chat_history", chatHistory);

      const unseen = chatHistory.filter(m => m.receiver_id === user_id && m.status !== "read");
      const unseenIds = unseen.map(m => m.id);
      console.log("[DEBUG] unseen message IDs:", unseenIds);

      if (unseenIds.length) {
        await axios.post(`${LARAVEL_API_URL}/api/socket/messages/seen`, { message_ids: unseenIds });
        console.log("[DEBUG] POST /messages/seen called");

        const updated = unseen.map(m => ({
          id: m.id,
          status: "read",
          sender_id: m.sender_id,
          receiver_id: m.receiver_id,
          message: m.message,
        }));

        socket.emit("status_update", updated);
        console.log("[DEBUG] status_update emitted to user:", user_id);

        const uniqueSenders = [...new Set(unseen.map(m => m.sender_id))];
        for (const senderId of uniqueSenders) {
          const senderSocket = onlineUsers.get(senderId);
          if (senderSocket) {
            senderSocket.emit("status_update", updated);
            console.log(`[DEBUG] status_update emitted to sender: ${senderId}`);
          }
        }
      }

      setActiveChat(user_id, with_user_id);

    } catch (err) {
      console.log("[ERROR] get_chat_history error:", err.message);
    }
  });

  // ------------------ SEND MESSAGE ------------------
  socket.on("send_message", async (rawData) => {
    try {
      console.log("[DEBUG] send_message called with:", rawData);
      const data = typeof rawData === "string" ? JSON.parse(rawData) : rawData;
      const { sender_id, receiver_id, message, message_type = "text", media_url } = data || {};
      if (!sender_id || !receiver_id || (!message && !media_url)) return;

      const payload = { sender_id, receiver_id, message_type, message, media_url };
      const res = await axios.post(`${LARAVEL_API_URL}/api/socket/messages`, payload);
      const saved = res.data?.data;
      if (!saved) return;
      console.log("[DEBUG] Message saved:", saved);

      const receiverSocket = onlineUsers.get(receiver_id);
      const receiverActive = activeChats.get(receiver_id);

      if (receiverActive && receiverActive.has(sender_id)) {
        saved.status = "read";
        await axios.post(`${LARAVEL_API_URL}/api/socket/messages/seen`, { message_ids: [saved.id] });
        console.log(`[DEBUG] Message ${saved.id} marked as read for receiver ${receiver_id}`);
      } else if (registeredUsers.has(receiver_id)) {
        saved.status = "delivered";
        await axios.post(`${LARAVEL_API_URL}/api/socket/messages/delivered`, { message_ids: [saved.id] });
        console.log(`[DEBUG] Message ${saved.id} marked as delivered`);
      } else {
        saved.status = "sent";
        console.log(`[DEBUG] Message ${saved.id} status set as sent`);
      }

      const statusPayload = [{ id: saved.id, status: saved.status, sender_id, receiver_id }];
      socket.emit("status_update", statusPayload);
      if (receiverSocket) receiverSocket.emit("status_update", statusPayload);
      console.log("[DEBUG] status_update emitted");

      if (receiverSocket) receiverSocket.emit("receive_message", saved);
      socket.emit("message_sent", saved);
      console.log("[DEBUG] message_sent emitted");

    } catch (err) {
      console.log("[ERROR] send_message error:", err.message);
    }
  });

  // ------------------ CHAT CLOSE ------------------
  socket.on("chat_close", (rawData) => {
    const data = typeof rawData === "string" ? JSON.parse(rawData) : rawData;
    const { user_id, with_user_id } = data || {};
    closeChat(user_id, with_user_id);
  });

  // ------------------ GROUP CHAT SECTION ------------------
  socket.on("create_group", async (rawData) => {
    try {
      const data = typeof rawData === "string" ? JSON.parse(rawData) : rawData;
      const { name, profile_image, created_by, description, members = [] } = data;
      console.log("[DEBUG] create_group called with:", data);

      if (!name || !created_by) {
        socket.emit("error", { message: "Both name and created_by are required" });
        return;
      }

      const res = await axios.post(`${LARAVEL_API_URL}/api/groups/create`, {
        name, profile_image, created_by, description, members,
      });
      console.log("[DEBUG] Group created:", res.data.data);
      socket.emit("group_created", res.data.data);

    } catch (err) {
      console.error("[ERROR] create_group error:", err.response?.data || err.message);
      socket.emit("error", { message: "Failed to create group" });
    }
  });

  socket.on("add_member", async (rawData) => {
    try {
      const data = typeof rawData === "string" ? JSON.parse(rawData) : rawData;
      const { group_id, added_by, members } = data;
      console.log("[DEBUG] add_member called with:", data);

      if (!group_id || !added_by || !Array.isArray(members) || members.length === 0) {
        socket.emit("error", { message: "Invalid add member request" });
        return;
      }

      const res = await axios.post(`${LARAVEL_API_URL}/api/groups/${group_id}/add-member`, { added_by, members });
      console.log("[DEBUG] Members added:", res.data.data);
      socket.emit("member_added", res.data.data);

    } catch (err) {
      console.error("[ERROR] add_member error:", err.response?.data || err.message);
      socket.emit("error", { message: "Failed to add members" });
    }
  });

  socket.on("remove_member", async (rawData) => {
    try {
      const data = typeof rawData === "string" ? JSON.parse(rawData) : rawData;
      const { group_id, removed_by, user_id } = data;
      console.log("[DEBUG] remove_member called with:", data);

      if (!group_id || !removed_by || !user_id) {
        socket.emit("error", { message: "Invalid remove member request" });
        return;
      }

      const res = await axios.post(`${LARAVEL_API_URL}/api/groups/${group_id}/remove-member`, { removed_by, user_id });
      console.log("[DEBUG] Member removed:", res.data.data);
      socket.emit("member_removed", res.data.data);

    } catch (err) {
      console.error("[ERROR] remove_member error:", err.response?.data || err.message);
      socket.emit("error", { message: "Failed to remove member" });
    }
  });

  socket.on("update_group", async (rawData) => {
    try {
      const data = typeof rawData === "string" ? JSON.parse(rawData) : rawData;
      const { group_id, name, description } = data;
      console.log("[DEBUG] update_group called with:", data);

      if (!group_id || !name) {
        socket.emit("error", { message: "group_id and name are required" });
        return;
      }

      const res = await axios.post(`${LARAVEL_API_URL}/api/groups/update/${group_id}`, { name, description });
      const updatedGroup = res.data.data;
      console.log("[DEBUG] Group updated:", updatedGroup);
      socket.emit("group_updated", updatedGroup);

      const membersRes = await axios.get(`${LARAVEL_API_URL}/api/groups/${group_id}/members`);
      const members = membersRes.data.data;
      for (const m of members) {
        const memberSocket = onlineUsers.get(m.user_id);
        if (memberSocket) memberSocket.emit("group_updated", updatedGroup);
      }

    } catch (err) {
      console.error("[ERROR] update_group error:", err.response?.data || err.message);
      socket.emit("error", { message: "Failed to update group" });
    }
  });

  // ------------------ SEND GROUP MESSAGE ------------------
  socket.on("send_group_message", async (rawData) => {
  try {
    const data = typeof rawData === "string" ? JSON.parse(rawData) : rawData;
    const { group_id, sender_id, message, message_type = "text", media_url } = data;
    if (!group_id || !sender_id || (!message && !media_url)) return;

    const res = await axios.post(`${LARAVEL_API_URL}/api/groups/message`, { group_id, sender_id, message, message_type, media_url });
    const msg = res.data.data;
    if (!msg) return;

    const membersRes = await axios.get(`${LARAVEL_API_URL}/api/groups/${group_id}/members`);
    const members = membersRes.data.data;

    for (const m of members) {
      const memberSocket = onlineUsers.get(m.user_id);
      if (memberSocket) {
        // agar member ne chat open kiya hua hai → read
        if (activeGroupChats.has(m.user_id) && activeGroupChats.get(m.user_id).has(group_id)) {
          msg.status = "read";
          await axios.post(`${LARAVEL_API_URL}/api/groups/message/seen`, { receiver_id: m.user_id, group_id, message_ids: [msg.id] });

          // sender ko real-time notify (read)
          const senderSocket = onlineUsers.get(sender_id);
          if (senderSocket) {
            senderSocket.emit("group_status_update", {
              group_id,
              receiver_id: m.user_id,
              message_id: msg.id,
              is_read: true,
            });
          }
        }

        // message har member ko bhej do
        memberSocket.emit("receive_group_message", msg);
      }
    }

    socket.emit("group_message_sent", msg);
  } catch (err) {
    console.error("[ERROR] send_group_message error:", err.message);
    socket.emit("error", { message: "Failed to send group message" });
  }
});


  // ------------------ GET GROUP HISTORY ------------------
socket.on("get_group_history", async (rawData) => {
  try {
    const data = typeof rawData === "string" ? JSON.parse(rawData) : rawData;
    const { group_id, receiver_id } = data;
    if (!group_id || !receiver_id) return;

    // 1️⃣ Fetch full history
    const res = await axios.get(`${LARAVEL_API_URL}/api/groups/history/${group_id}/${receiver_id}`);
    const messages = res.data.data || [];

    // 2️⃣ Emit full history to receiver only
    socket.emit("group_history", messages);
    console.log("✅ group_history emitted to receiver:", receiver_id);

    // 3️⃣ Filter unread messages
    const unreadMessages = messages.filter(m => m.group_id === group_id && m.is_read === 0);
    const unreadIds = unreadMessages.map(m => m.id);

    if (unreadIds.length) {
      // 4️⃣ Mark messages as seen in backend
      await axios.post(`${LARAVEL_API_URL}/api/groups/message/seen`, {
        receiver_id,
        group_id,
        message_ids: unreadIds,
      });

      // 5️⃣ Emit unread status to receiver only
      const statusPayload = {
        group_id,
        receiver_id,
        message_ids: unreadIds,
        is_read: true,
      };
      socket.emit("group_status_update", statusPayload);
      console.log("✅ group_status_update emitted to receiver:", receiver_id);

      // 6️⃣ Notify senders individually, skip receiver
      const uniqueSenderIds = [...new Set(unreadMessages.map(m => m.sender_id))];
      uniqueSenderIds.forEach(senderId => {
        if (senderId === receiver_id) return; // skip receiver

        // ⚠️ Broadcast to everyone except current socket (receiver)
        socket.broadcast.emit("group_status_update", {
          ...statusPayload,
          senderId
        });
        console.log("✅ Status update emitted for sender:", senderId);
      });
    }

    // 7️⃣ Mark group active for receiver
    if (!activeGroupChats.has(receiver_id)) activeGroupChats.set(receiver_id, new Set());
    activeGroupChats.get(receiver_id).add(group_id);

  } catch (err) {
    console.error("[ERROR] get_group_history error:", err.message);
    socket.emit("error", { message: "Failed to fetch group history" });
  }
});












  // ------------------ GROUP CHAT CLOSE ------------------
  socket.on("group_chat_close", (rawData) => {
    const data = typeof rawData === "string" ? JSON.parse(rawData) : rawData;
    const { user_id, group_id } = data || {};
    if (!user_id || !group_id) return;

    if (activeGroupChats.has(user_id)) {
      activeGroupChats.get(user_id).delete(group_id);
      if (activeGroupChats.get(user_id).size === 0) activeGroupChats.delete(user_id);
      console.log(`[DEBUG] User ${user_id} closed group chat ${group_id}`);
    }
  });

  // ------------------ DISCONNECT ------------------
  socket.on("disconnect", () => {
    if (socket.userId && onlineUsers.get(socket.userId)?.id === socket.id) {
      onlineUsers.delete(socket.userId);
      activeChats.delete(socket.userId);
      activeGroupChats.delete(socket.userId);
      registeredUsers.delete(socket.userId);
      console.log(`[DEBUG] User ${socket.userId} disconnected`);
    }
  });

});

const PORT = 5292;
server.listen(PORT, () => console.log(`[DEBUG] WebSocket server running on port ${PORT}`));
