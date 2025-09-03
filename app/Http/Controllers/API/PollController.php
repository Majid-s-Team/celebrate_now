<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Poll;
use App\Models\Event;
use App\Models\User;
use App\Models\PollCandidate;
use App\Models\PollVote;
use App\Models\EventMember;
use App\Models\PollOption;
use App\Models\PollMemberOption;

class PollController extends Controller
{
    public function vote(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'poll_id' => 'required|exists:polls,id',
            'candidate_id' => 'required',
        ]);

        $poll = Poll::with('candidates')->find($data['poll_id']);
        if (!$poll) {
            return $this->sendError('Poll not found', [], 404);
        }
        if ($poll->status !== 'active') {
            return $this->sendError('Poll is closed', [], 400);
        }

        // Member check
        $isMember = EventMember::where('event_id', $poll->event_id)
            ->where('user_id', $user->id)
            ->exists();
        if (!$isMember) {
            return $this->sendError('Only event members can vote', [], 403);
        }

        // Candidate IDs ko normalize karo
        $candidateIds = is_array($data['candidate_id'])
            ? $data['candidate_id']
            : [$data['candidate_id']];

        // Single vs multiple selection check
        if (!$poll->allow_multiple_selection && count($candidateIds) > 1) {
            return $this->sendError('This poll allows only one selection', [], 400);
        }

        $votes = [];
        foreach ($candidateIds as $cid) {
            // candidate valid hai ya nahi
            $isCandidate = PollCandidate::where('poll_id', $poll->id)
                ->where('candidate_id', $cid)
                ->exists();

            if (!$isCandidate) {
                return $this->sendError("Invalid candidate ID: {$cid}", [], 400);
            }

            // check agar pehle se vote diya hua hai
            $existingVote = PollVote::where('poll_id', $poll->id)
                ->where('voter_id', $user->id)
                ->where('candidate_id', $cid)
                ->first();

            if ($existingVote) {
                // toggle: agar vote already hai to delete kar do
                $existingVote->delete();
                $votes[] = ['candidate_id' => $cid, 'status' => 'removed'];
            } else {
                // naya vote create karo
                $votes[] = PollVote::create([
                    'poll_id' => $poll->id,
                    'voter_id' => $user->id,
                    'candidate_id' => $cid,
                ]);
            }
        }

        return $this->sendResponse('Vote(s) processed successfully', $votes, 200);
    }



    public function show(Request $request, $pollId = null)
    {
        $user = $request->user();

        if ($pollId) {
            $poll = Poll::with(['event', 'candidates.candidate', 'votes'])->find($pollId);
            if (!$poll) {
                return $this->sendError('Poll not found', [], 404);
            }

            $candidates = $poll->candidates->map(function ($c) use ($poll) {
                $count = $poll->votes()->where('candidate_id', $c->candidate_id)->count();
                return [
                    'candidate_id' => $c->candidate_id,
                    'name' => $c->candidate->first_name . ' ' . $c->candidate->last_name,
                    'profile_image' => $c->candidate->profile_image,
                    'votes' => $count,
                ];
            });

            return $this->sendResponse('Poll details fetched successfully', [
                'poll' => $poll,
                'candidates' => $candidates,
                'total_votes' => $poll->votes()->count(),
            ]);
        }

        $polls = Poll::with('event')
            ->where('created_by', $user->id)
            ->get();

        return $this->sendResponse('All polls fetched successfully', $polls);
    }

    public function eventPollResults($eventId)
    {
        $event = Event::with(['polls.candidates.candidate', 'polls.votes.voter'])->find($eventId);
        if (!$event) {
            return $this->sendError('Event not found', [], 404);
        }

        $results = $event->polls->map(function ($poll) {
            $candidates = $poll->candidates->map(function ($c) use ($poll) {
                $votes = $poll->votes->where('candidate_id', $c->candidate_id);

                return [
                    'candidate_id' => $c->candidate_id,
                    'name' => $c->candidate->first_name . ' ' . $c->candidate->last_name,
                    'email' => $c->candidate->email,
                    'profile_image' => $c->candidate->profile_image,
                    'votes_count' => $votes->count(),
                    'voters' => $votes->map(function ($v) {
                        return [
                            'id' => $v->voter->id,
                            'name' => $v->voter->first_name . ' ' . $v->voter->last_name,
                            'email' => $v->voter->email,
                            'profile_image' => $v->voter->profile_image,
                        ];
                    }),
                ];
            });

            // voters who didn’t vote
            $allCandidates = $poll->candidates->pluck('candidate_id');
            $votedUserIds = $poll->votes->pluck('voter_id');
            $notVoted = User::whereIn('id', $allCandidates)
                ->whereNotIn('id', $votedUserIds)
                ->get(['id', 'first_name', 'last_name', 'email', 'profile_image']);

            return [
                'poll_id' => $poll->id,
                'poll_date' => $poll->poll_date,
                'status' => $poll->status,
                'candidates' => $candidates,
                'not_voted' => $notVoted,
                'total_votes' => $poll->votes->count(),
            ];
        });

        return $this->sendResponse('Event poll results fetched successfully', [
            'event_id' => $event->id,
            'event_title' => $event->title,
            'polls' => $results,
        ]);
    }

    public function createPoll(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'event_id' => 'required|exists:events,id',
            'question' => 'required|string|max:255',
            'options' => 'required|array|min:1|max:6',
            'options.*' => 'required|string|max:100',
            'poll_date' => 'nullable|date',
            'allow_member_add_option' => 'boolean',
            'allow_multiple_selection' => 'boolean',
        ]);

        $lowercaseOptions = array_map('strtolower', $data['options']);
        if (count($lowercaseOptions) !== count(array_unique($lowercaseOptions))) {
            return $this->sendError('Duplicate options are not allowed in a poll');
        }

        $poll = Poll::create([
            'event_id' => $data['event_id'],
            'created_by' => $user->id,
            'question' => $data['question'],
            'poll_date' => $data['poll_date'] ?? null,
            'allow_member_add_option' => $data['allow_member_add_option'] ?? false,
            'allow_multiple_selection' => $data['allow_multiple_selection'] ?? false,
            'status' => 'active',
        ]);

        foreach ($data['options'] as $option) {
            $exists = PollOption::where('poll_id', $poll->id)
                ->whereRaw('LOWER(option_text) = ?', [strtolower($option)])
                ->exists();

            if ($exists) {
                continue;
            }

            PollOption::create([
                'poll_id' => $poll->id,
                'option_text' => $option,
                'added_by' => $user->id,
            ]);
        }

        return $this->sendResponse('Poll created successfully', $poll->load('options'));
    }
    public function updatePoll(Request $request, $pollId)
    {
        $user = $request->user();
        $poll = Poll::with('event')->findOrFail($pollId);


        $isCreator = $poll->created_by === $user->id;
        $isHostOrCohost = EventMember::where('event_id', $poll->event_id)
            ->where('user_id', $user->id)
            ->whereIn('role', ['host', 'cohost'])
            ->exists();

        if (!$isCreator && !$isHostOrCohost) {
            return $this->sendError('You are not authorized to update this poll', [], 403);
        }

        $data = $request->validate([
            'question' => 'sometimes|required|string|max:255',
            'options' => 'sometimes|array|min:1|max:6',
            'options.*' => 'required|string|max:100',
            'poll_date' => 'nullable|date',
            'allow_member_add_option' => 'boolean',
            'allow_multiple_selection' => 'boolean',
            'status' => 'in:active,closed'
        ]);

        $poll->update([
            'question' => $data['question'] ?? $poll->question,
            'poll_date' => $data['poll_date'] ?? $poll->poll_date,
            'allow_member_add_option' => $data['allow_member_add_option'] ?? $poll->allow_member_add_option,
            'allow_multiple_selection' => $data['allow_multiple_selection'] ?? $poll->allow_multiple_selection,
            'status' => $data['status'] ?? $poll->status,
        ]);

        if (isset($data['options'])) {
            $lowercaseOptions = array_map('strtolower', $data['options']);
            if (count($lowercaseOptions) !== count(array_unique($lowercaseOptions))) {
                return $this->sendError('Duplicate options are not allowed in a poll');
            }

            $poll->options()->delete();

            foreach ($data['options'] as $option) {
                PollOption::create([
                    'poll_id' => $poll->id,
                    'option_text' => $option,
                    'added_by' => $user->id,
                ]);
            }
        }

        return $this->sendResponse('Poll updated successfully', $poll->load('options'));
    }


    public function deletePoll(Request $request, $pollId)
    {
        $user = $request->user();
        $poll = Poll::with('event')->findOrFail($pollId);

        $isCreator = $poll->created_by === $user->id;
        $isHostOrCohost = EventMember::where('event_id', $poll->event_id)
            ->where('user_id', $user->id)
            ->whereIn('role', ['host', 'cohost'])
            ->exists();

        if (!$isCreator && !$isHostOrCohost) {
            return $this->sendError('You are not authorized to delete this poll', [], 403);
        }

        $poll->delete();

        return $this->sendResponse('Poll deleted successfully');
    }



    public function addOption(Request $request, $pollId)
    {
        $user = $request->user();

        $poll = Poll::findOrFail($pollId);

        if (!$poll->allow_member_add_option) {
            return $this->sendError('Members are not allowed to add options');
        }

        $count = PollOption::where('poll_id', $pollId)->count();
        if ($count >= 6) {
            return $this->sendError('Maximum 6 options allowed');
        }

        $data = $request->validate([
            'option_text' => 'required|string|max:100'
        ]);

        $exists = PollOption::where('poll_id', $pollId)
            ->whereRaw('LOWER(option_text) = ?', [strtolower($data['option_text'])])
            ->exists();

        if ($exists) {
            return $this->sendError('This option already exists in the poll');
        }

        $option = PollOption::create([
            'poll_id' => $poll->id,
            'option_text' => $data['option_text'],
            'added_by' => $user->id,
        ]);

        PollMemberOption::create([
            'poll_id' => $poll->id,
            'user_id' => $user->id,
            'poll_option_id' => $option->id,
        ]);

        return $this->sendResponse('Option added successfully', $option);
    }


}
