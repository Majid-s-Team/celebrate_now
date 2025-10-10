<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Poll;
use App\Models\Event;
use App\Models\Notification;
use App\Models\User;
use App\Models\PollCandidate;
use App\Models\PollVote;
use App\Models\EventMember;
use App\Models\PollOption;
use App\Models\PollMemberOption;
use Illuminate\Validation\Rule;
use Carbon\Carbon;



class PollController extends Controller
{
// public function vote(Request $request)
// {
//     $user = $request->user();

//     // Poll find karo
//     $poll = Poll::with('candidates', 'event')->find($request->poll_id);

//     if (!$poll) {
//         return $this->sendError('Poll not found', [], 404);
//     }
//     if ($poll->status !== 'active') {
//         return $this->sendError('Poll is closed', [], 400);
//     }

//     // Event nikaal lo
//     $event = $poll->event;
//     $eventId = $event->id;

//     // Base rules
//     $rules = [
//         'poll_id' => 'required|exists:polls,id',
//     ];

//     $countPolls = Poll::where('event_id', $eventId)->count();
//     // dd(vars: $poll->auto_poll);
//     $isGroupVote = ($event->mode == 'physical' && $event->physical_type == 'group_vote' && $poll->auto_poll===1);

//     if ($isGroupVote) {
//         $rules['candidate_id'] = 'required';
//     } else {
//         $rules['poll_option_id'] = [
//             'required',
//             Rule::exists(PollOption::class, 'id')
//                 ->where('poll_id', $poll->id),
//         ];
//     }

//     $data = $request->validate($rules);

//     // Check member
//     $isMember = EventMember::where('event_id', $poll->event_id)
//         ->where('user_id', $user->id)
//         ->exists();

//     if (!$isMember) {
//         return $this->sendError('Only event members can vote', [], 403);
//     }

//     $votes = [];

//     if ($isGroupVote) {
//         // Candidate vote logic
//         $candidateIds = is_array($data['candidate_id'])
//             ? $data['candidate_id']
//             : [$data['candidate_id']];

//         if (!$poll->allow_multiple_selection && count($candidateIds) > 1) {
//             return $this->sendError('This poll allows only one selection', [], 400);
//         }

//         foreach ($candidateIds as $cid) {
//             $isCandidate = PollCandidate::where('poll_id', $poll->id)
//                 ->where('candidate_id', $cid)
//                 ->exists();

//             if (!$isCandidate) {
//                 return $this->sendError("Invalid candidate ID: {$cid}", [], 400);
//             }

//             $existingVote = PollVote::where('poll_id', $poll->id)
//                 ->where('voter_id', $user->id)
//                 ->where('candidate_id', $cid)
//                 ->first();

//             if ($existingVote) {
//                 $existingVote->delete();
//                 $votes[] = ['candidate_id' => $cid, 'status' => 'removed'];
//             } else {
//                 $votes[] = PollVote::create([
//                     'poll_id'      => $poll->id,
//                     'voter_id'     => $user->id,
//                     'candidate_id' => $cid,
//                 ]);
//             }
//         }
//     } else {
//         // Poll option vote logic
//         $existingVote = PollVote::where('poll_id', $poll->id)
//             ->where('voter_id', $user->id)
//             ->first();

//         if ($existingVote) {
//             $existingVote->delete();
//             $votes[] = ['poll_option_id' => $data['poll_option_id'], 'status' => 'removed'];
//         } else {
//             $votes[] = PollVote::create([
//                 'poll_id'        => $poll->id,
//                 'voter_id'       => $user->id,
//                 'poll_option_id' => $data['poll_option_id'],
//             ]);
//         }
//     }

//     return $this->sendResponse('Vote(s) processed successfully', $votes, 200);
// }

public function vote(Request $request)
{
    $user = $request->user();
    // Poll find karo
    $poll = Poll::with('candidates', 'event')->find($request->poll_id);
    $pollEndDate = $poll->poll_date;

    if (!$poll) {
        return $this->sendError('Poll not found', [], 404);
    }
  if (!empty($pollEndDate)) {
    $pollEndDate = Carbon::parse($pollEndDate)->startOfDay(); // 👈 string → Carbon
    $today = Carbon::today();
    // dd(vars:$today->greaterThan($pollEndDate));

    if ($today->greaterThan($pollEndDate)) {
        return $this->sendError("The Poll is closed", [], 422);
    }
}

    if ($poll->status !== 'active') {
        return $this->sendError('Poll is closed', [], 400);
    }



    // Event nikaal lo
    $event = $poll->event;
    $eventId = $event->id;

    // Base rules
    $rules = [
        'poll_id' => 'required|exists:polls,id',
    ];

    $isGroupVote = (
        $event->mode == 'physical' &&
        $event->physical_type == 'group_vote' &&
        $poll->auto_poll === 1
    );

    if ($isGroupVote) {
        $rules['candidate_id'] = 'required';
    } else {
        $rules['poll_option_id'] = [
            'required',
            Rule::exists(PollOption::class, 'id')
                ->where('poll_id', $poll->id),
        ];
    }

    $data = $request->validate($rules);

    // Check member
    $isMember = EventMember::where('event_id', $poll->event_id)
        ->where('user_id', $user->id)
        ->exists();

    if (!$isMember) {
        return $this->sendError('Only event members can vote', [], 403);
    }

    $votes = [];

    if ($isGroupVote) {
        // Single-selection candidate vote logic
        $cid = is_array($data['candidate_id']) ? $data['candidate_id'][0] : $data['candidate_id'];

        $isCandidate = PollCandidate::where('poll_id', $poll->id)
            ->where('candidate_id', $cid)
            ->exists();

        if (!$isCandidate) {
            return $this->sendError("Invalid candidate ID: {$cid}", [], 400);
        }

        // Remove previous vote if exists
        $existingVote = PollVote::where('poll_id', $poll->id)
            ->where('voter_id', $user->id)
            ->first();

        if ($existingVote) {
            if ($existingVote->candidate_id != $cid) {
                $votes[] = ['candidate_id' => $existingVote->candidate_id, 'status' => 'removed'];
                $existingVote->delete();
            } else {
                // Same candidate → toggle remove
                $existingVote->delete();
                $votes[] = ['candidate_id' => $cid, 'status' => 'removed'];
                return $this->sendResponse('Vote(s) processed successfully', $votes, 200);
            }
        }

        // Insert new vote
        $votes[] = PollVote::create([
            'poll_id'      => $poll->id,
            'voter_id'     => $user->id,
            'candidate_id' => $cid,
        ]);

    } else {
        // Poll option vote logic (unchanged)
        $pollOptionIds = is_array($data['poll_option_id'])
            ? $data['poll_option_id']
            : [$data['poll_option_id']];

        if (!$poll->allow_multiple_selection && count($pollOptionIds) > 1) {
            return $this->sendError('This poll allows only one selection', [], 400);
        }
        if ($poll->allow_multiple_selection) {
            // Multiple select true → handle each option toggle
            foreach ($pollOptionIds as $optionId) {
                $existingVote = PollVote::where('poll_id', $poll->id)
                    ->where('voter_id', $user->id)
                    ->where('poll_option_id', $optionId)
                    ->first();

                if ($existingVote) {
                    $existingVote->delete();
                    $votes[] = ['poll_option_id' => $optionId, 'status' => 'removed'];
                } else {
                    $votes[] = PollVote::create([
                        'poll_id'        => $poll->id,
                        'voter_id'       => $user->id,
                        'poll_option_id' => $optionId,
                    ]);
                }
            }
        } else {
            // Single select → remove old vote if exists, then insert new
            $newOptionId = $pollOptionIds[0];

            $existingVote = PollVote::where('poll_id', $poll->id)
                ->where('voter_id', $user->id)
                ->first();

            if ($existingVote) {
                if ($existingVote->poll_option_id != $newOptionId) {
                    // Replace vote (delete old, insert new)
                    $existingVote->delete();
                    $votes[] = ['poll_option_id' => $existingVote->poll_option_id, 'status' => 'removed'];

                    $votes[] = PollVote::create([
                        'poll_id'        => $poll->id,
                        'voter_id'       => $user->id,
                        'poll_option_id' => $newOptionId,
                    ]);
                } else {
                    // Same option → toggle remove
                    $existingVote->delete();
                    $votes[] = ['poll_option_id' => $newOptionId, 'status' => 'removed'];
                }
            } else {
                // First time vote
                $votes[] = PollVote::create([
                    'poll_id'        => $poll->id,
                    'voter_id'       => $user->id,
                    'poll_option_id' => $newOptionId,
                ]);
            }
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

// public function eventPollResults($eventId)

// {
//     $event = Event::with([
//         'polls.candidates.candidate',
//         'polls.votes.voter',
//         'polls.votes.option',
//         'polls.options',
//     ])->find($eventId);

//     if (!$event) {
//         return $this->sendError('Event not found', [], 404);
//     }

//     $results = $event->polls->map(function ($poll) {
//         // check karo poll me candidates hain ya sirf options
//         if ($poll->candidates->count() > 0) {
//             // candidate-based poll
//             $votes = $poll->candidates
//                 ->filter(fn($c) => $c->candidate !== null)
//                 ->map(function ($c) use ($poll) {
//                     $candidateVotes = $poll->votes
//                         ->where('candidate_id', $c->candidate_id)
//                         ->filter(fn($v) => $v->voter !== null);

//                     $totalCandidateVotes = $candidateVotes->count(); // ✅ ek hi jagah count

//                     return [
//                         'candidate_id'   => $c->candidate_id,
//                         'name'           => $c->candidate->first_name . ' ' . $c->candidate->last_name,
//                         'email'          => $c->candidate->email,
//                         'profile_image'  => $c->candidate->profile_image,
//                         'votes_count'    => $totalCandidateVotes,
//                         'voters'         => $candidateVotes->map(fn($vote) => [
//                             'voter_id'      => $vote->voter_id,
//                             'name'          => $vote->voter->first_name . ' ' . $vote->voter->last_name,
//                             'email'         => $vote->voter->email,
//                             'profile_image' => $vote->voter->profile_image,
//                         ])->values(),
//                     ];
//                 })
//                 ->values();
//         } else {
//             // option-based poll
//             $votes = $poll->options->map(function ($opt) use ($poll) {
//                 $optionVotes = $poll->votes
//                     ->where('poll_option_id', $opt->id)
//                     ->filter(fn($v) => $v->voter !== null);

//                 return [
//                     'poll_option_id' => $opt->id,
//                     'option_text'    => $opt->option_text,
//                     'votes_count'    => $optionVotes->count(),
//                     'voters'         => $optionVotes->map(fn($v) => [
//                         'voter_id'      => $v->voter_id,
//                         'name'          => $v->voter->first_name . ' ' . $v->voter->last_name,
//                         'email'         => $v->voter->email,
//                         'profile_image' => $v->voter->profile_image,
//                     ])->values(),
//                 ];
//             });
//         }

//         // poll ke saare options nikal lo
//         $options = $poll->options->map(function ($opt) {
//             return [
//                 'poll_option_id' => $opt->id,
//                 'option_text'    => $opt->option_text,
//                 'added_by'       => $opt->added_by,
//             ];
//         });

//         return [
//             'poll_id'       => $poll->id,
//             'poll_caption'  => $poll->question,
//             'created_by'    => $poll->created_by,
//             'poll_end_date' => $poll->poll_date,
//             'created_at'    => $poll->created_at?->toDateString(),
//             'allow_member_add_option'   => (bool) $poll->allow_member_add_option,
//             'allow_multiple_selection'  => (bool) $poll->allow_multiple_selection,
//             'status'        => $poll->status,
//             'votes'         => $poll->votes->count() > 0 ? $votes : null,
//             'options'       => $options,
//             'total_votes'   => $poll->votes->count(),
//         ];
//     });

//     return $this->sendResponse('Event poll results fetched successfully', [
//         'event_id'    => $event->id,
//         'event_title' => $event->title,
//         'polls'       => $results,
//     ]);
// }







//fucntion to get poll results, with options in automated poll as well.

public function eventPollResults($eventId)
{
    $event = Event::with([
        'polls.candidates.candidate',
        'polls.votes.voter',
        'polls.votes.option',
        'polls.options',
    ])->find($eventId);

    if (!$event) {
        return $this->sendError('Event not found', [], 404);
    }

    $results = $event->polls->map(function ($poll) {
        if ($poll->candidates->count() > 0) {
            // candidate-based poll
            $votes = $poll->candidates
                ->filter(fn($c) => $c->candidate !== null)
                ->map(function ($c) use ($poll) {
                    $candidateVotes = $poll->votes
                        ->where('candidate_id', $c->candidate_id)
                        ->filter(fn($v) => $v->voter !== null);

                    return [
                        'candidate_id'   => $c->candidate_id,
                        'name'           => $c->candidate->first_name . ' ' . $c->candidate->last_name,
                        'email'          => $c->candidate->email,
                        'profile_image'  => $c->candidate->profile_image,
                        'votes_count'    => $candidateVotes->count(),
                        'voters'         => $candidateVotes->map(fn($vote) => [
                            'voter_id'      => $vote->voter_id,
                            'name'          => $vote->voter->first_name . ' ' . $vote->voter->last_name,
                            'email'         => $vote->voter->email,
                            'profile_image' => $vote->voter->profile_image,
                        ])->values(),
                    ];
                })
                ->values();

            // options key now contains candidate_id and candidate_name
            $options = $poll->candidates->map(function ($c) {
                return [
                    'poll_candidate_id' => $c->id,
                    'candidate_id'      => $c->candidate_id,
                    'candidate_name'    => $c->candidate->first_name . ' ' . $c->candidate->last_name,
                    'candidate_picture' => $c->candidate->profile_image,
                ];
            });
        } else {
            // option-based poll
            $votes = $poll->options->map(function ($opt) use ($poll) {
                $optionVotes = $poll->votes
                    ->where('poll_option_id', $opt->id)
                    ->filter(fn($v) => $v->voter !== null);

                return [
                    'poll_option_id' => $opt->id,
                    'option_text'    => $opt->option_text,
                    'votes_count'    => $optionVotes->count(),
                    'voters'         => $optionVotes->map(fn($v) => [
                        'voter_id'      => $v->voter_id,
                        'name'          => $v->voter->first_name . ' ' . $v->voter->last_name,
                        'email'         => $v->voter->email,
                        'profile_image' => $v->voter->profile_image,
                    ])->values(),
                ];
            });

            // keep option text only, remove added_by
            $options = $poll->options->map(function ($opt) {
                return [
                    'poll_option_id' => $opt->id,
                    'option_text'    => $opt->option_text,
                ];
            });
        }

        return [
            'poll_id'       => $poll->id,
            'poll_caption'  => $poll->question,
            'created_by'    => $poll->created_by,
            'poll_end_date' => $poll->poll_date,
            'created_at'    => $poll->created_at?->toDateString(),
            'allow_member_add_option'   => (bool) $poll->allow_member_add_option,
            'allow_multiple_selection'  => (bool) $poll->allow_multiple_selection,
            'status'        => $poll->status,
            'votes'         => $poll->votes->count() > 0 ? $votes : null,
            'options'       => $options, // ✅ either poll_options or poll_candidates
            'total_votes'   => $poll->votes->count(),
        ];
    });

    return $this->sendResponse('Event poll results fetched successfully', [
        'event_id'    => $event->id,
        'event_title' => $event->title,
        'polls'       => $results,
    ]);
}
public function createPoll(Request $request)
{
    $user = $request->user();

    // Validate request inputs
    $data = $request->validate([
        'event_id' => 'required|exists:events,id',
        'question' => 'required|string|max:255',
        'options' => 'required|array|min:1|max:6',
        'options.*' => 'required|string|max:100',
        'poll_date' => 'nullable|date',
        'allow_member_add_option' => 'boolean',
        'allow_multiple_selection' => 'boolean',
    ]);

    // Check if user is host or cohost of the event
    $isAuthorized = EventMember::where('event_id', $data['event_id'])
        ->where('user_id', $user->id)
        ->whereIn('role', ['host', 'cohost'])
        ->exists();

    if (!$isAuthorized) {
        return $this->sendError('Only the host or cohost can create a poll.', [], 403);
    }

    // Fetch event start datetime
    $event = Event::find($data['event_id']);
    $eventStart = Carbon::parse($event->date . ' ' . $event->start_time);

    // Poll date validation
    if (!empty($data['poll_date'])) {
        $pollDate = Carbon::parse($data['poll_date']);

        // Poll date must not be after event date (same day allowed)
        if ($pollDate->startOfDay()->gt($eventStart->copy()->startOfDay())) {
            return $this->sendError("Poll date must not be after the event date.", [], 422);
        }

        // Agar poll_date valid hai → end time = 23:59:00
        $pollEndTime = "23:59:00";
    } else {
        $pollEndTime = "00:00:00";
    }

    // Check for duplicate options
    $lowercaseOptions = array_map('strtolower', $data['options']);
    if (count($lowercaseOptions) !== count(array_unique($lowercaseOptions))) {
        return $this->sendError('Duplicate options are not allowed in a poll', [], 422);
    }

    // Create the poll
    $poll = Poll::create([
        'event_id' => $data['event_id'],
        'created_by' => $user->id,
        'question' => $data['question'],
        'poll_date' => $data['poll_date'] ?? null,
        'poll_end_time' => $pollEndTime, // ✅ Auto set
        'allow_member_add_option' => $data['allow_member_add_option'] ?? false,
        'allow_multiple_selection' => $data['allow_multiple_selection'] ?? false,
        'status' => 'active',
    ]);

     $onlymembers = EventMember::where('event_id',$event->id)
            ->where('status', 'joined')
        ->whereNotIn('role', ['host','cohost'])->get();
        foreach($onlymembers as $om)
        {
            Notification::create([
            'user_id' => auth()->id(), // jisne event create kiya
            'receiver_id' => $om->user_id, // jisko notification milegi
            'title' => 'Manual poll added Successfully',
            'message' => "{$user->first_name} {$user->last_name} has just created a pole in the event {$event->title}",
            'data'=>[
                'poll_id'=>$poll->id
            ]
        ]);
        }


    // Add poll options
    foreach ($data['options'] as $option) {
        $exists = PollOption::where('poll_id', $poll->id)
            ->whereRaw('LOWER(option_text) = ?', [strtolower($option)])
            ->exists();

        if ($exists) continue;

        PollOption::create([
            'poll_id' => $poll->id,
            'option_text' => $option,
            'added_by' => $user->id,
        ]);
    }

    // Return response with poll and options
    return $this->sendResponse('Poll created successfully', $poll->load('options'));
}


// public function createPoll(Request $request)
// {
//     $user = $request->user();

//     // ✅ Validate request inputs
//     $data = $request->validate([
//         'event_id' => 'required|exists:events,id',
//         'question' => 'required|string|max:255',
//         'options' => 'required|array|min:1|max:6',
//         'options.*' => 'required|string|max:100',
//         'poll_date' => 'nullable|date',
//         'poll_end_time' => 'nullable|date_format:H:i',
//         'allow_member_add_option' => 'boolean',
//         'allow_multiple_selection' => 'boolean',
//     ]);

//     // Check if user is host or cohost of the event
//     $isAuthorized = EventMember::where('event_id', $data['event_id'])
//         ->where('user_id', $user->id)
//         ->whereIn('role', ['host', 'cohost'])
//         ->exists();

//     if (!$isAuthorized) {
//         return $this->sendError('Only the host or cohost can create a poll.', [], 403);
//     }

//     // Fetch event start datetime
//     $event = Event::find($data['event_id']);
//     $eventStart = Carbon::parse($event->date . ' ' . $event->start_time);

//     // Poll date and end time validation
//     if (!empty($data['poll_date']) || !empty($data['poll_end_time'])) {
//         $pollDate = !empty($data['poll_date']) ? Carbon::parse($data['poll_date']) : $eventStart->copy()->startOfDay();
//         $pollEnd = !empty($data['poll_end_time']) ? Carbon::parse($pollDate->format('Y-m-d') . ' ' . $data['poll_end_time']) : $pollDate->copy()->endOfDay();

//         // Poll date must not be after event date (same day allowed)
//         if ($pollDate->startOfDay()->gt($eventStart->copy()->startOfDay())) {
//             return $this->sendError("Poll date must not be after the event date.", [], 422);
//         }

//         // Poll end must be before event start
//         if ($pollEnd->greaterThanOrEqualTo($eventStart)) {
//             return $this->sendError("Your event starts soon. Set the deadline at least 1 hour before the event begins", [], 422);
//         }

//         // If event is today, poll_end_time must be at least 1 hour before event start
//         if ($eventStart->isToday() && Carbon::now()->isToday()) {
//             if ($pollEnd->greaterThanOrEqualTo($eventStart->copy()->subHour())) {
//                 return $this->sendError("Your event starts soon. Set the deadline at least 1 hour before the event begins.", [], 422);
//             }
//         }
//     }

//     // ✅ Check for duplicate options
//     $lowercaseOptions = array_map('strtolower', $data['options']);
//     if (count($lowercaseOptions) !== count(array_unique($lowercaseOptions))) {
//         return $this->sendError('Duplicate options are not allowed in a poll', [], 422);
//     }

//     // ✅ Create the poll
//     $poll = Poll::create([
//         'event_id' => $data['event_id'],
//         'created_by' => $user->id,
//         'question' => $data['question'],
//         'poll_date' => $data['poll_date'] ?? null,
//         'poll_end_time' => $data['poll_end_time'] ?? "00:00:00",
//         'allow_member_add_option' => $data['allow_member_add_option'] ?? false,
//         'allow_multiple_selection' => $data['allow_multiple_selection'] ?? false,
//         'status' => 'active',
//     ]);

//     // ✅ Add poll options
//     foreach ($data['options'] as $option) {
//         $exists = PollOption::where('poll_id', $poll->id)
//             ->whereRaw('LOWER(option_text) = ?', [strtolower($option)])
//             ->exists();

//         if ($exists) continue;

//         PollOption::create([
//             'poll_id' => $poll->id,
//             'option_text' => $option,
//             'added_by' => $user->id,
//         ]);
//     }

//     // ✅ Return response with poll and options
//     return $this->sendResponse('Poll created successfully', $poll->load('options'));
// }

public function updatePoll(Request $request, $pollId)
{
    $user = $request->user();
    $poll = Poll::with('event')->findOrFail($pollId);

    // ✅ Check authorization: creator or host/cohost
    $isCreator = $poll->created_by === $user->id;
    $isHostOrCohost = EventMember::where('event_id', $poll->event_id)
        ->where('user_id', $user->id)
        ->whereIn('role', ['host', 'cohost'])
        ->exists();

    if (!$isCreator && !$isHostOrCohost) {
        return $this->sendError('You are not authorized to update this poll', [], 403);
    }

    // ✅ Validate inputs
    $data = $request->validate([
        'question' => 'sometimes|required|string|max:255',
        'options' => 'sometimes|array|min:1|max:6',
        'options.*' => 'required|string|max:100',
        'poll_date' => 'nullable|date',
        'poll_end_time' => 'nullable|date_format:H:i',
        'allow_member_add_option' => 'boolean',
        'allow_multiple_selection' => 'boolean',
        'status' => 'in:active,closed'
    ]);

    $event = $poll->event;
    $eventStart = Carbon::parse($event->date . ' ' . $event->start_time);

    // ✅ Poll date and poll_end_time validation
    if (!empty($data['poll_date']) || !empty($data['poll_end_time'])) {
        $pollDate = !empty($data['poll_date']) ? Carbon::parse($data['poll_date']) : Carbon::parse($poll->poll_date ?? $event->date);
        $pollEnd = !empty($data['poll_end_time']) ? Carbon::parse($pollDate->format('Y-m-d') . ' ' . $data['poll_end_time']) : Carbon::parse($pollDate->format('Y-m-d') . ' 00:00:00');

        // Poll date must not be after event date
        if ($pollDate->startOfDay()->gt($eventStart->copy()->startOfDay())) {
            return $this->sendError("Poll date must not be after the event date.", [], 422);
        }

        // Poll end must be before event start
        if ($pollEnd->greaterThanOrEqualTo($eventStart)) {
            return $this->sendError("Your event starts soon. Set the deadline at least 1 hour before the event begins.", [], 422);
        }

        // If event is today, poll_end_time must be at least 1 hour before event start
        if ($eventStart->isToday() && Carbon::now()->isToday()) {
            if ($pollEnd->greaterThanOrEqualTo($eventStart->copy()->subHour())) {
                return $this->sendError("Your event starts soon. Set the deadline at least 1 hour before the event begins.", [], 422);
            }
        }
    }

    // ✅ Update poll data
    $poll->update([
        'question' => $data['question'] ?? $poll->question,
        'poll_date' => $data['poll_date'] ?? $poll->poll_date,
        'poll_end_time' => $data['poll_end_time'] ?? $poll->poll_end_time ?? "00:00:00",
        'allow_member_add_option' => $data['allow_member_add_option'] ?? $poll->allow_member_add_option,
        'allow_multiple_selection' => $data['allow_multiple_selection'] ?? $poll->allow_multiple_selection,
        'status' => $data['status'] ?? $poll->status,
    ]);

    // ✅ Update poll options if provided
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



// public function createPoll(Request $request)
// {
//     $user = $request->user();

//     // ✅ Validate request inputs
//     $data = $request->validate([
//         'event_id' => 'required|exists:events,id',
//         'question' => 'required|string|max:255',
//         'options' => 'required|array|min:1|max:6',
//         'options.*' => 'required|string|max:100',
//         'poll_date' => 'nullable|date',
//         'poll_end_time' => 'nullable|date_format:H:i',
//         'allow_member_add_option' => 'boolean',
//         'allow_multiple_selection' => 'boolean',
//     ]);

//     // ✅ Check if user is host or cohost of the event
//     $isAuthorized = EventMember::where('event_id', $data['event_id'])
//         ->where('user_id', $user->id)
//         ->whereIn('role', ['host', 'cohost'])
//         ->exists();

//     if (!$isAuthorized) {
//         return $this->sendError('Only the host or cohost can create a poll.', [], 403);
//     }

//     // ✅ Fetch event start datetime
//     $event = Event::find($data['event_id']);
//     $eventStart = Carbon::parse($event->date . ' ' . $event->start_time);

//     // ✅ Poll date and end time validation
//     if (!empty($data['poll_date']) || !empty($data['poll_end_time'])) {
//         $pollDate = !empty($data['poll_date']) ? Carbon::parse($data['poll_date']) : $eventStart->copy()->startOfDay();
//         $pollEnd = !empty($data['poll_end_time']) ? Carbon::parse($pollDate->format('Y-m-d') . ' ' . $data['poll_end_time']) : $pollDate->copy()->endOfDay();

//         // Poll date must not be after event date
//         if ($pollDate->greaterThan($eventStart->copy()->startOfDay())) {
//             return $this->sendError("Poll date must not be after the event date.", [], 422);
//         }

//         // Poll end must be before event start
//         if ($pollEnd->greaterThanOrEqualTo($eventStart)) {
//             return $this->sendError("Voting deadline must be set before the event starts.", [], 422);
//         }

//         // If event is today, poll_end_time must be at least 1 hour before event start
//         if ($eventStart->isToday() && Carbon::now()->isToday()) {
//             if ($pollEnd->greaterThanOrEqualTo($eventStart->copy()->subHour())) {
//                 return $this->sendError("Your event starts soon. Set the deadline at least 1 hour before the event begins.", [], 422);
//             }
//         }
//     }

//     // ✅ Check for duplicate options
//     $lowercaseOptions = array_map('strtolower', $data['options']);
//     if (count($lowercaseOptions) !== count(array_unique($lowercaseOptions))) {
//         return $this->sendError('Duplicate options are not allowed in a poll', [], 422);
//     }

//     // ✅ Create the poll
//     $poll = Poll::create([
//         'event_id' => $data['event_id'],
//         'created_by' => $user->id,
//         'question' => $data['question'],
//         'poll_date' => $data['poll_date'] ?? null,
//         'poll_end_time' => $data['poll_end_time'] ?? "00:00:00",
//         'allow_member_add_option' => $data['allow_member_add_option'] ?? false,
//         'allow_multiple_selection' => $data['allow_multiple_selection'] ?? false,
//         'status' => 'active',
//     ]);

//     // ✅ Add poll options
//     foreach ($data['options'] as $option) {
//         $exists = PollOption::where('poll_id', $poll->id)
//             ->whereRaw('LOWER(option_text) = ?', [strtolower($option)])
//             ->exists();

//         if ($exists) continue;

//         PollOption::create([
//             'poll_id' => $poll->id,
//             'option_text' => $option,
//             'added_by' => $user->id,
//         ]);
//     }

//     // ✅ Return response with poll and options
//     return $this->sendResponse('Poll created successfully', $poll->load('options'));
// }



    // public function createPoll(Request $request)
    // {
    //     $user = $request->user();

    //     $data = $request->validate([
    //         'event_id' => 'required|exists:events,id',
    //         'question' => 'required|string|max:255',
    //         'options' => 'required|array|min:1|max:6',
    //         'options.*' => 'required|string|max:100',
    //         'poll_date' => 'nullable|date',
    //         'allow_member_add_option' => 'boolean',
    //         'allow_multiple_selection' => 'boolean',
    //     ]);

    //     $lowercaseOptions = array_map('strtolower', $data['options']);
    //     if (count($lowercaseOptions) !== count(array_unique($lowercaseOptions))) {
    //         return $this->sendError('Duplicate options are not allowed in a poll');
    //     }

    //     $poll = Poll::create([
    //         'event_id' => $data['event_id'],
    //         'created_by' => $user->id,
    //         'question' => $data['question'],
    //         'poll_date' => $data['poll_date'] ?? null,
    //         'allow_member_add_option' => $data['allow_member_add_option'] ?? false,
    //         'allow_multiple_selection' => $data['allow_multiple_selection'] ?? false,
    //         'status' => 'active',
    //     ]);

    //     foreach ($data['options'] as $option) {
    //         $exists = PollOption::where('poll_id', $poll->id)
    //             ->whereRaw('LOWER(option_text) = ?', [strtolower($option)])
    //             ->exists();

    //         if ($exists) {
    //             continue;
    //         }

    //         PollOption::create([
    //             'poll_id' => $poll->id,
    //             'option_text' => $option,
    //             'added_by' => $user->id,
    //         ]);
    //     }

    //     return $this->sendResponse('Poll created successfully', $poll->load('options'));
    // }



//     public function createPoll(Request $request)
// {
//     $user = $request->user();

//     $data = $request->validate([
//         'event_id' => 'required|exists:events,id',
//         'question' => 'required|string|max:255',
//         'options' => 'required|array|min:1|max:6',
//         'options.*' => 'required|string|max:100',
//         'poll_date' => 'nullable|date',
//         'allow_member_add_option' => 'boolean',
//         'allow_multiple_selection' => 'boolean',
//     ]);

//     // Check if user is host or cohost of the event
//     $isAuthorized = EventMember::where('event_id', $data['event_id'])
//         ->where('user_id', $user->id)
//         ->whereIn('role', ['host', 'cohost'])
//         ->exists();

//     if (!$isAuthorized) {
//         return $this->sendError('Only the host or cohost can create a poll.', [], 403);
//     }

//     //  Check for duplicate options
//     $lowercaseOptions = array_map('strtolower', $data['options']);
//     if (count($lowercaseOptions) !== count(array_unique($lowercaseOptions))) {
//         return $this->sendError('Duplicate options are not allowed in a poll', [], 422);
//     }

//     // Create poll
//     $poll = Poll::create([
//         'event_id' => $data['event_id'],
//         'created_by' => $user->id,
//         'question' => $data['question'],
//         'poll_date' => $data['poll_date'] ?? null,
//         'allow_member_add_option' => $data['allow_member_add_option'] ?? false,
//         'allow_multiple_selection' => $data['allow_multiple_selection'] ?? false,
//         'status' => 'active',
//     ]);

//     //  Add options
//     foreach ($data['options'] as $option) {
//         $exists = PollOption::where('poll_id', $poll->id)
//             ->whereRaw('LOWER(option_text) = ?', [strtolower($option)])
//             ->exists();

//         if ($exists) {
//             continue;
//         }

//         PollOption::create([
//             'poll_id' => $poll->id,
//             'option_text' => $option,
//             'added_by' => $user->id,
//         ]);
//     }

//     return $this->sendResponse('Poll created successfully', $poll->load('options'));
// }

    // public function updatePoll(Request $request, $pollId)
    // {
    //     $user = $request->user();
    //     $poll = Poll::with('event')->findOrFail($pollId);


    //     $isCreator = $poll->created_by === $user->id;
    //     $isHostOrCohost = EventMember::where('event_id', $poll->event_id)
    //         ->where('user_id', $user->id)
    //         ->whereIn('role', ['host', 'cohost'])
    //         ->exists();

    //     if (!$isCreator && !$isHostOrCohost) {
    //         return $this->sendError('You are not authorized to update this poll', [], 403);
    //     }

    //     $data = $request->validate([
    //         'question' => 'sometimes|required|string|max:255',
    //         'options' => 'sometimes|array|min:1|max:6',
    //         'options.*' => 'required|string|max:100',
    //         'poll_date' => 'nullable|date',
    //         'allow_member_add_option' => 'boolean',
    //         'allow_multiple_selection' => 'boolean',
    //         'status' => 'in:active,closed'
    //     ]);

    //     $poll->update([
    //         'question' => $data['question'] ?? $poll->question,
    //         'poll_date' => $data['poll_date'] ?? $poll->poll_date,
    //         'allow_member_add_option' => $data['allow_member_add_option'] ?? $poll->allow_member_add_option,
    //         'allow_multiple_selection' => $data['allow_multiple_selection'] ?? $poll->allow_multiple_selection,
    //         'status' => $data['status'] ?? $poll->status,
    //     ]);

    //     if (isset($data['options'])) {
    //         $lowercaseOptions = array_map('strtolower', $data['options']);
    //         if (count($lowercaseOptions) !== count(array_unique($lowercaseOptions))) {
    //             return $this->sendError('Duplicate options are not allowed in a poll');
    //         }

    //         $poll->options()->delete();

    //         foreach ($data['options'] as $option) {
    //             PollOption::create([
    //                 'poll_id' => $poll->id,
    //                 'option_text' => $option,
    //                 'added_by' => $user->id,
    //             ]);
    //         }
    //     }

    //     return $this->sendResponse('Poll updated successfully', $poll->load('options'));
    // }


    // public function deletePoll(Request $request, $pollId)
    // {
    //     $user = $request->user();
    //     $poll = Poll::with('event')->findOrFail($pollId);

    //     $isCreator = $poll->created_by === $user->id;
    //     $isHostOrCohost = EventMember::where('event_id', $poll->event_id)
    //         ->where('user_id', $user->id)
    //         ->whereIn('role', ['host', 'cohost'])
    //         ->exists();

    //     if (!$isCreator && !$isHostOrCohost) {
    //         return $this->sendError('You are not authorized to delete this poll', [], 403);
    //     }

    //     $poll->delete();

    //     return $this->sendResponse('Poll deleted successfully');
    // }



//New Deletepoll due to prepared statment db issue
public function deletePoll(Request $request, $pollId)
{
    $user = $request->user();
    $poll = Poll::with('event')->findOrFail($pollId);

    // Check if user is creator or host/cohost of the event
    $isCreator = $poll->created_by === $user->id;
    $isHostOrCohost = EventMember::where('event_id', $poll->event_id)
        ->where('user_id', $user->id)
        ->whereIn('role', ['host', 'cohost'])
        ->exists();

    if (!$isCreator && !$isHostOrCohost) {
        return $this->sendError('You are not authorized to delete this poll', [], 403);
    }

    // Delete related poll options in a single query to avoid prepared statement issues
    \DB::table('poll_options')
        ->where('poll_id', $poll->id)
        ->delete();

    // Delete the poll itself
    $poll->delete();

    return $this->sendResponse('Poll deleted successfully');
}

    public function addOption(Request $request, $pollId)
    {
        $user = $request->user();

        $poll = Poll::findOrFail($pollId);
        $eventPoll= Poll::with('event')
        ->find($pollId);


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

    $onlymembers = EventMember::where('event_id',$eventPoll->event_id)
            ->where('status', 'joined')->get();
    foreach($onlymembers as $om){
         Notification::create([
            'user_id' => auth()->id(), // jisne poll edit kiya
            'receiver_id' => $om->user_id, // jisko notification milegi
            'title' => 'Poll option added Successfully',
            'message' => "{$user->first_name} {$user->last_name} has just added an option {$option->option_text} in the event : {$eventPoll->event->title} .",
            'data'=> [
                'poll_id'=>$poll->id,
                'poll_option'=>$option->option_text
            ]


        ]);

    }

        return $this->sendResponse('Option added successfully', $option);
    }


}
