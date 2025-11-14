<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Group, GroupMember, GroupMessage,GroupMessageStatus, User,UserBlock};
use App\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\Validator;

class GroupChatController extends Controller
{
    use ApiResponseTrait;

    /**
     * Create a new group (with optional members)
     */
    public function create(Request $request)
{
    $validator = Validator::make($request->all(), [
        'name' => 'required|string|max:255',
        'profile_image' => 'nullable|string|max:255',
        'description' => 'nullable|string|max:255',
        'created_by' => 'required|exists:users,id',
        'members' => 'nullable|array',
        'members.*.id' => 'required|integer|exists:users,id',
        'members.*.can_see_past_messages' => 'nullable|boolean',
    ]);

    if ($validator->fails()) {
        return $this->apiResponse('Validation failed', $validator->errors(), 422);
    }

    // Create group
    $group = Group::create([
        'name' => $request->name,
        'profile_image' => $request->profile_image,
        'description' => $request->description,
        'created_by' => $request->created_by,
    ]);

    // Add creator as member (always true)
    GroupMember::firstOrCreate([
        'group_id' => $group->id,
        'user_id' => $request->created_by,
    ], ['can_see_past_messages' => true]);

    // Add provided members with optional per-member settings
    if ($request->has('members')) {
        foreach ($request->members as $member) {
            if ($member['id'] == $request->created_by) continue;

            GroupMember::firstOrCreate(
                [
                    'group_id' => $group->id,
                    'user_id' => $member['id']
                ],
                [
                    'can_see_past_messages' => $member['can_see_past_messages'] ?? true
                ]
            );
        }
    }

    // Fetch members with user details
    $members = GroupMember::where('group_id', $group->id)
        ->with('user:id,first_name,last_name,profile_image')
        ->get();

    return $this->apiResponse('Group created successfully', [
        'group' => $group,
        'members' => $members
    ]);
}

/**
 *
 * Update an exisiting group ( name & description)
 *
 */


public function updateGroup(Request $request, $groupId)
{
    $validator = Validator::make($request->all(), [
        'name' => 'required|string|max:255',
        'description' => 'nullable|string|max:1000',
    ]);

    if ($validator->fails()) {
        return $this->apiResponse('Validation failed', $validator->errors(), 422);
    }

    $group = Group::find($groupId);

    if (!$group) {
        return $this->apiResponse('Group not found', null, 404);
    }

    // Update group name and description
    $group->update([
        'name' => $request->name,
        'description' => $request->description,
    ]);

    return $this->apiResponse('Group updated successfully', $group);
}


    /**
     * Add multiple members to an existing group
     */
   public function addMember(Request $request, $groupId)
{
    $validator = Validator::make($request->all(), [
        'added_by' => 'required|exists:users,id',
        'members' => 'required|array|min:1',
        'members.*.id' => 'required|exists:users,id',
        'members.*.can_see_past_messages' => 'nullable|boolean',
    ]);

    if ($validator->fails()) {
        return $this->apiResponse('Validation failed', $validator->errors(), 422);
    }

    $group = Group::findOrFail($groupId);
    $addedByUser = User::find($request->added_by);

    // Only group creator or admins can add members
    if ($group->created_by != $request->added_by) {
        return $this->apiResponse('Only the group creator can add members', null, 403);
    }

    $addedMembers = [];

    foreach ($request->members as $memberData) {
        $member = GroupMember::firstOrCreate(
            [
                'group_id' => $groupId,
                'user_id' => $memberData['id']
            ],
            [
                'can_see_past_messages' => $memberData['can_see_past_messages'] ?? true
            ]
        );

        $addedMembers[] = $member->user->first_name . ' ' . $member->user->last_name;
    }

    // Create a system message in group_messages
    $messageText = $addedByUser->first_name . ' added ' . implode(', ', $addedMembers) . ' to the group.';
    GroupMessage::create([
        'group_id' => $groupId,
        'sender_id' => $request->added_by,
        'message' => $messageText,
        'message_type' => 'system'
    ]);

    return $this->apiResponse('Members added successfully', [
        'added_by' => $addedByUser,
        'added_members' => $addedMembers
    ]);
}

