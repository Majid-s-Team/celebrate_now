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
        try {
            $user = auth()->user();
            $wallet = Wallet::firstOrCreate(['user_id' => $user->id], ['balance' => 0]);

            $data = [
                'user_id' => $user->id,
                'balance' => (int) $wallet->balance,
            ];

            return $this->sendResponse('My wallet fetched successfully', $data);
        } catch (\Exception $e) {
            return $this->sendError('Something went wrong', [$e->getMessage()], 500);
        }
    }

   public function listWithDonations(Request $request)
{
    try {
        $authId = auth()->id();
        $eventId = $request->query('event_id');

        if (!$eventId) {
            return $this->sendError('Event ID is required', [], 422);
        }

        $event = Event::with([
            'creator:id,first_name,last_name',
            'donationContributions.sender:id,first_name,last_name',
            // 'surpriseContributions.sender:id,first_name,last_name'
        ])->find($eventId);

        if (!$event) {
            return $this->sendError('Event not found', [], 404);
        }

        $donationTotal = $event->donations()->sum('amount');
        $surpriseTotal = $event->surprise_contribution
            ? $event->surpriseContributions()->sum('coins')
            : 0;

        $totalCollected = $donationTotal + $surpriseTotal;
        $remaining = max(0, ($event->donation_goal ?? 0) - $totalCollected);

        $myContribution = $event->donations()
            ->where('user_id', $authId)
            ->sum('amount')
            +
            ($event->surprise_contribution
                ? $event->surpriseContributions()
                    ->where('sender_id', $authId)
                    ->sum('coins')
                : 0
            );

        $donors = [];
        if ($event->is_show_donation) {
            $donors = $event->donations->map(function ($d) {
                return [
                    'user_id' => $d->user->id,
                    'name' => $d->user->first_name . ' ' . $d->user->last_name,
                    'amount' => $d->amount,
                    'profile_image' => $d->user->profile_image,
                    'created_at' => $d->created_at->toDateTimeString(),
                ];
            });
        } else {
            $hostId = $event->created_by;
            $donors = $event->donations
                ->where('user_id', $hostId)
                ->map(function ($d) {
                    return [
                        'user_id' => $d->user->id,
                        'name' => $d->user->first_name . ' ' . $d->user->last_name,
                        'amount' => $d->amount,
                        'profile_image' => $d->user->profile_image,
                        'created_at' => $d->created_at->toDateTimeString(),
                    ];
                })
                ->values();
        }

        $surpriseContributors = [];
        if ($event->surprise_contribution) {

            $surpriseContributors = $event->surpriseContributions->map(function ($tx) {
                return [
                    'user_id' => $tx->sender->id,
                    'name' => $tx->sender->first_name . ' ' . $tx->sender->last_name,
                    'amount' => $tx->coins,
                    'profile_image' => $tx->sender->profile_image,
                    'created_at' => $tx->created_at->toDateTimeString(),
                ];
            });
        }

        $data = [
            'event_id'              => $event->id,
            'title'                 => $event->title,
            'funding_type'          => $event->funding_type,
            'target_amount'         => $event->donation_goal,
            'total_collected'       => $totalCollected,
            'remaining'             => $remaining,
            'my_contribution'       => $myContribution,
            'donors'                => $donors,
            'surprise_contributors' => $surpriseContributors,
            'is_show_donation' => $event->is_show_donation
        ];

        return $this->sendResponse('Event donation & surprise contribution detail fetched successfully', $data);

    } catch (\Exception $e) {
        return $this->sendError('Something went wrong', [$e->getMessage()], 500);
    }
}


public function listWithSurpriseContributionsAndTotal(Request $request)
{
    try {
        $authId = auth()->id();
        $eventId = $request->query('event_id');

        if (!$eventId) {
            return $this->sendError('Event ID is required', [], 422);
        }

        $event = Event::with([
            'creator:id,first_name,last_name',
            'surpriseContributions.sender:id,first_name,last_name,profile_image'
        ])->find($eventId);

        if (!$event) {
            return $this->sendError('Event not found', [], 404);
        }

        // Fetching surprise contributors
        $surpriseContributors = collect(); // start with empty collection
        $totalSurpriseAmount = 0;

        if ($event->surprise_contribution) {
            $surpriseContributors = $event->surpriseContributions->map(function ($tx) use (&$totalSurpriseAmount) {
                $totalSurpriseAmount += $tx->coins;
                return [
                    'user_id'    => $tx->sender->id,
                    'name'       => $tx->sender->first_name . ' ' . $tx->sender->last_name,
                    'amount'     => $tx->coins,
                    'profile_image' => $tx->sender->profile_image,
                    'created_at' => $tx->created_at->toDateTimeString(),
                ];
            });
        }

        // If no surprise contributions, return an empty array
        if ($surpriseContributors->isEmpty()) {
            return $this->sendResponse('No surprise contributions found', []);
        }

        // Preparing response data with total surprise contribution amount
        $data = [
            'event_id'              => $event->id,
            'title'                 => $event->title,
            'total_surprise_amount' => $totalSurpriseAmount,
            'surprise_contributors' => $surpriseContributors,
        ];

        return $this->sendResponse('Surprise contribution details fetched successfully', $data);

    } catch (\Exception $e) {
        return $this->sendError('Something went wrong', [$e->getMessage()], 500);
    }
}



}
