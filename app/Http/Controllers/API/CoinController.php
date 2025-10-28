<?php

namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CoinPackage;
use App\Models\CoinTransaction;
use App\Models\Event;
use App\Models\Post;
use App\Models\Card;
use App\Models\User;
use App\Models\Notification;
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





    // public function purchase(Request $request)
    // {
    //     try {
    //         $data = $request->validate([
    //             'coin_package_id' => ['required', 'exists:coin_packages,id'],
    //             'card_id' => ['required', 'exists:cards,id'],
    //         ]);

    //         $user = auth()->user();

    //         $card = Card::where('id', $data['card_id'])
    //             ->where('user_id', $user->id)
    //             ->first();

    //         if (!$card) {
    //             return $this->sendError('Card not found or does not belong to you', [], 404);
    //         }

    //         $package = CoinPackage::find($data['coin_package_id']);
    //         if (!$package) {
    //             return $this->sendError('Coin package not found', [], 404);
    //         }

    //         DB::beginTransaction();

    //         try {
    //             $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();
    //             if (!$wallet) {
    //                 $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 0]);
    //                 $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();
    //             }

    //             $wallet->balance = (int) $wallet->balance + (int) $package->coins;
    //             $wallet->save();

    //             CoinTransaction::create([
    //                 'sender_id' => null,
    //                 'receiver_id' => $user->id,
    //                 'coin_package_id' => $package->id,
    //                 'post_id' => null,
    //                 'event_id' => null,
    //                 'coins' => $package->coins,
    //                 'type' => 'purchase',
    //                 'message' => 'Purchased ' . $package->coins . ' coins using card #' . $card->id,
    //             ]);

    //             // Send notification
    //             Notification::create([
    //                 'user_id' => $user->id,
    //                 'title'   => 'Coin Purchase Successful',
    //                 'message' => 'You purchased ' . $package->coins . ' coins successfully.',
    //             ]);

    //             DB::commit();

    //             return $this->sendResponse('Coins purchased successfully', [
    //                 'wallet_balance' => $wallet->balance,
    //                 'package' => $package->only(['id', 'name', 'coins', 'price']),
    //             ]);
    //         } catch (\Exception $e) {
    //             DB::rollBack();
    //             return $this->sendError('Purchase failed', ['error' => $e->getMessage()], 500);
    //         }
    //     } catch (\Illuminate\Validation\ValidationException $e) {
    //         return $this->sendError('Validation Error', $e->errors(), 422);
    //     } catch (\Exception $e) {
    //         return $this->sendError('Something went wrong', ['error' => $e->getMessage()], 500);
    //     }
    // }
    public function purchase(Request $request)
{
    try {
        $data = $request->validate([
            'coins'  => ['required', 'integer', 'min:1'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $user = auth()->user();

        DB::beginTransaction();

        try {
            $package = CoinPackage::where('coins', $data['coins'])->first();

            if (!$package) {
                $package = CoinPackage::create([
                    'coins' => $data['coins'],
                    'price' => $data['amount'],
                    'currency' => 'USD',
                ]);
            }
             $wallet = Wallet::firstOrCreate(
                ['user_id' => $user->id],
                ['balance' => 0]
            );

            $wallet->balance += $data['coins'];
            $wallet->save();

            $transaction = CoinTransaction::create([
                'sender_id'       => null,
                'receiver_id'     => $user->id,
                'coin_package_id' => $package->id,
                'post_id'         => null,
                'event_id'        => null,
                'coins'           => $data['coins'],
                'type'            => 'purchase',
                'message'         => 'Purchased ' . $data['coins'] . ' coins for amount ' . $data['amount'],
            ]);

            Notification::create([
                'user_id' => $user->id,
                'receiver_id' => $user->id,
                'title'   => 'Coins Purchased Successfully',
                'message' => 'You purchased ' . $data['coins'] . ' coins for ' . $data['amount'] . ' $.',
                'data' => [
                    'wallet_id' => $wallet->id,
                    'transaction_id' => $transaction->id
                ],
                'type'=> 'coinPurchase'
            ]);

            DB::commit();

            return $this->sendResponse('Coins purchased successfully', [
                'transaction' => $transaction,
                'package'     => $package->only(['coins', 'price', 'created_at', 'updated_at']),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Purchase failed', ['error' => $e->getMessage()], 500);
        }

    } catch (\Illuminate\Validation\ValidationException $e) {
        return $this->sendError('Validation Error', $e->errors(), 422);
    } catch (\Exception $e) {
        return $this->sendError('Something went wrong', ['error' => $e->getMessage()], 500);
    }
}







}
