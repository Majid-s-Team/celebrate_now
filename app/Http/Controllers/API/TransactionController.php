<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CoinTransaction;
use App\Models\EventDonation;
use App\Models\Post;
use App\Models\Event;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class TransactionController extends Controller
{

    // Post Contribution
    // Agar post_id pass ho → contribution us post ke user_id ko milega.
    // Validation: user apne khud ke post par apne aapko coins nahi bhej sakta.

    //  Event Contribution (funding_type = donation_based)
    // Jo bhi send karega uske coins event creator ke wallet me jayenge.
    // Contribution type hamesha "donation".
    // Message + coins save honge.
    // Validation: event creator apne event me apne aapko coins nahi bhej sakta.

    // Event Contribution (funding_type = self_financed + surprise_contribution = true)
    // Jo bhi bhejega uska record "surprise" contribution ke sath save hoga.
    // Receiver phir bhi event creator hoga, lekin contribution_type = surprise.
    // Validation same (no self transfer).
  public function send(Request $request)
{
    try {
         $type  = $request->get(key: "type");
        $data = $request->validate([
            'coins' => ['required', 'integer', 'min:1'],
            'message' => ['nullable', 'string', 'max:1000'],
            'post_id' => ['nullable', 'exists:posts,id'],
            'event_id' => ['nullable', 'exists:events,id'],
        ]);

        $sender = auth()->user();
        $coins = (int) $data['coins'];
        $note = $data['message'] ?? null;

        $receiverId = null;
        $eventId = $data['event_id'] ?? null;
        $postId = $data['post_id'] ?? null;
        $contributionType = null;

        if (!empty($postId)) {
            $post = Post::findOrFail($postId);
            $receiverId = $post->user_id;
            $contributionType = 'donation';
        } elseif (!empty($eventId)) {
            $event = Event::findOrFail($eventId);
            $receiverId = $event->created_by;

            $eventStart = Carbon::parse($event->date . ' ' . $event->start_time);
            if (now()->greaterThanOrEqualTo($eventStart)) {
                return $this->sendError('Event has already started or ended, cannot contribute.', [], 422);
            }

            if ($event->funding_type === 'donation_based') {
                $contributionType = 'donation';
            } elseif ($event->funding_type === 'self_financed') {
                if ($event->surprise_contribution) {
                    $contributionType = 'surprise';
                } else {
                    return $this->sendError('Surprise contribution not allowed for this event.', [], 422);
                }
            }
        }

        if (!$receiverId) {
            return $this->sendError('Invalid contribution target', [], 422);
        }

        if ($receiverId === $sender->id) {
            return $this->sendError('Cannot send coins to yourself', [], 422);
        }

        DB::transaction(function () use ($sender, $receiverId, $coins, $note, $postId, $eventId, $contributionType) {
            $senderWallet = Wallet::firstOrCreate(
                ['user_id' => $sender->id],
                ['balance' => 0]
            );
            $senderWallet->refresh()->lockForUpdate();

            if ($senderWallet->balance < $coins) {
                abort(422, 'Insufficient balance');
            }

            $receiverWallet = Wallet::firstOrCreate(
                ['user_id' => $receiverId],
                ['balance' => 0]
            );
            $receiverWallet->refresh()->lockForUpdate();

            $senderWallet->decrement('balance', $coins);
            $receiverWallet->increment('balance', $coins);

            // Log in coin_transactions (send)
            CoinTransaction::create([
                'sender_id' => $sender->id,
                'receiver_id' => $receiverId,
                'coin_package_id' => null,
                'post_id' => $postId,
                'event_id' => $eventId,
                'coins' => $coins,
                'type' => 'send',
                'message' => $note,
                'contribution_type' => $contributionType,
            ]);

            // Log in coin_transactions (receive)
            CoinTransaction::create([
                'sender_id' => $sender->id,
                'receiver_id' => $receiverId,
                'coin_package_id' => null,
                'post_id' => $postId,
                'event_id' => $eventId,
                'coins' => $coins,
                'type' => 'receive',
                'message' => $note,
                'contribution_type' => $contributionType,
            ]);
$contributionType = $contributionType ? $contributionType : ($type ?? 'donation');

// dd($contributionType);

            // ✅ Extra insert for event donations table
            if ($eventId && $contributionType === 'donation') {
                EventDonation::create([
                    'event_id' => $eventId,
                    'user_id'  => $sender->id,
                    'amount'   => $coins,
                ]);
            }
        });

        return $this->sendResponse('Coins sent successfully');
    } catch (\Exception $e) {
        return $this->sendError('Failed to send coins', ['error' => $e->getMessage()], 500);
    }
}




   public function eventTransactions(Request $request, $eventId = null)
{
    try {
        $user       = auth()->user();
        $dateTime   = $request->date_time;   
        $status     = $request->status;      
        $startDate  = $request->start_date;  
        $endDate    = $request->end_date;   

        if ($eventId) {
            $event = Event::findOrFail($eventId);

            $totalDonated = CoinTransaction::where('event_id', $eventId)
                ->where('type', 'send')
                ->when($dateTime, fn($q) => $q->whereDate('created_at', $dateTime))
                ->when($status, fn($q) => $q->where('type', $status))
                ->when($startDate && $endDate, fn($q) => $q->whereBetween('created_at', [$startDate, $endDate]))
                ->sum('coins');

            $targetAmount = $event->donation_goal ?? 0;
            $remaining = max($targetAmount - $totalDonated, 0);
            $percentage = $targetAmount > 0 ? round(($totalDonated / $targetAmount) * 100, 2) : 0;

            $response = [
                'event_id'       => $event->id,
                'event_title'    => $event->title,
                'target_amount'  => $targetAmount,
                'total_donated'  => $totalDonated,
                'remaining'      => $remaining,
                'percentage'     => $percentage,
                'contributors'   => [],
                'surprise'       => [],
            ];

            $donations = CoinTransaction::where('event_id', $eventId)
                ->where('type', 'send')
                ->where('contribution_type', 'donation')
                ->when($status, fn($q) => $q->where('status', $status))
                ->when($startDate && $endDate, fn($q) => $q->whereBetween('created_at', [$startDate, $endDate]))
                ->select('sender_id', DB::raw('SUM(coins) as total_coins'))
                ->groupBy('sender_id')
                ->with('sender:id,first_name,last_name')
                ->get();

            $surprises = CoinTransaction::where('event_id', $eventId)
                ->where('type', 'send')
                ->where('contribution_type', 'surprise')
                ->when($status, fn($q) => $q->where('status', $status))
                ->when($startDate && $endDate, fn($q) => $q->whereBetween('created_at', [$startDate, $endDate]))
                ->select('sender_id', DB::raw('SUM(coins) as total_coins'))
                ->groupBy('sender_id')
                ->with('sender:id,first_name,last_name')
                ->get();

            // Visibility logic
            if ($event->created_by == $user->id || $event->is_show_donation) {
                $response['contributors'] = $donations->map(fn($row) => [
                    'user_id' => $row->sender_id,
                    'name'    => $row->sender->first_name . ' ' . $row->sender->last_name,
                    'coins'   => $row->total_coins,
                    'type'    => 'donation',
                ]);

                $response['surprise'] = $surprises->map(fn($row) => [
                    'user_id' => $row->sender_id,
                    'name'    => $row->sender->first_name . ' ' . $row->sender->last_name,
                    'coins'   => $row->total_coins,
                    'type'    => 'surprise',
                ]);
            } else {
                $ownDonation = $donations->firstWhere('sender_id', $user->id);
                if ($ownDonation) $response['contributors'][] = [
                    'user_id' => $ownDonation->sender_id,
                    'name'    => $ownDonation->sender->first_name . ' ' . $ownDonation->sender->last_name,
                    'coins'   => $ownDonation->total_coins,
                    'type'    => 'donation',
                ];

                $ownSurprise = $surprises->firstWhere('sender_id', $user->id);
                if ($ownSurprise) $response['surprise'][] = [
                    'user_id' => $ownSurprise->sender_id,
                    'name'    => $ownSurprise->sender->first_name . ' ' . $ownSurprise->sender->last_name,
                    'coins'   => $ownSurprise->total_coins,
                    'type'    => 'surprise',
                ];
            }

            return $this->sendResponse('Event transactions fetched successfully', $response);
        }

        // Non-event transactions
        $transactions = CoinTransaction::with(['sender:id,first_name,last_name','receiver:id,first_name,last_name'])
            ->where(function($q) use ($user) {
                $q->where('sender_id', $user->id)
                  ->orWhere('receiver_id', $user->id);
            })
            ->when($status, fn($q) => $q->where('type', $status))
            ->when($startDate && $endDate, fn($q) => $q->whereBetween('created_at', [$startDate, $endDate]))
            ->orderBy('created_at','desc')
            ->get();

        $response = $transactions->map(fn($tx) => [
            'transaction_id'    => $tx->id,
            'coins'             => $tx->coins,
            'type'              => $tx->type,
            'message'           => $tx->message,
            'status'            => $tx->status, // include status in response
            'sender_id'         => $tx->sender_id,
            'receiver_id'       => $tx->receiver_id,
            'post_id'           => $tx->post_id,
            'event_id'          => $tx->event_id,
            'contribution_type' => $tx->contribution_type,
            'created_at'        => $tx->created_at->toDateTimeString(),
        ]);

        return $this->sendResponse('User transactions fetched successfully', $response);

    } catch (\Exception $e) {
        return $this->sendError('Failed to fetch transactions', ['error' => $e->getMessage()], 500);
    }
}




    public function gifts(Request $request)
    {
        try {
            $user = auth()->user();

            $type = $request->query('type', 'sent');
            $postId = $request->query('post_id');

            $query = CoinTransaction::with(['sender:id,first_name,last_name', 'receiver:id,first_name,last_name', 'post:id,caption,user_id'])
                ->whereNotNull('post_id');

            if ($type === 'sent') {
                $query->where('sender_id', $user->id)->where('type', 'send');
            } elseif ($type === 'received') {
                $query->where('receiver_id', $user->id)->where('type', 'receive');
            }

            if (!empty($postId)) {
                $query->where('post_id', $postId);
            }

            $transactions = $query->orderBy('id', 'desc')->get();

            $response = $transactions->map(function ($tx) use ($type) {
                return [
                    'transaction_id' => $tx->id,
                    'post_id' => $tx->post_id,
                    'post_title' => $tx->post->caption ?? null,
                    'coins' => $tx->coins,
                    'message' => $tx->message,
                    'contribution_type' => $tx->contribution_type,
                    'sender' => [
                        'id' => $tx->sender->id,
                        'name' => $tx->sender->first_name . ' ' . $tx->sender->last_name,
                    ],
                    'receiver' => [
                        'id' => $tx->receiver->id,
                        'name' => $tx->receiver->first_name . ' ' . $tx->receiver->last_name,
                    ],
                    'type' => $tx->type,
                    'created_at' => $tx->created_at->toDateTimeString(),
                ];
            });

            return $this->sendResponse("Gifts {$type} successfully", $response);

        } catch (\Exception $e) {
            return $this->sendError('Failed to fetch gifts', ['error' => $e->getMessage()], 500);
        }
    }



}
