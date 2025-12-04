<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Group, GroupMember, GroupMessage,GroupMessageStatus,GroupMembership, User,UserBlock};
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

    \DB::beginTransaction();
    try {
        // 1️⃣ Create group
        $group = Group::create([
            'name' => $request->name,
            'profile_image' => $request->profile_image,
            'description' => $request->description,
            'created_by' => $request->created_by,
        ]);

        // 2️⃣ Add creator as membership (role: admin)
        $creatorMembership = GroupMembership::create([
            'group_id' => $group->id,
            'user_id' => $request->created_by,
            'role' => 'admin',
            'can_see_past_messages' => true, // creator always sees past messages
        ]);

        // Sync to group_members table
        GroupMember::updateOrCreate(
            [
                'group_id' => $group->id,
                'user_id' => $request->created_by,
            ],
            [
                'current_membership_id' => $creatorMembership->id,
                'is_active' => true,
            ]
        );

        // 3️⃣ Add provided members (role: member)
        if ($request->has('members')) {
            foreach ($request->members as $member) {
                $memberId = $member['id'];
                if ($memberId == $request->created_by) continue;

                // Determine can_see_past_messages
                $canSeePast = $member['can_see_past_messages'] ?? true;

                // Create membership
                $membership = GroupMembership::create([
                    'group_id' => $group->id,
                    'user_id' => $memberId,
                    'role' => 'member',
                    'can_see_past_messages' => $canSeePast,
                ]);

                // Sync to group_members table
                GroupMember::updateOrCreate(
                    [
                        'group_id' => $group->id,
                        'user_id' => $memberId,
                    ],
                    [
                        'current_membership_id' => $membership->id,
                        'is_active' => true,
                         'can_see_past_messages' => $canSeePast, // ✅ important
                    ]
                );
            }
        }

        // 4️⃣ Fetch members with user details
        $members = GroupMember::where('group_id', $group->id)
            ->with('user:id,first_name,last_name,profile_image')
            ->get();

        \DB::commit();

        return $this->apiResponse('Group created successfully', [
            'group' => $group,
            'members' => $members
        ]);
    } catch (\Exception $e) {
        \DB::rollBack();
        return $this->apiResponse('Group creation failed', $e->getMessage(), 500);
    }
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

    // Only group creator can add
    if ($group->created_by != $request->added_by) {
        return $this->apiResponse('Only the group creator can add members', null, 403);
    }

    \DB::beginTransaction();
    try {

        $addedMembers = [];

        foreach ($request->members as $memberData) {

            $memberId = $memberData['id'];
            $canSeePast = $memberData['can_see_past_messages'] ?? true;

            // Fetch current group_members record
            $gm = GroupMember::where('group_id', $groupId)
                ->where('user_id', $memberId)
                ->first();

            // ⭐ CASE 1: User is already active → skip
            if ($gm && $gm->is_active) {
                continue;
            }

            // ⭐ CASE 2: User left before → CREATE NEW membership (do NOT reuse old)
            if ($gm && !$gm->is_active) {

                // Create a new membership interval
                $newMembership = GroupMembership::create([
                    'group_id' => $groupId,
                    'user_id' => $memberId,
                    'role' => 'member',
                ]);

                // Update group_members only (no new row)
                $gm->current_membership_id = $newMembership->id;
                $gm->is_active = 1;
                $gm->can_see_past_messages = $canSeePast;
                $gm->save();

                $addedMembers[] = User::find($memberId)->first_name;
                continue;
            }

            // ⭐ CASE 3: New member
            $newMembership = GroupMembership::create([
                'group_id' => $groupId,
                'user_id' => $memberId,
                'role' => 'member',
            ]);

            GroupMember::updateOrCreate(
                [
                    'group_id' => $groupId,
                    'user_id' => $memberId,
                ],
                [
                    'current_membership_id' => $newMembership->id,
                    'is_active' => true,
                    'can_see_past_messages' => $canSeePast,
                ]
            );

            $addedMembers[] = User::find($memberId)->first_name;
        }

        // Create system message
        if (!empty($addedMembers)) {
            $messageText =
                $addedByUser->first_name . ' added ' . implode(', ', $addedMembers) . ' to the group.';

            GroupMessage::create([
                'group_id' => $groupId,
                'sender_id' => $request->added_by,
                'message' => $messageText,
                'message_type' => 'system'
            ]);
        }

        \DB::commit();

        return $this->apiResponse('Members added successfully', [
            'added_by' => $addedByUser,
            'added_members' => $addedMembers
        ]);

    } catch (\Exception $e) {
        \DB::rollBack();
        return $this->apiResponse('Failed to add members', $e->getMessage(), 500);
    }
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

    // Only group creator can remove
    if ($group->created_by != $request->removed_by) {
        return $this->apiResponse('Only the group creator can remove members', null, 403);
    }

    // Creator cannot be removed
    if ($request->user_id == $group->created_by) {
        return $this->apiResponse('Group creator cannot be removed', null, 403);
    }

    \DB::beginTransaction();
    try {
        // 1️⃣ Update group_members table
        $groupMember = GroupMember::where('group_id', $groupId)
            ->where('user_id', $request->user_id)
            ->first();

        if ($groupMember) {
            $groupMember->is_active = 0;
            $groupMember->save();
        }

        // 2️⃣ Update latest GroupMembership row
        $membership = GroupMembership::where('group_id', $groupId)
            ->where('user_id', $request->user_id)
            ->whereNull('left_at')
            ->latest('joined_at')
            ->first();

        if ($membership) {
            $membership->left_at = now();
            $membership->save();
        }

        // 3️⃣ System message
        GroupMessage::create([
            'group_id' => $groupId,
            'sender_id' => $request->removed_by,
            'message' => "{$removedUser->first_name} was removed by {$removedByUser->first_name}.",
            'message_type' => 'system'
        ]);

        \DB::commit();

        return $this->apiResponse('Member removed successfully', [
            'removed_by' => $removedByUser->first_name,
            'removed_user' => $removedUser->first_name
        ]);
    } catch (\Exception $e) {
        \DB::rollBack();
        return $this->apiResponse('Failed to remove member', $e->getMessage(), 500);
    }
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
        ->where('is_active',1)
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
        ->where('is_active',1)
        ->get();

    $memberStatusList = [];
    $statusesForResponse = []; // history-style statuses

    foreach ($members as $member) {

        $status = 'sent';

        $isBlockedEitherWay =
            UserBlock::isBlocked($member->user_id, $request->sender_id) ||
            UserBlock::isBlocked($request->sender_id, $member->user_id);

        // Create status record in DB
        $statusRecord = GroupMessageStatus::create([
            'group_id' => $request->group_id,
            'sender_id' => $request->sender_id,
            'receiver_id' => $member->user_id,
            'message_id' => $msg->id,
            'hidden_for_receiver' => $isBlockedEitherWay,
            'status' => $status,
        ]);

        // --- HISTORY FORMAT STATUSES (sender == receiver SKIP) ---
        if ($statusRecord->receiver_id != $statusRecord->sender_id) {

            $statusesForResponse[] = [
                'id' => $statusRecord->id,
                'group_id' => $statusRecord->group_id,
                'sender_id' => $statusRecord->sender_id,
                'receiver_id' => $statusRecord->receiver_id,
                'status' => $statusRecord->status,
                'hidden_for_receiver' => $statusRecord->hidden_for_receiver,
                'updated_at' => $statusRecord->updated_at,
                'receiver' => [
                    'id' => $member->user->id,
                    'first_name' => $member->user->first_name,
                    'last_name' => $member->user->last_name,
                    'profile_image' => $member->user->profile_image,
                ]
            ];
        }

        // --- OLD RESPONSE FOR MEMBERS (SENDER SKIP) ---
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

    // --- Final response ---
    $response = $msg->toArray();
    $response['members'] = $memberStatusList;
    $response['statuses'] = $statusesForResponse; // history-style statuses

    return $this->apiResponse('Message sent', $response);
}

   public function getMembers($groupId)
    {
        $members = GroupMember::where('group_id', $groupId)
            ->where('is_active',1)
            ->with('user:id,first_name,last_name,profile_image')
            ->get();

        return $this->apiResponse('Members fetched', $members);
    }