  public function removeMember(Request $request, $groupId)
{
    $validator = Validator::make($request->all(), [
        'removed_by' => 'required|exists:users,id',
        'user_id' => 'required|exists:users,id',
    ]);

    if ($validator->fails()) {
        return $this->apiResponse('Validation failed', $validator->errors(), 422);
    }

    $group = Group::findOrFail($groupId);
    $removedByUser = User::find($request->removed_by);
    $removedUser = User::find($request->user_id);

    if ($group->created_by != $request->removed_by) {
        return $this->apiResponse('Only the group creator can remove members', null, 403);
    }

    if ($request->user_id == $group->created_by) {
        return $this->apiResponse('Group creator cannot be removed', null, 403);
    }

    GroupMember::where('group_id', $groupId)
        ->where('user_id', $request->user_id)
        ->delete();

    // System message for removal
    GroupMessage::create([
        'group_id' => $groupId,
        'sender_id' => $request->removed_by,
        'message' => "{$removedUser->first_name} was removed by {$removedByUser->first_name}.",
        'message_type' => 'system'
    ]);

    return $this->apiResponse('Member removed successfully', [
        'removed_by' => $removedByUser->first_name,
        'removed_user' => $removedUser->first_name
    ]);
}

    /**
     * Send message (only if user is a group member)
     */
 public function sendMessage(Request $request)
{
    $validator = Validator::make($request->all(), [
        'group_id' => 'required|exists:groups,id',
        'sender_id' => 'required|exists:users,id',
        'message' => 'nullable|string',
        'message_type' => 'nullable|string|in:text,image,video,file,emoji,link',
        'media_url' => 'nullable|string',
    ]);

    if ($validator->fails()) {
        return $this->apiResponse('Validation failed', $validator->errors(), 422);
    }

    // --- Check sender is a group member ---
    $isMember = GroupMember::where('group_id', $request->group_id)
        ->where('user_id', $request->sender_id)
        ->exists();

    if (!$isMember) {
        return $this->apiResponse('User is not a group member', null, 403);
    }

    // --- Create message ---




     $msg = GroupMessage::create($request->only([
        'group_id', 'sender_id', 'message', 'message_type', 'media_url'
    ]));

    $msg->load(['sender:id,first_name,last_name,profile_image']);

    // --- Fetch all group members ---
    $members = GroupMember::with('user:id,first_name,last_name,profile_image')
        ->where('group_id', $request->group_id)
        ->get();

    $memberStatusList = [];

    foreach ($members as $member) {
        $status = 'sent';

        $isBlockedEitherWay =
    UserBlock::isBlocked($member->user_id, $request->sender_id) ||
    UserBlock::isBlocked($request->sender_id, $member->user_id);


        // DB me sab ka status create karo (sender bhi)
        $statusRecord = GroupMessageStatus::create([
            'group_id' => $request->group_id,
            'sender_id' => $request->sender_id,
            'receiver_id' => $member->user_id,
            'message_id' => $msg->id,
            'hidden_for_receiver'=>$isBlockedEitherWay,
            'status' => $status,
        ]);

        // Response list me sender ko skip kar do
        if ($member->user_id == $request->sender_id) {
            continue;
        }

        $memberStatusList[] = [
            'user_id' => $member->user->id,
            'first_name' => $member->user->first_name,
            'last_name' => $member->user->last_name,
            'profile_image' => $member->user->profile_image,
            'updated_at' => $statusRecord->updated_at,
            'status' => $status
        ];
    }

    // --- Final response with members list ---
    $response = $msg->toArray();
    $response['members'] = $memberStatusList;

    return $this->apiResponse('Message sent', $response);
}


