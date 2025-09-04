<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Wallet;
use App\Models\Event;

class WalletController extends Controller
{
    public function myWallet()
    {
        $user = auth()->user();
        $wallet = Wallet::firstOrCreate(['user_id' => $user->id], ['balance' => 0]);

        return response()->json([
            'message' => 'My wallet',
            'data' => [
                'user_id' => $user->id,
                'balance' => (int) $wallet->balance,
            ],
        ]);
    }
    public function listWithDonations(Request $request)
    {
        $authId = auth()->id();
        $eventId = $request->query('event_id');

        $query = Event::with('creator:id,first_name,last_name')
            ->with('donations.user:id,first_name,last_name');

        if ($eventId) {
            $query->where('id', $eventId);
        }

        $events = $query->get();

        $response = $events->map(function ($event) use ($authId) {
            $totalCollected = $event->donations()->sum('amount');
            $remaining = max(0, ($event->target_amount ?? 0) - $totalCollected);

            $myContribution = $event->donations()
                ->where('user_id', $authId)
                ->sum('amount');

            $donors = [];
            if ($event->is_show_donation) {
                $donors = $event->donations->map(function ($d) {
                    return [
                        'user_id' => $d->user->id,
                        'name' => $d->user->first_name . ' ' . $d->user->last_name,
                        'amount' => $d->amount,
                        'created_at' => $d->created_at->toDateTimeString(),
                    ];
                });
            }

            return [
                'event_id' => $event->id,
                'title' => $event->title,
                'funding_type' => $event->funding_type,
                'target_amount' => $event->target_amount,
                'total_collected' => $totalCollected,
                'remaining' => $remaining,
                'my_contribution' => $myContribution,
                'donors' => $event->is_show_donation ? $donors : [],
            ];
        });

        return response()->json([
            'message' => $eventId ? 'Event donation detail' : 'Events with donations',
            'data' => $eventId ? $response->first() : $response,
        ]);
    }

}