public function history($groupId, $receiver_id)
{
    // 1️⃣ Fetch all memberships for this user in the group, ordered by joined_at
    $memberships = GroupMembership::where('group_id', $groupId)
        ->where('user_id', $receiver_id)
        ->orderBy('joined_at', 'asc')
        ->get();

    if ($memberships->isEmpty()) {
        return $this->apiResponse('User is not a group member', null, 403);
    }

    // 2️⃣ Fetch group and creator
    $group = Group::select('id','created_by')->find($groupId);
    $creatorId = $group->created_by;

    // 3️⃣ Fetch messages in the group
    $messages = GroupMessage::with([
        'sender:id,first_name,last_name,profile_image',
        'statuses' => function($q) {
            $q->whereColumn('receiver_id', '!=', 'sender_id')
              ->orderBy('receiver_id');
        },
        'statuses.receiver:id,first_name,last_name,profile_image'
    ])->where('group_id', $groupId)
      ->orderBy('created_at')
      ->get();

    // 4️⃣ Filter messages based on membership intervals
    $messages = $messages->filter(function ($msg) use ($memberships) {
        foreach ($memberships as $m) {
            $start = $m->joined_at;
            $end = $m->left_at ?? now();

            if ($msg->created_at >= $start && $msg->created_at <= $end) {
                return true;
            }
        }
        return false;
    });

    // 5️⃣ Club status + remove sender=receiver statuses
    $messages->transform(function ($msg) {
        $statuses = $msg->statuses->filter(function($st) use ($msg) {
            return $st->receiver_id != $msg->sender_id;
        })->values();

        $msg->club_status =
            $statuses->contains('status', 'sent') ? 'sent' :
            ($statuses->contains('status', 'delivered') ? 'delivered' : 'read');

        $msg->statuses = $statuses;
        return $msg;
    });

    // 6️⃣ Personal status
    $messages->transform(function ($msg) use ($receiver_id, $creatorId) {
        if ($receiver_id == $creatorId) {
            $status = $msg->statuses->where('receiver_id', '!=', $creatorId)->first();
        } else {
            $status = $msg->statuses->where('receiver_id', $receiver_id)->first();
        }
        $msg->status = $status->status ?? 'sent';
        return $msg;
    });

    // 7️⃣ Global block info
    $is_block = UserBlock::where('blocker_id', $receiver_id)
        ->where('is_active', true)
        ->exists();

    // 8️⃣ Fetch current group member
    $gm = GroupMember::where('group_id', $groupId)
        ->where('user_id', $receiver_id)
        ->first();

    // 9️⃣ Per-message sender deleted + is_left
    $messages->transform(function ($msg) use ($is_block, $memberships, $gm) {
        $is_sender_deleted = User::withoutGlobalScopes()
            ->withTrashed()
            ->where('id', $msg->sender_id)
            ->whereNotNull('deleted_at')
            ->exists();

        // ✅ Determine if user had left at the time of this message
        if ($gm && $gm->is_active == 0) {
            $is_left = true;
        } else {
            $is_left = true;
            foreach ($memberships as $m) {
                $start = $m->joined_at;
                $end = $m->left_at ?? now();
                if ($msg->created_at >= $start && $msg->created_at <= $end) {
                    $is_left = false;
                    break;
                }
            }
        }

        $msg->is_block = $is_block;
        $msg->is_deleted = $is_sender_deleted;
        $msg->is_left = $is_left;

        return $msg;
    });

    return $this->apiResponse('Chat loaded', $messages->toArray());
}

