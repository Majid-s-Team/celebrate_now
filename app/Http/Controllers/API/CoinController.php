<?php

namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CoinPackage;
use App\Models\CoinTransaction;
use App\Models\Event;
use App\Models\Post;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CoinController extends Controller {
    // ---------------------------
    // Packages
    // ---------------------------

    // GET /api/coins/packages
    public function listPackages()
    {
        $packages = CoinPackage::orderBy('price')->get();
        return response()->json([
            'message' => 'Coin packages',
            'data' => $packages,
        ]);
    }

    // (Optional - Admin) POST /api/coins/packages
    public function createPackage(Request $request)
    {
        $this->authorize('create', CoinPackage::class); // if you use policies
        $data = $request->validate([
            'coins'    => ['required', 'integer', 'min:1'],
            'price'    => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'], // e.g. USD
        ]);

        $pkg = CoinPackage::create([
            'coins'    => $data['coins'],
            'price'    => $data['price'],
            'currency' => $data['currency'] ?? 'USD',
        ]);

        return response()->json(['message' => 'Package created', 'data' => $pkg], 201);
    }

    // (Optional - Admin) PUT /api/coins/packages/{id}
    public function updatePackage(Request $request, $id)
    {
        $this->authorize('update', CoinPackage::class);
        $pkg = CoinPackage::findOrFail($id);

        $data = $request->validate([
            'coins'    => ['sometimes', 'integer', 'min:1'],
            'price'    => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
        ]);

        $pkg->update($data);
        return response()->json(['message' => 'Package updated', 'data' => $pkg]);
    }

    // (Optional - Admin) DELETE /api/coins/packages/{id}
    public function deletePackage($id)
    {
        $this->authorize('delete', CoinPackage::class);
        $pkg = CoinPackage::findOrFail($id);
        $pkg->delete();

        return response()->json(['message' => 'Package deleted']);
    }

    // ---------------------------
    // Wallet
    // ---------------------------

    // GET /api/coins/wallet
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

    // POST /api/coins/purchase
    // body: { coin_package_id: int }
    public function purchase(Request $request)
    {
        $data = $request->validate([
            'coin_package_id' => ['required', 'exists:coin_packages,id'],
        ]);

        $user = auth()->user();
        $package = CoinPackage::findOrFail($data['coin_package_id']);

        DB::transaction(function () use ($user, $package) {
            // Lock or create wallet
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();
            if (!$wallet) {
                $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 0]);
                // re-lock to be safe
                $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();
            }

            // Add coins
            $wallet->balance = (int)$wallet->balance + (int)$package->coins;
            $wallet->save();

            // Log transaction (purchase)
            CoinTransaction::create([
                'sender_id'       => null,
                'receiver_id'     => $user->id,
                'coin_package_id' => $package->id,
                'post_id'         => null,
                'event_id'        => null,
                'coins'           => $package->coins,
                'type'            => 'purchase',
                'message'         => 'Package purchase: ' . $package->coins . ' coins',
            ]);
        });

        return response()->json(['message' => 'Coins purchased successfully']);
    }

    // ---------------------------
    // Send coins (direct / post / event)
    // ---------------------------

    // POST /api/coins/send
    // body: { to_user_id, coins, message?, post_id?, event_id? }
    public function send(Request $request)
    {
        $data = $request->validate([
            'to_user_id' => ['required', 'exists:users,id'],
            'coins'      => ['required', 'integer', 'min:1'],
            'message'    => ['nullable', 'string', 'max:1000'],
            'post_id'    => ['nullable', 'exists:posts,id'],
            'event_id'   => ['nullable', 'exists:events,id'],
        ]);

        $sender = auth()->user();
        if ((int)$data['to_user_id'] === (int)$sender->id) {
            return response()->json(['message' => 'Cannot send coins to yourself'], 422);
        }

        // If post_id or event_id is provided, prefer their owner as receiver
        $receiverId = (int)$data['to_user_id'];

        if (!empty($data['post_id'])) {
            $post = Post::findOrFail($data['post_id']);
            $receiverId = (int)$post->user_id; // tip to post owner
        } elseif (!empty($data['event_id'])) {
            $event = Event::findOrFail($data['event_id']);
            $receiverId = (int)$event->created_by; // tip to event creator
        }

        // Prevent sending to self after resolving post/event owner
        if ($receiverId === (int)$sender->id) {
            return response()->json(['message' => 'Cannot send coins to yourself'], 422);
        }

        $coins  = (int)$data['coins'];
        $note   = $data['message'] ?? null;
        $postId = $data['post_id'] ?? null;
        $eventId= $data['event_id'] ?? null;

        DB::transaction(function () use ($sender, $receiverId, $coins, $note, $postId, $eventId) {
            // Lock sender wallet
            $senderWallet = Wallet::where('user_id', $sender->id)->lockForUpdate()->first();
            if (!$senderWallet) {
                $senderWallet = Wallet::create(['user_id' => $sender->id, 'balance' => 0]);
                $senderWallet = Wallet::where('id', $senderWallet->id)->lockForUpdate()->first();
            }

            if ((int)$senderWallet->balance < $coins) {
                abort(422, 'Insufficient balance');
            }

            // Lock receiver wallet
            $receiverWallet = Wallet::where('user_id', $receiverId)->lockForUpdate()->first();
            if (!$receiverWallet) {
                $receiverWallet = Wallet::create(['user_id' => $receiverId, 'balance' => 0]);
                $receiverWallet = Wallet::where('id', $receiverWallet->id)->lockForUpdate()->first();
            }

            // Move coins
            $senderWallet->balance = (int)$senderWallet->balance - $coins;
            $senderWallet->save();

            $receiverWallet->balance = (int)$receiverWallet->balance + $coins;
            $receiverWallet->save();

            // Log BOTH sides for clean history
            CoinTransaction::create([
                'sender_id'       => $sender->id,
                'receiver_id'     => $receiverId,
                'coin_package_id' => null,
                'post_id'         => $postId,
                'event_id'        => $eventId,
                'coins'           => $coins,
                'type'            => 'send',
                'message'         => $note,
            ]);

            CoinTransaction::create([
                'sender_id'       => $sender->id,
                'receiver_id'     => $receiverId,
                'coin_package_id' => null,
                'post_id'         => $postId,
                'event_id'        => $eventId,
                'coins'           => $coins,
                'type'            => 'receive',
                'message'         => $note,
            ]);
        });

        return response()->json(['message' => 'Coins sent successfully']);
    }

    // ---------------------------
    // Spend coins (platform purpose without a receiver)
    // e.g. feature purchase, boost, etc.
    // ---------------------------

    // POST /api/coins/spend
    // body: { coins, message?, post_id?, event_id? }  // post_id/event_id just to link context if you like
    public function spend(Request $request)
    {
        $data = $request->validate([
            'coins'    => ['required', 'integer', 'min:1'],
            'message'  => ['nullable', 'string', 'max:1000'],
            'post_id'  => ['nullable', 'exists:posts,id'],
            'event_id' => ['nullable', 'exists:events,id'],
        ]);

        $user  = auth()->user();
        $coins = (int)$data['coins'];

        DB::transaction(function () use ($user, $coins, $data) {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();
            if (!$wallet) {
                $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 0]);
                $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();
            }

            if ((int)$wallet->balance < $coins) {
                abort(422, 'Insufficient balance');
            }

            $wallet->balance = (int)$wallet->balance - $coins;
            $wallet->save();

            CoinTransaction::create([
                'sender_id'       => $user->id,
                'receiver_id'     => null, // platform
                'coin_package_id' => null,
                'post_id'         => $data['post_id'] ?? null,
                'event_id'        => $data['event_id'] ?? null,
                'coins'           => $coins,
                'type'            => 'spend',
                'message'         => $data['message'] ?? null,
            ]);
        });

        return response()->json(['message' => 'Coins spent successfully']);
    }

    // ---------------------------
    // History
    // ---------------------------

    // GET /api/coins/transactions?type=&direction=&per_page=15
    // direction: all|in|out  (in = received/purchased, out = send/spend)
    public function myTransactions(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'type'      => ['nullable', Rule::in(['purchase', 'send', 'receive', 'spend'])],
            'direction' => ['nullable', Rule::in(['all', 'in', 'out'])],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $type = $request->get('type');
        $direction = $request->get('direction', 'all');
        $perPage = (int)($request->get('per_page', 15));

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
            'data'    => $items,
        ]);
    }
}