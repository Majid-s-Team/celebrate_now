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

// const LARAVEL_API_URL = "http://127.0.0.1:8000";


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

  // Replace old socket if exists
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
    //
    // ============================================================
    //  FETCH INBOX + GROUP LIST
    // ============================================================
    //
    const inboxRes = await axios.get(`${LARAVEL_API_URL}/api/socket/messages/inbox/${user_id}`);
    const inboxData = inboxRes.data?.data?.map(item => ({ ...item, is_group: false })) || [];

    const groupRes = await axios.get(`${LARAVEL_API_URL}/api/groups/user/${user_id}`);
    const groupData = groupRes.data?.data?.map(g => ({ ...g, is_group: true })) || [];

    const combinedInbox = [...inboxData, ...groupData];

    if (combinedInbox.length) socket.emit("inbox_list", combinedInbox);

    console.log(`[DEBUG] Sent inbox + group list to ${user_id}`);

    //
    // ============================================================
    // 1:1 MESSAGE DELIVERED SYSTEM (CORRECT LOGIC)
    // ============================================================
    //
    const pendingRes = await axios.get(
      `${LARAVEL_API_URL}/api/socket/messages/pending/${user_id}`
    );

    const pendingMessages = pendingRes.data?.data || [];
    const messageIds = pendingMessages.map(m => m.id);

    if (messageIds.length > 0) {
      // Mark message_ids as delivered
      await axios.post(`${LARAVEL_API_URL}/api/socket/messages/delivered`, {
        message_ids: messageIds
      });

      for (const msg of pendingMessages) {
        const message_id = msg.id;
        const sender_id = msg.sender_id;

        // Emit to receiver
        socket.emit("status_update", [{
          id: message_id,
          receiver_id: user_id,
          status: "delivered",
          is_group: false
        }]);

        console.log(`[DEBUG] Delivered 1:1 → receiver ${user_id}, msg ${message_id}`);

        // Emit to sender
        const senderSocket = onlineUsers.get(sender_id);
        if (senderSocket && sender_id !== user_id) {
          senderSocket.emit("status_update", [{
            id: message_id,
            sender_id,
            receiver_id: user_id,
            status: "delivered",
            is_group: false
          }]);

          console.log(`[DEBUG] Delivered 1:1 → sender ${sender_id}, msg ${message_id}`);
        }
      }
    }

    //
    // ============================================================
    // GROUP MESSAGE DELIVERED SYSTEM
    // ============================================================
    //
   const deliveredGroup = await axios.post(`${LARAVEL_API_URL}/api/groups/message/delivered`, {
  receiver_id: user_id
});

const deliveredGroupData = deliveredGroup.data?.data || {};