// have to make the lower part according ot new db strucutre for membersship and group member.


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
        ->map(function ($group) use ($userId) {

            // 🔹 Get group member row
            $gm = GroupMember::where('group_id', $group->id)
                    ->where('user_id', $userId)
                    ->first();

            // 🔹 User left?
            $is_left = $gm && $gm->is_active == 0;

            // 🔹 Fetch all membership intervals
            $memberships = GroupMembership::where('group_id', $group->id)
                ->where('user_id', $userId)
                ->orderBy('joined_at', 'asc')
                ->get();

            // 🔹 Filter last message based on membership intervals
            $lastMessage = GroupMessage::where('group_id', $group->id)
                ->whereHas('statuses', function($q) use ($userId) {
                    $q->where('receiver_id', $userId)
                      ->where('hidden_for_receiver', 0);
                })
                ->orderBy('created_at', 'desc')
                ->get()
                ->filter(function($msg) use ($memberships) {
                    foreach ($memberships as $m) {
                        $start = $m->joined_at;
                        $end   = $m->left_at ?? now();
                        if ($msg->created_at >= $start && $msg->created_at <= $end) {
                            return true;
                        }
                    }
                    return false;
                })
                ->first();

            // 🔹 unread count excluding hidden
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
                        'profile_image' => $group->creator->profile_image,
                    ] : null,
                    'members_count' => $group->members->count(),
                    'is_left' => $is_left,   // ✅ ADDED
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
        })
        ->sortByDesc('created_at')
        ->values();

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

        $leavestatus = GroupMember::where('group_id',$groupId)
        ->where('user_id',$userId)
        ->first();

        if($leavestatus->is_active===0)
        { return $this->apiResponse('This user has left the group', [

        ]);}



        // Update all messages in that group for this user
        $updated = GroupMessageStatus::where('receiver_id', $userId)
                    ->where('group_id', $groupId)
                    ->update(['status' => 'read']);

        $updatedtime = GroupMessageStatus::where('receiver_id', $userId)
                    ->where('group_id', $groupId)
                    ->first();


    $receiver = User::select('id','first_name','last_name','profile_image')
                           ->find($userId);

        return $this->apiResponse('Group messages marked as read', [
            'group_id' => $groupId,
            'receiver_id' => $userId,
            'receiver' =>$receiver,
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
    $senderId = $groupRecord ? $groupRecord->sender_id :null;

    $leavestatus = GroupMember::where('group_id',$groupId)
        ->where('user_id',$userId)
        ->first();

        if($leavestatus->is_active===0)
        { return $this->apiResponse('This user has left the group', [

        ]);}


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

    $receiver = User::select('id','first_name','last_name','profile_image')
                           ->find($userId);

    return $this->apiResponse('Group messages marked as delivered', [
        'receiver_id' => $userId,
        'receiver' => $receiver,
        'sender_id' => $senderId,
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

    \DB::beginTransaction();
    try {

        // 1️⃣ Update group_members table (NO DELETE)
        $member->is_active = 0;
        $member->save();

        // 2️⃣ Update membership row → left_at = now()
        $membership = GroupMembership::where('group_id', $groupId)
            ->where('user_id', $request->user_id)
            ->whereNull('left_at')
            ->latest('joined_at')
            ->first();

        if ($membership) {
            $membership->left_at = now();
            $membership->save();
        }

        // Remaining members
        $remainingMembers = GroupMember::where('group_id', $groupId)
            ->where('is_active', 1)
            ->pluck('user_id')
            ->toArray();


        // 3️⃣ Creator leaving logic
        if ($group->created_by == $request->user_id) {

            if (count($remainingMembers) > 0) {

                // assign new admin → earliest joined active member
                $newCreatorId = GroupMember::where('group_id', $groupId)
                    ->where('is_active', 1)
                    ->orderBy('created_at', 'asc')
                    ->value('user_id');

                $group->update(['created_by' => $newCreatorId]);

                // system message
                GroupMessage::create([
                    'group_id' => $groupId,
                    'sender_id' => $newCreatorId,
                    'message' => "{$user->first_name} left the group. {$group->name}'s new admin is " . User::find($newCreatorId)->first_name . ".",
                    'message_type' => 'system'
                ]);

            } else {

                // No members left
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

        \DB::commit();

        return $this->apiResponse('You have left the group successfully', [
            'group_id' => $groupId,
            'left_user' => $user->only(['id', 'first_name', 'last_name']),
            'remaining_members' => count($remainingMembers)
        ]);

    } catch (\Exception $e) {

        \DB::rollBack();
        return $this->apiResponse('Failed to leave group', $e->getMessage(), 500);
    }
}



}
