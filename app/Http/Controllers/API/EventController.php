<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Event;
use App\Models\EventMember;
use App\Models\Poll;
use App\Models\Post;
use App\Models\PollCandidate;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    // Create event
    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'location' => 'nullable|string|max:1000',
            'description' => 'nullable|string',
            'cover_photo_url' => 'nullable|url',
            'event_type_id' => 'nullable|exists:event_categories,id',
            'mode' => ['required', Rule::in(['online', 'physical'])],
            'physical_type' => ['nullable', Rule::in(['self_host', 'group_vote'])],
            'funding_type' => ['nullable', Rule::in(['self_financed', 'donation_based'])],
            'surprise_contribution' => 'nullable|boolean',
            'member_ids' => 'nullable|array',
            'member_ids.*' => 'exists:users,id',
            'cohost_ids' => 'nullable|array',
            'cohost_ids.*' => 'exists:users,id',
            'poll_date' => 'nullable|date',
            'donation_goal' => 'required_if:funding_type,donation_based|numeric|min:1',
            'is_show_donation' => 'required_if:funding_type,donation_based|boolean',
            'donation_deadline' => 'nullable|date|after:date',

        ]);
        if (($data['funding_type'] ?? null) === 'donation_based' && !empty($data['surprise_contribution']) && $data['surprise_contribution'] == true) {
            return $this->sendError("Surprise contribution is not allowed for donation based events.", [], 422);
        }

        if (($data['funding_type'] ?? null) === 'donation_based') {
            $data['surprise_contribution'] = false;
        }

        DB::beginTransaction();
        try {
            $event = Event::create([
                'title' => $data['title'],
                'date' => $data['date'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'location' => $data['location'] ?? null,
                'description' => $data['description'] ?? null,
                'cover_photo_url' => $data['cover_photo_url'] ?? null,
                'event_type_id' => $data['event_type_id'] ?? null,
                'mode' => $data['mode'],
                'physical_type' => $data['physical_type'] ?? null,
                'funding_type' => $data['funding_type'] ?? null,
                'surprise_contribution' => $data['surprise_contribution'] ?? false,
                'created_by' => $user->id,
                'donation_goal' => $data['donation_goal'] ?? null,
                'is_show_donation' => $data['is_show_donation'] ?? false,
                'donation_deadline' => $data['donation_deadline'] ?? null,

            ]);


            EventMember::create([
                'event_id' => $event->id,
                'user_id' => $user->id,
                'role' => 'host',
                'status' => 'joined',
            ]);


            if (!empty($data['cohost_ids'])) {
                foreach ($data['cohost_ids'] as $coId) {
                    if ($coId == $user->id)
                        continue;
                    EventMember::create([
                        'event_id' => $event->id,
                        'user_id' => $coId,
                        'role' => 'cohost',
                        'status' => 'joined',
                    ]);
                }
            }


            $members = $data['member_ids'] ?? [];
            foreach ($members as $memberId) {
                if ($memberId == $user->id)
                    continue;
                EventMember::firstOrCreate([
                    'event_id' => $event->id,
                    'user_id' => $memberId
                ], [
                    'role' => 'member',
                    'status' => 'joined',
                ]);
            }


            if ($event->mode == 'physical' && $event->physical_type == 'group_vote') {
                $poll = Poll::create([
                    'event_id' => $event->id,
                    'created_by' => $user->id,
                    'status' => 'active',
                    'question' => 'Who should host the event?',
                    'poll_date' => $data['poll_date'] ?? $event->date,
                ]);

                $memberRecords = EventMember::where('event_id', $event->id)->get();
                foreach ($memberRecords as $m) {
                    PollCandidate::create([
                        'poll_id' => $poll->id,
                        'candidate_id' => $m->user_id,
                    ]);
                }
            }

            DB::commit();
            return $this->sendResponse("Event created successfully", $event->load('members', 'category', 'creator'), 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError("Event creation failed", $e->getMessage(), 500);
        }
    }

    // List all events
    public function index()
    {
        $events = Event::with([
            'creator:id,first_name,last_name,email,profile_image',
            'category',
            'members.user:id,first_name,last_name,profile_image',
            'polls.candidates.candidate:id,first_name,last_name,profile_image',
            'polls.votes'
        ])->get();

        return $this->sendResponse("Events fetched successfully", $events);
    }

    // Show single event
    public function show($id)
    {
        $event = Event::with([
            'creator:id,first_name,last_name,email,profile_image',
            'category',
            'members.user:id,first_name,last_name,profile_image',
            'polls.candidates.candidate:id,first_name,last_name,profile_image',
            'polls.votes'
        ])->find($id);

        if (!$event) {
            return $this->sendError("Event not found", [], 404);
        }

        return $this->sendResponse("Event fetched successfully", $event);
    }

    // Update event
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $event = Event::find($id);
        if (!$event)
            return $this->sendError("Event not found", [], 404);

        if ($event->created_by != $user->id) {
            return $this->sendError("Unauthorized", [], 403);
        }

        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'date' => 'nullable|date',
            'start_time' => 'nullable',
            'end_time' => 'nullable',
            'location' => 'nullable|string|max:1000',
            'description' => 'nullable|string',
            'cover_photo_url' => 'nullable|url',
            'event_type_id' => 'nullable|exists:event_categories,id',
            'mode' => [Rule::in(['online', 'physical'])],
            'physical_type' => [Rule::in(['self_host', 'group_vote'])],
            'funding_type' => [Rule::in(['self_financed', 'donation_based'])],
            'surprise_contribution' => 'nullable|boolean',
            'member_ids' => 'nullable|array',
            'member_ids.*' => 'exists:users,id',
            'cohost_ids' => 'nullable|array',
            'cohost_ids.*' => 'exists:users,id',
            'poll_date' => 'nullable|date',
            'donation_goal' => 'required_if:funding_type,donation_based|numeric|min:1',
            'is_show_donation' => 'required_if:funding_type,donation_based|boolean',
            'donation_deadline' => 'nullable|date|after:date',
        ]);

        DB::beginTransaction();
        try {
            $event->update(array_filter($data, fn($value) => !is_null($value)));


            if ($event->funding_type === 'donation_based') {
                $event->donation_goal = $data['donation_goal'];
                $event->contribution_from_members = $data['contribution_from_members'];
                $event->donation_deadline = $data['donation_deadline'] ?? null;
                $event->save();
            } else {

                $event->donation_goal = null;
                $event->contribution_from_members = false;
                $event->donation_deadline = null;
                $event->save();
            }


            if (isset($data['cohost_ids'])) {
                EventMember::where('event_id', $event->id)->where('role', 'cohost')->delete();
                foreach ($data['cohost_ids'] as $coId) {
                    if ($coId == $event->created_by)
                        continue;
                    EventMember::updateOrCreate(
                        ['event_id' => $event->id, 'user_id' => $coId],
                        ['role' => 'cohost', 'status' => 'joined']
                    );
                }
            }


            if (isset($data['member_ids'])) {
                $incoming = collect($data['member_ids'])->filter(fn($id) => $id != $event->created_by)->values()->all();
                EventMember::where('event_id', $event->id)->where('role', 'member')->whereNotIn('user_id', $incoming)->delete();
                foreach ($incoming as $memberId) {
                    EventMember::updateOrCreate(
                        ['event_id' => $event->id, 'user_id' => $memberId],
                        ['role' => 'member', 'status' => 'joined']
                    );
                }
            }


            if ($event->mode === 'physical' && $event->physical_type === 'group_vote') {
                $poll = $event->polls()->first();
                if (!$poll) {
                    $poll = Poll::create([
                        'event_id' => $event->id,
                        'created_by' => $user->id,
                        'status' => 'active',
                        'poll_date' => $data['poll_date'] ?? $event->date,
                    ]);
                    $members = EventMember::where('event_id', $event->id)->get();
                    foreach ($members as $m) {
                        PollCandidate::create([
                            'poll_id' => $poll->id,
                            'candidate_id' => $m->user_id,
                        ]);
                    }
                } elseif (isset($data['poll_date'])) {
                    $poll->update(['poll_date' => $data['poll_date']]);
                }
            }


            if (
                $event->mode === 'online' ||
                ($event->mode === 'physical' && $event->physical_type === 'self_host')
            ) {
                $polls = $event->polls()->get();
                foreach ($polls as $poll) {
                    $poll->votes()->delete();
                    if (method_exists($poll, 'candidates')) {
                        $poll->candidates()->delete();
                    }
                    $poll->delete();
                }
            }

            DB::commit();
            return $this->sendResponse("Event updated successfully", $event->load('members', 'polls'));
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError("Update failed", $e->getMessage(), 500);
        }
    }


    public function groupMembersForVote($eventId)
    {
        $event = Event::with(['members.user'])->find($eventId);
        if (!$event)
            return $this->sendError("Event not found", [], 404);

        $membersExceptHost = $event->members->filter(fn($m) => $m->role !== 'host')->map(function ($m) {
            return [
                'user_id' => $m->user->id,
                'name' => $m->user->first_name . ' ' . $m->user->last_name,
                'role' => $m->role,
                'profile_image' => $m->user->profile_image ?? null,
            ];
        })->values();

        return $this->sendResponse("Members fetched successfully", $membersExceptHost);
    }
    public function destroy($id)
    {
        $event = Event::find($id);

        if (!$event) {
            return $this->sendError('Event not found', [], 404);
        }


        // if (auth()->id() !== $event->creator_id) {
        //     return $this->sendError('Unauthorized', [], 403);
        // }

        $event->delete();

        return $this->sendResponse([], 'Event deleted successfully');
    }

    public function eventPosts($eventId, Request $request)
    {
        $event = Event::findOrFail($eventId);


        $isMember = EventMember::where('event_id', $eventId)
            ->where('user_id', auth()->id())
            ->where('status', 'joined')
            ->exists();

        if (!$isMember) {
            return $this->sendError('You are not authorized to view posts of this event.', [], 403);
        }

        $perPage = $request->get('per_page', 10);

        $posts = Post::where('event_id', $eventId)
            ->with(['user', 'media', 'likes', 'comments.user', 'comments.replies.user'])
            ->latest()
            ->paginate($perPage);

        $posts->getCollection()->transform(function ($post) {
            $post->is_liked = $post->likes->contains('user_id', auth()->id());
            $post->comments->transform(function ($comment) {
                $comment->is_liked = $comment->likes->contains('user_id', auth()->id());
                return $comment;
            });
            return $post;
        });

        return $this->sendResponse('Event posts fetched successfully', $posts);
    }