    /**
     * Fetch group chat history
     */
//     public function history($groupId, $receiver_id)
// {
//     $member = GroupMember::where('group_id', $groupId)
//         ->where('user_id', $receiver_id)
//         ->first();

//     if (!$member) {
//         return $this->apiResponse('User is not a group member', null, 403);
//     }

//     $query = GroupMessage::with('sender:id,first_name,last_name,profile_image')
//         ->where('group_id', $groupId);

//     $sender_id = $query->sender_id;

//     if (!$member->can_see_past_messages) {
//         $query->where('created_at', '>=', $member->created_at);
//     }

//     $messages = $query->orderBy('created_at')->get();

//     $messages->transform(function ($msg) use ($receiver_id) {
//         $status = GroupMessageStatus::where('group_id', $msg->group_id)
//             ->where('message_id', $msg->id)
//             ->where('receiver_id', $receiver_id)
//             ->first();




//         // sirf DB me jo value hai wahi use karo
// $msg->status = $status ? $status->status : 'sent';
//         return $msg;

//     });


//        $blocks = UserBlock::where(function ($q) use ($sender_id, $receiver_id) {
//             $q->where('blocker_id', $sender_id)->where('blocked_id', $receiver_id);
//         })
//         ->orWhere(function ($q) use ($receiver_id, $sender_id) {
//             $q->where('blocker_id', $sender_id)->where('blocked_id', $receiver_id);
//         })
//         ->orderBy('blocked_at', 'asc')
//         ->get();

//     // 🔹 Apply logic for visibility
//     $filtered = $messages->filter(function ($msg) use ($sender_id, $receiver_id, $blocks) {

//         // Case 1️: Message hidden for receiver
//         if ($msg->is_read && $msg->receiver_id == $receiver_id && $msg->sender_id == $sender_id) {
//             return false;
//         }

//         // Get last block record (if exists)
//         $activeBlock = $blocks->where('is_active', true)->first();
//         $lastBlock = $blocks->sortByDesc('blocked_at')->first();

//         // Case 2️: If current user is blocker (user1 blocked user2)
//         if ($activeBlock && $activeBlock->blocker_id == $sender_id) {
//             // Blocker cannot see any chat (even old ones) after block
//             if ($msg->sender_id == $sender_id && $msg->created_at > $activeBlock->blocked_at) {
//                 return false;  // Hide new messages from blocked user after blocking
//             }
//         }

//         // Case 3️: After unblock, hide all messages sent during block period
//         if ($lastBlock && $lastBlock->blocker_id == $sender_id) {
//             // hide messages from blocked user that were created during block period
//             if (
//                 $msg->sender_id == $lastBlock->blocked_id &&
//                 $msg->created_at > $lastBlock->blocked_at &&
//                 ($lastBlock->unblocked_at == null || $msg->created_at < $lastBlock->unblocked_at)
//             ) {
//                 return false;
//             }
//         }

//         // Otherwise visible
//         return true;
//     });

//     // 🔹 Current block status
//     $is_block = UserBlock::where('blocker_id', $sender_id)
//         ->where('blocked_id', $receiver_id)
//         ->where('is_active', true)
//         ->exists();

//     // 🔹 Check if user2 deleted
//     $is_deleted = User::withTrashed()
//         ->where('id', $receiver_id)
//         ->whereNotNull('deleted_at')
//         ->exists();

//     // 🔹 Add meta
//     $filtered->transform(function ($msg) use ($is_block, $is_deleted) {
//         $msg->is_block = $is_block;
//         $msg->is_deleted = $is_deleted;
//         return $msg;
//     });

//     return $this->apiResponse('Chat loaded', $messages);
// }




public function history($groupId, $receiver_id)
{
    $member = GroupMember::where('group_id', $groupId)
        ->where('user_id', $receiver_id)
        ->first();

    if (!$member) {
        return $this->apiResponse('User is not a group member', null, 403);
    }

    // 🔹 Get messages with sender & receiver status eager loaded
    $query = GroupMessage::with([
        'sender:id,first_name,last_name,profile_image',
        'statuses' => function($q) use ($receiver_id) {
            $q->where('receiver_id', $receiver_id);
        }
    ])->where('group_id', $groupId);

    if (!$member->can_see_past_messages) {
        $query->where('created_at', '>=', $member->created_at);
    }

    $messages = $query->orderBy('created_at')->get();

    // 🔹 Fetch block records between receiver and any sender
    $blocks = UserBlock::where(function ($q) use ($receiver_id) {
            $q->where('blocker_id', $receiver_id)
              ->orWhere('blocked_id', $receiver_id);
        })
        ->orderBy('blocked_at', 'asc')
        ->get();

    // 🔹 Filter messages based on block/unblock periods + permanent hidden_for_receiver
    $messages = $messages->filter(function ($msg) use ($receiver_id, $blocks) {

        // 🚫 Check if message was marked hidden_for_receiver in status table
        $statusCheck = $msg->statuses->first();

        if ($statusCheck && $statusCheck->hidden_for_receiver) {
            return false; // permanently hide, even after unblock
        }

        foreach ($blocks as $block) {
            if (!$block->is_active) continue; // skip inactive blocks for new messages

            $blockStart = $block->blocked_at;
            $blockEnd = $block->unblocked_at;

            // Case 1: Receiver blocked sender
            if ($block->blocker_id == $receiver_id && $block->blocked_id == $msg->sender_id) {
                if ($msg->created_at >= $blockStart && (!$blockEnd || $msg->created_at <= $blockEnd)) {
                    return false;
                }
            }

            // Case 2: Sender blocked receiver
            if ($block->blocker_id == $msg->sender_id && $block->blocked_id == $receiver_id) {
                if ($msg->created_at >= $blockStart && (!$blockEnd || $msg->created_at <= $blockEnd)) {
                    return false;
                }
            }
        }

        return true; // show message
    });

    // 🔹 Add message status
    $messages->transform(function ($msg) use ($receiver_id) {
        $status = $msg->statuses->first();
        $msg->status = $status ? $status->status : 'sent';
        return $msg;
    });

    // 🔹 Current block status
    $is_block = UserBlock::where('blocker_id', $receiver_id)
        ->where('is_active', true)
        ->exists();

    // 🔹 Check if user deleted
    $is_deleted = User::withTrashed()
        ->where('id', $receiver_id)
        ->whereNotNull('deleted_at')
        ->exists();

    // 🔹 Add meta
    $messages->transform(function ($msg) use ($is_block, $is_deleted) {
        $msg->is_block = $is_block;
        $msg->is_deleted = $is_deleted;
        return $msg;
    });

    // 🔹 Convert final collection to plain array
    $messagesArray = $messages->toArray();

    return $this->apiResponse('Chat loaded', $messagesArray);
}