if (deliveredGroupData.updated_rows > 0 && Array.isArray(deliveredGroupData.message_ids)) {
  for (const message_id of deliveredGroupData.message_ids) {

    // Emit to receiver
    socket.emit("status_update", [{
      id: message_id,
      group_id: deliveredGroupData.group_id || null,
      receiver_id: user_id,
      updated_at: deliveredGroupData.updated_at || null,
      status: "delivered",
      is_group: true,

      receiver: deliveredGroupData.receiver ?? 0
    }]);

    console.log(`[DEBUG] Delivered GROUP → receiver ${user_id}, msg ${message_id}`);

    // Emit to sender
    const sender_id = deliveredGroupData.sender_id;
    const senderSocket = onlineUsers.get(sender_id);

    if (senderSocket && sender_id !== user_id) {
      senderSocket.emit("status_update", [{
        id: message_id,
        group_id: deliveredGroupData.group_id || null,
        receiver_id: user_id,
        sender_id,
        updated_at: deliveredGroupData.updated_at || null,
        status: "delivered",
        is_group: true,

        receiver: deliveredGroupData.receiver ?? 0
      }]);

          console.log(`[DEBUG] Delivered GROUP → sender ${sender_id}, msg ${message_id}`);
        }
      }
    }

  } catch (err) {
    console.log("[ERROR] Failed in register:", err.message);
  }
});




  // ------------------ GET CHAT HISTORY ------------------

 socket.on("get_chat_history", async (rawData) => {
  try {
    console.log("[DEBUG] get_chat_history called with:", rawData);
    const data = typeof rawData === "string" ? JSON.parse(rawData) : rawData;
    const { user_id, with_user_id, group_id } = data || {};
    if (!user_id && !group_id) return;

    // ----------------------- 1️⃣ Individual chat -----------------------
    if (with_user_id) {

      // ⭐ REALTIME ENABLED
      socket.join(`chat_${user_id}_${with_user_id}`);

      const res = await axios.get(`${LARAVEL_API_URL}/api/socket/messages/history/${user_id}/${with_user_id}`);
      const chatHistory = res.data?.data || [];
      console.log(`[DEBUG] chat_history fetched: ${chatHistory.length} messages`);
      socket.emit("chat_history", chatHistory);

      const unseen = chatHistory.filter(m => m.receiver_id === user_id && m.status !== "read");
      const unseenIds = unseen.map(m => m.id);
      console.log("[DEBUG] unseen message IDs:", unseenIds);

      if (unseenIds.length) {
        await axios.post(`${LARAVEL_API_URL}/api/socket/messages/seen`, { message_ids: unseenIds });
        const updated = unseen.map(m => ({
          id: m.id,
          status: "read",
          sender_id: m.sender_id,
          receiver_id: m.receiver_id,
          message: m.message,
          is_group: false,
        }));

        socket.emit("status_update", updated);

        const uniqueSenders = [...new Set(unseen.map(m => m.sender_id))];
        for (const senderId of uniqueSenders) {
          const senderSocket = onlineUsers.get(senderId);
          if (senderSocket) senderSocket.emit("status_update", updated);
        }
      }

      setActiveChat(user_id, with_user_id);
    }

    // ----------------------- 2️⃣ Group chat -----------------------
   else if (group_id) {

  // ⭐ REALTIME ENABLED
  socket.join(`group_${group_id}`);

  const res = await axios.get(`${LARAVEL_API_URL}/api/groups/history/${group_id}/${user_id}`);
  let messages = res.data.data || [];
  messages = Array.isArray(messages) ? messages : Object.values(messages);

  socket.emit("chat_history", messages);
  console.log("MMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMMM", messages);

  const unreadMessages = messages.filter(m =>
    m.group_id === group_id && m.status !== 'read'
  );

  const unreadIds = unreadMessages.map(m => m.id);

  if (unreadIds.length > 0) {

    const reso = await axios.post(`${LARAVEL_API_URL}/api/groups/message/seen`, {
      receiver_id: user_id,
      group_id,
      message_ids: unreadIds,
    });

    const status = 'read';
    const sentGroupData = reso.data?.data || {};

    const updated = unreadMessages.map(m => ({
      id: m.id,
      status,
      group_id,
      sender_id: m.sender_id,
      receiver_id: user_id,
      message: m.message,
      is_group: true,
      receiver: sentGroupData.receiver ?? 0
    }));

    // ❌ DO NOT SEND STATUS UPDATE TO SELF (YOUR OWN SENT MESSAGES)
    const hasMessagesFromOthers = unreadMessages.some(m => m.sender_id !== user_id);
    if (hasMessagesFromOthers) {
      socket.emit("status_update", updated);
    }

    // SEND ONLY TO OTHER SENDERS (NOT SELF)
    const uniqueSenderIds = [...new Set(unreadMessages.map(m => m.sender_id))];

    for (const senderId of uniqueSenderIds) {
      if (senderId === user_id) continue; // ❌ skip self

      const senderSocket = onlineUsers.get(senderId);
      if (senderSocket) senderSocket.emit("status_update", updated);
    }
  }

  if (!activeGroupChats.has(user_id)) activeGroupChats.set(user_id, new Set());
  activeGroupChats.get(user_id).add(group_id);
}


  } catch (err) {
    console.error("[ERROR] get_chat_history error:", err.message);
    socket.emit("error", { message: "Failed to fetch chat history" });
  }
});



  // ------------------ SEND MESSAGE ------------------
