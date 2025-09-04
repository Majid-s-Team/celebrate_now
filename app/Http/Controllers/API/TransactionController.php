<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CoinTransaction;
use App\Models\Post;
use App\Models\Event;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    public function send(Request $request)
    {
        $data = $request->validate([
            'to_user_id' => ['required', 'exists:users,id'],
            'coins' => ['required', 'integer', 'min:1'],
            'message' => ['nullable', 'string', 'max:1000'],
            'post_id' => ['nullable', 'exists:posts,id'],
            'event_id' => ['nullable', 'exists:events,id'],
        ]);

        $sender = auth()->user();
        if ((int) $data['to_user_id'] === (int) $sender->id) {
            return response()->json(['message' => 'Cannot send coins to yourself'], 422);
        }


        $receiverId = (int) $data['to_user_id'];

        if (!empty($data['post_id'])) {
            $post = Post::findOrFail($data['post_id']);
            $receiverId = (int) $post->user_id;
        } elseif (!empty($data['event_id'])) {
            $event = Event::findOrFail($data['event_id']);
            $receiverId = (int) $event->created_by;
        }


        if ($receiverId === (int) $sender->id) {
            return response()->json(['message' => 'Cannot send coins to yourself'], 422);
        }

        $coins = (int) $data['coins'];
        $note = $data['message'] ?? null;
        $postId = $data['post_id'] ?? null;
        $eventId = $data['event_id'] ?? null;

        DB::transaction(function () use ($sender, $receiverId, $coins, $note, $postId, $eventId) {

            $senderWallet = Wallet::where('user_id', $sender->id)->lockForUpdate()->first();
            if (!$senderWallet) {
                $senderWallet = Wallet::create(['user_id' => $sender->id, 'balance' => 0]);
                $senderWallet = Wallet::where('id', $senderWallet->id)->lockForUpdate()->first();
            }

            if ((int) $senderWallet->balance < $coins) {
                abort(422, 'Insufficient balance');
            }


            $receiverWallet = Wallet::where('user_id', $receiverId)->lockForUpdate()->first();
            if (!$receiverWallet) {
                $receiverWallet = Wallet::create(['user_id' => $receiverId, 'balance' => 0]);
                $receiverWallet = Wallet::where('id', $receiverWallet->id)->lockForUpdate()->first();
            }


            $senderWallet->balance = (int) $senderWallet->balance - $coins;
            $senderWallet->save();

            $receiverWallet->balance = (int) $receiverWallet->balance + $coins;
            $receiverWallet->save();

            CoinTransaction::create([
                'sender_id' => $sender->id,
                'receiver_id' => $receiverId,
                'coin_package_id' => null,
                'post_id' => $postId,
                'event_id' => $eventId,
                'coins' => $coins,
                'type' => 'send',
                'message' => $note,
            ]);

            CoinTransaction::create([
                'sender_id' => $sender->id,
                'receiver_id' => $receiverId,
                'coin_package_id' => null,
                'post_id' => $postId,
                'event_id' => $eventId,
                'coins' => $coins,
                'type' => 'receive',
                'message' => $note,
            ]);
        });

        return response()->json(['message' => 'Coins sent successfully']);
    }


    public function spend(Request $request)
    {
        $data = $request->validate([
            'coins' => ['required', 'integer', 'min:1'],
            'message' => ['nullable', 'string', 'max:1000'],
            'post_id' => ['nullable', 'exists:posts,id'],
            'event_id' => ['nullable', 'exists:events,id'],
        ]);

        $user = auth()->user();
        $coins = (int) $data['coins'];

        DB::transaction(function () use ($user, $coins, $data) {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();
            if (!$wallet) {
                $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 0]);
                $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();
            }

            if ((int) $wallet->balance < $coins) {
                abort(422, 'Insufficient balance');
            }

            $wallet->balance = (int) $wallet->balance - $coins;
            $wallet->save();

            CoinTransaction::create([
                'sender_id' => $user->id,
                'receiver_id' => null, // platform
                'coin_package_id' => null,
                'post_id' => $data['post_id'] ?? null,
                'event_id' => $data['event_id'] ?? null,
                'coins' => $coins,
                'type' => 'spend',
                'message' => $data['message'] ?? null,
            ]);
        });

        return response()->json(['message' => 'Coins spent successfully']);
    }
    public function myTransactions(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'type' => ['nullable', Rule::in(['purchase', 'send', 'receive', 'spend'])],
            'direction' => ['nullable', Rule::in(['all', 'in', 'out'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $type = $request->get('type');
        $direction = $request->get('direction', 'all');
        $perPage = (int) ($request->get('per_page', 15));

        $q = CoinTransaction::query()
            ->with(['sender:id,first_name,last_name', 'receiver:id,first_name,last_name', 'package:id,coins,price,currency'])
            ->where(function ($sub) use ($user, $direction) {
                if ($direction === 'in') {
                    $sub->where(function ($s) use ($user) {
                        $s->where('receiver_id', $user->id)
                            ->orWhere(function ($s2) use ($user) {
                                $s2->whereNull('receiver_id')->where('sender_id', $user->id)->where('type', 'purchase'); // rare shape (not used here)
                            });
                    });
                } elseif ($direction === 'out') {
                    $sub->where('sender_id', $user->id);
                } else {
                    $sub->where(function ($s) use ($user) {
                        $s->where('sender_id', $user->id)
                            ->orWhere('receiver_id', $user->id);
                    });
                }
            });

        if ($type) {
            $q->where('type', $type);
        }

        $q->orderByDesc('id');

        $items = $q->paginate($perPage);

        return response()->json([
            'message' => 'My transactions',
            'data' => $items,
        ]);
    }
}