public function getEventMembers($eventId)
{
    try {
        $event = Event::with(['members.user'])->findOrFail($eventId);

        // Filter out members who don't have a user
        $members = $event->members
            ->filter(function ($member) {
                return $member->user !== null;
            })
            ->map(function ($member) {
                return [
                    'id' => $member->id, // You may want to use $member->id here
                    'role' => $member->role,
                    'status' => $member->status,
                    'user' => [
                        'id' => $member->user->id,
                        'first_name' => $member->user->first_name,
                        'last_name' => $member->user->last_name,
                        'email' => $member->user->email,
                        'profile_image' => $member->user->profile_image,
                    ],
                ];
            })
            ->values(); // Re-index the collection (optional)

        return $this->sendResponse('Event members fetched successfully', [
            'event_id' => $event->id,
            'event_title' => $event->title,
            'members' => $members,
        ]);
    } catch (\Exception $e) {
        return $this->sendError('Failed to fetch event members', ['error' => $e->getMessage()], 500);
    }
}

// public function getUserEventPolls(Request $request)
// {
//     try {
//         $user = auth()->user();
//         $eventId = $request->input('event_id');

//         $eventsQuery = Event::whereHas('members', function ($q) use ($user) {
//             $q->where('user_id', $user->id);
//         })
//         ->with([
//             'creator',
//             'members.user',
//             'polls.creator',
//             'polls.options.addedBy',
//             'polls.candidates.candidate',
//             'polls.votes.voter',
//             'polls.votes.candidate',
//             'posts.user',
//             'posts.media',
//             'posts.category',
//             'posts.tags',
//             'posts.likes',
//             'posts.comments.replies',
//             'posts.comments.user',
//             'posts.comments.replies.user',
//         ]);

