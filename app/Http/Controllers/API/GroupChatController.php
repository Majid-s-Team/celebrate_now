<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Group, GroupMember, GroupMessage,GroupMessageStatus, User};
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

    // Check sender is a group member
    $isMember = GroupMember::where('group_id', $request->group_id)
        ->where('user_id', $request->sender_id)
        ->exists();

    if (!$isMember) {
        return $this->apiResponse('User is not a group member', null, 403);
    }

    // Send message
    $msg = GroupMessage::create($request->all());
    $msg->load(['sender:id,first_name,last_name,profile_image']);

    // Create status rows for all other group members
    $members = GroupMember::where('group_id', $request->group_id)->get();

    foreach ($members as $member) {
        if ($member->user_id != $request->sender_id) {
            GroupMessageStatus::create([
                'group_id' => $request->group_id,
                'sender_id' => $request->sender_id,
                'receiver_id' => $member->user_id,
                'message_id' => $msg->id,
                'is_read' => false,
            ]);
        }
    }

    return $this->apiResponse('Message sent', $msg);
}

    /**
     * Fetch group chat history
     */
    public function history($groupId, $receiver_id)
{
    $member = GroupMember::where('group_id', $groupId)
        ->where('user_id', $receiver_id)
        ->first();

    if (!$member) {
        return $this->apiResponse('User is not a group member', null, 403);
    }

    $query = GroupMessage::with('sender:id,first_name,last_name,profile_image')
        ->where('group_id', $groupId);

    if (!$member->can_see_past_messages) {
        $query->where('created_at', '>=', $member->created_at);
    }

    $messages = $query->orderBy('created_at')->get();

    // DB se is_read leke attach karna
    $messages->transform(function ($msg) use ($receiver_id) {
        $status = GroupMessageStatus::where('group_id', $msg->group_id)
            ->where('message_id', $msg->id)
            ->where('receiver_id', $receiver_id)
            ->first();

        // sirf DB me jo value hai wahi use karo
        $msg->is_read = $status ? $status->is_read : false;
        return $msg;
    });

    return $this->apiResponse('Chat loaded', $messages);
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
            'lastMessage.sender:id,first_name,last_name,profile_image',
            'creator:id,first_name,last_name,profile_image'
        ])
        ->get()
        ->map(function ($group) {
            $lastMessage = $group->lastMessage;

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
                    ->update(['is_read' => true]);

        return $this->apiResponse('Group messages marked as read', [
            'group_id' => $groupId,
            'receiver_id' => $userId,
            'updated_rows' => $updated
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