    public function getMembers($groupId)
    {
        $members = GroupMember::where('group_id', $groupId)
            ->with('user:id,first_name,last_name,profile_image')
            ->get();

        return $this->apiResponse('Members fetched', $members);
    }


public function userGroups($userId)
{
    $groups = Group::whereHas('members', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
        ->with([
            'members.user:id,first_name,last_name,profile_image',
            'creator:id,first_name,last_name,profile_image'
        ])
        ->get()
        ->sortByDesc(function ($group) use ($userId) {
            // use latest visible message timestamp for sorting
            $lastVisible = $group->messages()
                ->whereHas('statuses', function($q) use ($userId) {
                    $q->where('receiver_id', $userId)
                      ->where('hidden_for_receiver', 0);
                })
                ->latest('created_at')
                ->first();
            return $lastVisible ? $lastVisible->created_at : $group->created_at;
        })
        ->values()
        ->map(function ($group) use ($userId) {

            // 🔹 latest visible message for inbox
            $lastMessage = $group->messages()
                ->whereHas('statuses', function($q) use ($userId) {
                    $q->where('receiver_id', $userId)
                      ->where('hidden_for_receiver', 0);
                })
                ->with('sender:id,first_name,last_name,profile_image')
                ->latest('created_at')
                ->first();

            // 🔹 unread count excluding hidden messages
            $unreadCount = GroupMessageStatus::where('receiver_id', $userId)
                ->where('status', '!=', 'read')
                ->where('hidden_for_receiver', 0)
                ->whereHas('message', function ($q) use ($group) {
                    $q->where('group_id', $group->id);
                })
                ->count();

            // 🔹 is_block
            $memberIds = $group->members->pluck('user_id')->filter(fn($id) => $id != $userId);
            $isBlock = UserBlock::where(function($q) use ($userId, $memberIds) {
                $q->where('blocker_id', $userId)->whereIn('blocked_id', $memberIds);
            })->orWhere(function($q) use ($userId, $memberIds) {
                $q->whereIn('blocker_id', $memberIds)->where('blocked_id', $userId);
            })->exists();

            return [
                'chat_with' => [
                    'id' => $group->id,
                    'group_name' => $group->name,
                    'description' => $group->description,
                    'group_image' => $group->profile_image,
                    'created_by' => $group->creator ? [
                        'id' => $group->creator->id,
                        'first_name' => $group->creator->first_name,
                        'last_name' => $group->creator->last_name,
                        'profile_image' => $group->creator->profile_image
                            ? asset('storage/' . $group->creator->profile_image)
                            : null,
                    ] : null,
                    'members_count' => $group->members->count(),
                ],

                'last_message' => $lastMessage?->message ?? '',
                'media_url' => $lastMessage?->media_url ?? '',
                'message_type' => $lastMessage?->message_type ?? 'text',
                'created_at' => $lastMessage
                    ? $lastMessage->created_at
                    : $group->created_at,

                'unread_count' => $unreadCount,
                'is_block' => $isBlock,
            ];
        });

    return $this->apiResponse('User groups fetched', $groups);
}





     public function markGroupAsRead(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'receiver_id'  => 'required|exists:users,id',
            'group_id' => 'required|exists:groups,id'
        ]);

        if ($validator->fails()) {
            return $this->apiResponse('Validation failed', $validator->errors(), 422);
        }

        $userId  = $request->receiver_id;
        $groupId = $request->group_id;

        // Update all messages in that group for this user
        $updated = GroupMessageStatus::where('receiver_id', $userId)
                    ->where('group_id', $groupId)
                    ->update(['status' => 'read']);

        $updatedtime = GroupMessageStatus::where('receiver_id', $userId)
                    ->where('group_id', $groupId)
                    ->first();

