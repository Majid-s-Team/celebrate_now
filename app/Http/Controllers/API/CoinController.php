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

class CoinController extends Controller
{

    public function listPackages()
    {
        $packages = CoinPackage::orderBy('price')->get();
        return response()->json([
            'message' => 'Coin packages',
            'data' => $packages,
        ]);
    }


    public function createPackage(Request $request)
    {

        $data = $request->validate([
            'coins' => ['required', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);

        $pkg = CoinPackage::create([
            'coins' => $data['coins'],
            'price' => $data['price'],
            'currency' => $data['currency'] ?? 'USD',
        ]);

        return response()->json(['message' => 'Package created', 'data' => $pkg], 201);
    }


    public function updatePackage(Request $request, $id)
    {
        // $this->authorize('update', CoinPackage::class);
        $pkg = CoinPackage::findOrFail($id);

        $data = $request->validate([
            'coins' => ['sometimes', 'integer', 'min:1'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
        ]);

        $pkg->update($data);
        return response()->json(['message' => 'Package updated', 'data' => $pkg]);
    }

    public function deletePackage($id)
    {
        // $this->authorize('delete', CoinPackage::class);
        $pkg = CoinPackage::findOrFail($id);
        $pkg->delete();

        return response()->json(['message' => 'Package deleted']);
    }





    public function purchase(Request $request)
    {
        $data = $request->validate([
            'coin_package_id' => ['required', 'exists:coin_packages,id'],
        ]);

        $user = auth()->user();
        $package = CoinPackage::findOrFail($data['coin_package_id']);

        DB::transaction(function () use ($user, $package) {

            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();
            if (!$wallet) {
                $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 0]);

                $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();
            }


            $wallet->balance = (int) $wallet->balance + (int) $package->coins;
            $wallet->save();


            CoinTransaction::create([
                'sender_id' => null,
                'receiver_id' => $user->id,
                'coin_package_id' => $package->id,
                'post_id' => null,
                'event_id' => null,
                'coins' => $package->coins,
                'type' => 'purchase',
                'message' => 'Package purchase: ' . $package->coins . ' coins',
            ]);
        });

        return response()->json(['message' => 'Coins purchased successfully']);
    }





}