//         if ($eventId) {
//             $eventsQuery->where('id', $eventId);
//         }

//         $events = $eventsQuery->get();

//         return $this->sendResponse('User event polls & posts fetched successfully', $events);
//     } catch (\Exception $e) {
//         return $this->sendError('Failed to fetch user event polls', ['error' => $e->getMessage()], 500);
//     }
// }



public function getUserEventPolls(Request $request)
{
    try {
        $user = auth()->user();
        $eventId = $request->input('event_id');

        $eventsQuery = Event::whereHas('members', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })
        ->with([
            'creator',
            'members.user',
            'polls.creator',
            'polls.options.addedBy',
            'polls.candidates.candidate',
            'polls.votes.voter',
            'polls.votes.candidate',
            'posts.user',
            'posts.media',
            'posts.category',
            'posts.tags',
            'posts.likes',
            'posts.comments.replies',
            'posts.comments.user',
            'posts.comments.replies.user',
        ]);

        if ($eventId) {
            $eventsQuery->where('id', $eventId);
        }

        // Get events with all relationships
        $events = $eventsQuery->get();

        // ✅ Optional: filter out members with no user
        $events->each(function ($event) {
            $event->members = $event->members
                ->filter(fn($member) => $member->user !== null)
                ->values();
        });

        // ✅ Optional: Filter polls where creator is not null
        $events->each(function ($event) {
            $event->polls = $event->polls
                ->filter(fn($poll) => $poll->creator !== null)
                ->values();
        });

        // ✅ Optional: Filter posts where user exists
        $events->each(function ($event) {
            $event->posts = $event->posts
                ->filter(fn($post) => $post->user !== null)
                ->each(function ($post) {
                    $post->comments = $post->comments
                        ->filter(fn($comment) => $comment->user !== null)
                        ->each(function ($comment) {
                            $comment->replies = $comment->replies
                                ->filter(fn($reply) => $reply->user !== null)
                                ->values();
                        })
                        ->values();
                })
                ->values();
        });

        return $this->sendResponse('User event polls & posts fetched successfully', $events);
    } catch (\Exception $e) {
        return $this->sendError('Failed to fetch user event polls', ['error' => $e->getMessage()], 500);
    }
}
}