        return $this->apiResponse('Group messages marked as read', [
            'group_id' => $groupId,
            'receiver_id' => $userId,
            'updated_at' =>$updatedtime->updated_at,
            'updated_rows' => $updated
        ]);
    }


  public function markGroupAsDelivered(Request $request)
{
    $validator = Validator::make($request->all(), [
        'receiver_id' => 'required|exists:users,id',
    ]);

    if ($validator->fails()) {
        return $this->apiResponse('Validation failed', $validator->errors(), 422);
    }

    $userId = $request->receiver_id;

    // ✅ 1️⃣ Sirf un messages ko lo jinka status abhi "sent" hai
    $pendingMessages = GroupMessageStatus::where('receiver_id', $userId)
        ->where('status', 'sent')
        ->pluck('message_id')
        ->toArray();

    // 🚫 Agar koi pending message nahi mila to return karo
    if (empty($pendingMessages)) {
        return $this->apiResponse('No new messages to mark delivered', [
            'receiver_id' => $userId,
            'message_ids' => [],
            'updated_rows' => 0,
        ]);
    }

    // ✅ 2️⃣ Group ID nikal lo (first message se)
    $groupRecord = GroupMessageStatus::whereIn('message_id', $pendingMessages)->first();
    $groupId = $groupRecord ? $groupRecord->group_id : null;

    // ✅ 3️⃣ Update only "sent" → "delivered"
    $updatedRows = GroupMessageStatus::where('receiver_id', $userId)
        ->whereIn('message_id', $pendingMessages)
        ->update([
            'status' => 'delivered',
            'updated_at' => now(),
        ]);

    // ✅ 4️⃣ Latest updated time le lo
    $latest = GroupMessageStatus::where('receiver_id', $userId)
        ->where('group_id', $groupId)
        ->latest('updated_at')
        ->first();

    return $this->apiResponse('Group messages marked as delivered', [
        'receiver_id' => $userId,
        'group_id' => $groupId,
        'updated_at' => optional($latest)->updated_at,
        'message_ids' => $pendingMessages, // ✅ sirf wo jinke status change hue
        'updated_rows' => $updatedRows,
    ]);
}


  public function groupChatMedia($groupId)
{

    // Get all non-text media messages for this group
    $mediaMessages = GroupMessage::where('group_id', $groupId)
        ->where('message_type', '!=', 'text')
        ->where('message_type', '!=', 'system')
        ->orderBy('created_at', 'desc')
        ->get([
            'id',
            'group_id',
            'sender_id',
            'message_type',
            'media_url',
            'created_at'
        ]);

    return $this->apiResponse('Group media loaded successfully', $mediaMessages);
}
public function leaveGroup(Request $request, $groupId)
{
    $validator = Validator::make($request->all(), [
        'user_id' => 'required|exists:users,id',
    ]);

    if ($validator->fails()) {
        return $this->apiResponse('Validation failed', $validator->errors(), 422);
    }

    $group = Group::find($groupId);

    if (!$group) {
        return $this->apiResponse('Group not found', null, 404);
    }

    $user = User::find($request->user_id);

    // Check if user is a member
    $member = GroupMember::where('group_id', $groupId)
        ->where('user_id', $request->user_id)
        ->first();

    if (!$member) {
        return $this->apiResponse('User is not a member of this group', null, 403);
    }

    // Remove member
    $member->delete();

    // Check remaining members
    $remainingMembers = GroupMember::where('group_id', $groupId)->pluck('user_id')->toArray();

    // If creator left
    if ($group->created_by == $request->user_id) {
        if (count($remainingMembers) > 0) {
            // Assign new creator — the earliest joined member
            $newCreatorId = GroupMember::where('group_id', $groupId)
                ->orderBy('created_at', 'asc')
                ->value('user_id');

            $group->update(['created_by' => $newCreatorId]);

            // System message for creator change
            GroupMessage::create([
                'group_id' => $groupId,
                'sender_id' => $newCreatorId,
                'message' => "{$user->first_name} left the group. {$group->name}'s new admin is " . User::find($newCreatorId)->first_name . ".",
                'message_type' => 'system'
            ]);
        } else {
            // No members left — just system message
            GroupMessage::create([
                'group_id' => $groupId,
                'sender_id' => $request->user_id,
                'message' => "{$user->first_name} left the group. No members remaining.",
                'message_type' => 'system'
            ]);
        }
    } else {
        // Normal member leave message
        GroupMessage::create([
            'group_id' => $groupId,
            'sender_id' => $request->user_id,
            'message' => "{$user->first_name} left the group.",
            'message_type' => 'system'
        ]);
    }

    return $this->apiResponse('User left the group successfully', [
        'group_id' => $groupId,
        'left_user' => $user->only(['id', 'first_name', 'last_name']),
        'remaining_members' => count($remainingMembers)
    ]);
}


}
