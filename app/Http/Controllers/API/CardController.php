<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Card;
use Exception;

class CardController extends Controller
{
    public function index()
    {
        try {
            $user = auth()->user();
            $cards = Card::where('user_id', $user->id)->get();

            return $this->sendResponse('User cards', $cards);
        } catch (Exception $e) {
            return $this->sendError('Failed to fetch cards', [$e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'card_holder_name' => 'required|string|max:100',
                'card_number' => 'required|string|min:12|max:19',
                'expiry_month' => 'required|string|max:2',
                'expiry_year' => 'required|string|max:4',
                'cvv' => 'required|string|min:3|max:4',
                'card_type' => 'nullable|string|max:20',
            ]);

            $user = auth()->user();

            $card = Card::create([
                'user_id' => $user->id,
                'card_holder_name' => $data['card_holder_name'],
                'card_number' => encrypt($data['card_number']),
                'expiry_month' => $data['expiry_month'],
                'expiry_year' => $data['expiry_year'],
                'cvv' => encrypt($data['cvv']),
                'card_type' => $data['card_type'] ?? 'credit',
            ]);

            return $this->sendResponse('Card added successfully', $card, 201);
        } catch (Exception $e) {
            return $this->sendError('Failed to add card', [$e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $user = auth()->user();
            $card = Card::where('id', $id)->where('user_id', $user->id)->firstOrFail();

            $data = $request->validate([
                'card_holder_name' => 'sometimes|string|max:100',
                'card_number' => 'sometimes|string|min:12|max:19',
                'expiry_month' => 'sometimes|string|max:2',
                'expiry_year' => 'sometimes|string|max:4',
                'cvv' => 'sometimes|string|min:3|max:4',
                'card_type' => 'sometimes|string|max:20',
            ]);

            if (isset($data['card_number'])) {
                $data['card_number'] = encrypt($data['card_number']);
            }
            if (isset($data['cvv'])) {
                $data['cvv'] = encrypt($data['cvv']);
            }

            $card->update($data);

            return $this->sendResponse('Card updated successfully', $card);
        } catch (Exception $e) {
            return $this->sendError('Failed to update card', [$e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $user = auth()->user();
            $card = Card::where('id', $id)->where('user_id', $user->id)->firstOrFail();
            $card->delete();

            return $this->sendResponse('Card deleted successfully');
        } catch (Exception $e) {
            return $this->sendError('Failed to delete card', [$e->getMessage()], 500);
        }
    }
}