socket.on("send_message", async (rawData) => {
  try {
    console.log("[DEBUG] send_message called with:", rawData);

    const data = typeof rawData === "string" ? JSON.parse(rawData) : rawData;
    const { sender_id, receiver_id, message, message_type = "text", media_url, group_id } = data || {};

    if (!sender_id || (!message && !media_url) || (!receiver_id && !group_id)) return;

    let saved;

    // ---------------------------------------------------------
    // 1️⃣ GROUP MESSAGE
    // ---------------------------------------------------------
  if (group_id) {
  // Save group message
  const res = await axios.post(`${LARAVEL_API_URL}/api/groups/message`, {
    group_id,
    sender_id,
    message,
    message_type,
    media_url,
  });

  saved = res.data?.data;
  if (!saved) return;

  // Get group members
  const membersRes = await axios.get(`${LARAVEL_API_URL}/api/groups/${group_id}/members`);
  let members = membersRes.data.data;

  // Remove sender from members list
  members = members.filter(m => m.user_id !== sender_id);

  // Update statuses for each member except sender
  const updatedMembers = await Promise.all(
    members.map(async (m) => {
      let status = "sent";

      if (activeGroupChats.has(m.user_id) && activeGroupChats.get(m.user_id).has(group_id)) {
        status = "read";
        await axios.post(`${LARAVEL_API_URL}/api/groups/message/seen`, {
          receiver_id: m.user_id,
          group_id,
          message_ids: [saved.id],
        });
      } else if (registeredUsers.has(m.user_id)) {
        status = "delivered";
        await axios.post(`${LARAVEL_API_URL}/api/groups/message/delivered`, {
          receiver_id: m.user_id,
          group_id,
          message_ids: [saved.id],
        });
      }

      const memberSocket = onlineUsers.get(m.user_id);
      if (memberSocket) {
        memberSocket.emit("receive_message", { ...saved, is_group: true });

        memberSocket.emit("status_update", [{
          id: saved.id,
          status,
          sender_id,
          receiver_id: m.user_id,
          is_group: true,
          group_id,
          receiver: m.user
        }]);
      }

      return { user_id: m.user_id, status };
    })
  );

  saved.members = updatedMembers;

  // ⭐⭐⭐ NOW SENDER ALSO GETS STATUS_UPDATE ⭐⭐⭐
const senderSocket = onlineUsers.get(sender_id);
if (senderSocket) {
  const senderStatusPayload = updatedMembers.map(m => ({
    id: saved.id,
    status: m.status,              // SAME STATUS
    sender_id,
    receiver_id: m.user_id,        // SAME RECEIVER ID
    is_group: true,
    group_id,
    receiver: members.find(x => x.user_id === m.user_id)?.user || null
  }));

  senderSocket.emit("status_update", senderStatusPayload);
}

  // Send back to sender
  socket.emit("message_sent", saved);
}

    // ---------------------------------------------------------
    // 2️⃣ 1-1 MESSAGE
    // ---------------------------------------------------------
    else {
      const payload = { sender_id, receiver_id, message_type, message, media_url };
      const res = await axios.post(`${LARAVEL_API_URL}/api/socket/messages`, payload);
      saved = res.data?.data;
      if (!saved) return;

      const receiverSocket = onlineUsers.get(receiver_id);
      const receiverActive = activeChats.get(receiver_id);

      if (receiverActive && receiverActive.has(sender_id)) {
        saved.status = "read";
        await axios.post(`${LARAVEL_API_URL}/api/socket/messages/seen`, {
          message_ids: [saved.id],
        });

      } else if (registeredUsers.has(receiver_id)) {
        saved.status = "delivered";
        await axios.post(`${LARAVEL_API_URL}/api/socket/messages/delivered`, {
          message_ids: [saved.id],
        });

      } else {
        saved.status = "sent";
      }

      const statusPayload = [{
        id: saved.id,
        status: saved.status,
        sender_id,
        receiver_id,
        is_group: false,
      }];

      socket.emit("status_update", statusPayload);
      if (receiverSocket) receiverSocket.emit("status_update", statusPayload);

      if (receiverSocket) receiverSocket.emit("receive_message", saved);

      socket.emit("message_sent", saved);
    }

    console.log("[DEBUG] send_message flow completed");

  } catch (err) {
    console.error("[ERROR] send_message error:", err.message);
    socket.emit("error", { message: "Failed to send message" });
  }
});



  // ------------------ CHAT CLOSE ------------------
  socket.on("chat_close", (rawData) => {
  const data = typeof rawData === "string" ? JSON.parse(rawData) : rawData;
  const { user_id, with_user_id, group_id } = data || {};

  // ----------------------- 1️⃣ 1:1 chat close -----------------------
  if (with_user_id) {
    closeChat(user_id, with_user_id);
    console.log(`[DEBUG] User ${user_id} closed chat with ${with_user_id}`);
  }

  // ----------------------- 2️⃣ Group chat close -----------------------
  else if (group_id) {
    if (!user_id) return; // receiver_id same as user_id
    if (activeGroupChats.has(user_id)) {
      activeGroupChats.get(user_id).delete(group_id);
      if (activeGroupChats.get(user_id).size === 0) activeGroupChats.delete(user_id);
      console.log(`[DEBUG] User ${user_id} closed group chat ${group_id}`);
    }
  }
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

    // 1️⃣ Create group
    const res = await axios.post(`${LARAVEL_API_URL}/api/groups/create`, {
      name, profile_image, created_by, description, members,
    });

    const newGroup = res.data.data;
    console.log("[DEBUG] Group created:", newGroup);

    // 2️⃣ Emit group_created to creator
    socket.emit("group_created", newGroup);

    // 3️⃣ Fetch updated inbox + group list (same as register)
    try {
      const inboxRes = await axios.get(`${LARAVEL_API_URL}/api/socket/messages/inbox/${created_by}`);
      const inboxData = inboxRes.data?.data?.map(i => ({ ...i, is_group: false })) || [];

      const groupRes = await axios.get(`${LARAVEL_API_URL}/api/groups/user/${created_by}`);
      const groupData = groupRes.data?.data?.map(g => ({ ...g, is_group: true })) || [];

      const combinedInbox = [...inboxData, ...groupData];

      // 4️⃣ Send updated inbox_list to creator
      socket.emit("inbox_list", combinedInbox);

      console.log("[DEBUG] Updated inbox_list emitted after group create");

    } catch (err) {
      console.log("[ERROR] Failed to refresh inbox after group create:", err.message);
    }

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
