<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Traits\ImageUploadTrait;

class AuthController extends Controller
{
        use ImageUploadTrait;


    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'contact_no' => 'nullable|string|unique:users',
            'profile_type' => ['required', Rule::in(['private', 'public'])],
            'password' => 'required|confirmed|min:6',
            'profile_image' => 'nullable|url', // Expecting uploaded URL
        ]);

        $exists = User::withTrashed()
            ->where(function ($q) use ($request) {
                $q->where('email', $request->email);
                if ($request->contact_no) {
                    $q->orWhere('contact_no', $request->contact_no);
                }
            })->first();

        if ($exists) {
            return response()->json(['message' => 'Account already exists or was deactivated. Contact support.'], 403);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'contact_no' => $request->contact_no,
            'profile_type' => $request->profile_type,
            'password' => Hash::make($request->password),
            'role' => 'user',
            'profile_image' => $request->profile_image,
            'is_active' => true,
        ]);

        return response()->json([
            'token' => $user->createToken('API Token')->plainTextToken,
            'user' => $user
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'Account is deactivated. Contact support.'], 403);
        }

        return response()->json([
            'token' => $user->createToken('API Token')->plainTextToken,
            'user' => $user
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user();
        // dd($request);

        $request->validate([
            'name' => 'nullable|string',
            'contact_no' => ['nullable', 'string', Rule::unique('users')->ignore($user->id)],
            'profile_type' => ['nullable', Rule::in(['private', 'public'])],
            'profile_image' => 'nullable|url',
        ]);

        $user->update($request->only('name', 'contact_no', 'profile_type', 'profile_image'));

        return response()->json(['message' => 'Profile updated', 'user' => $user]);
    }

   public function uploadImage(Request $request)
    {
        $request->validate([
            'key'   => 'required|string|in:profile,cover,post,gallery,logo',
            'image' => 'required|image|max:2048',
        ]);

        $url = $this->uploadImageByKey($request->file('image'), $request->key);

        return response()->json([
            'url' => $url,
            'message' => 'Image uploaded successfully'
        ], 201);
    }



    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }

    public function profile()
    {
        return response()->json(auth()->user());
    }



    public function changePassword(Request $request)
    {
        $request->validate([
            'old_password' => 'required',
            'new_password' => 'required|confirmed|min:6',
        ]);

        if (!Hash::check($request->old_password, auth()->user()->password)) {
            return response()->json(['message' => 'Incorrect old password'], 400);
        }

        auth()->user()->update([
            'password' => Hash::make($request->new_password)
        ]);

        return response()->json(['message' => 'Password updated successfully']);
    }

    public function deactivate()
    {
        $user = auth()->user();
        $user->is_active = false;
        $user->tokens()->delete();
        $user->save();

        return response()->json(['message' => 'Account deactivated']);
    }
}
