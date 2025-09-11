<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Models\UserOtp;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Traits\ImageUploadTrait;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class AuthController extends Controller
{
    use ImageUploadTrait;

    public function register(Request $request)
    {
        //manual validation due to sendError custom method for error responses
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|email|unique:users',
            'contact_no' => 'nullable|string|unique:users',
            'profile_type' => ['required', Rule::in(['private', 'public'])],
            'dob' => 'nullable|date',
            'password' => 'required|confirmed|min:6',
            'profile_image' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors()->all(), 422);
        }

        $exists = User::withTrashed()
            ->where(function ($q) use ($request) {
                $q->where('email', $request->email);
                if ($request->contact_no) {
                    $q->orWhere('contact_no', $request->contact_no);
                }
            })->first();

        if ($exists) {
            return $this->sendError('Account already exists or was deactivated. Contact support.', [], 403);
        }

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'contact_no' => $request->contact_no,
            'profile_type' => $request->profile_type,
            'dob' => $request->dob,
            'password' => Hash::make($request->password),
            'role' => 'user',
            'profile_image' => $request->profile_image,
            'is_active' => true,
        ]);

        return $this->sendResponse('User registered successfully', [
            'token' => $user->createToken('API Token')->plainTextToken,
            'user' => $user
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'otp' => 'nullable|string'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->sendError('Invalid credentials', [], 401);
        }

        // If user is inactive
        if (!$user->is_active) {
            $otp = rand(100000, 999999);

            UserOtp::updateOrCreate(
                ['user_id' => $user->id],
                ['otp' => $otp, 'expires_at' => now()->addMinutes(10)]
            );

            return $this->sendResponse('Account is deactivated. OTP sent to your email.', [
                'otp' => $otp,
                'token' => $user->createToken('API Token')->plainTextToken,
                'user' => $user
            ], 403);
        }

        // User is active: delete old tokens and login
        $user->tokens()->delete();
        $token = $user->createToken('API Token')->plainTextToken;

        return $this->sendResponse('Login successful', [
            'token' => $token,
            'user' => $user
        ]);
    }




    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        if (!$user) {
            return $this->sendError('User not authenticated', [], 401);
        }

        //changed first_name and last_name to required strings
        //added contact_no validation to allow null but unique if provided
        //added profile_type validation to ensure it is either 'private' or 'public'

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'contact_no' => ['nullable', 'string', Rule::unique('users')->ignore($user->id)],
            'profile_type' => ['required', Rule::in(['private', 'public'])],
            'dob' => 'nullable|date',
            'profile_image' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors(), 422);
        }

        $user->update($validator->validated());

        return $this->sendResponse('Profile updated', $user);
    }
    public function uploadImage(Request $request)
    {
        $key = $request->input('key');

        // Dynamic validation rules based on 'key'
        $rules = [
            'key' => 'required|string|in:profile,cover,post,gallery,logo,video',
        ];

        if ($key === 'post') {
            $rules['image'] = 'required|array';
            $rules['image.*'] = 'required|file|mimes:jpeg,png,jpg,gif,mp4,mov,avi,webm,mkv,flv,wmv,mpeg,m4v,3gp|max:1048576';
        } else {
            $rules['image'] = 'required|file|mimes:jpeg,png,jpg,gif,mp4,mov,avi,webm,mkv,flv,wmv,mpeg,m4v,3gp|max:1048576';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors(), 422);
        }

        $uploadedUrls = [];
        $hasImage = false;
        $hasVideo = false;

        $files = ($key === 'post') ? $request->file('image') : [$request->file('image')];

        foreach ($files as $file) {
            $url = $this->uploadImageByKey($file, $key);
            $uploadedUrls[] = $url;

            $mime = $file->getMimeType();
            if (str_starts_with($mime, 'image/')) {
                $hasImage = true;
            } elseif (str_starts_with($mime, 'video/')) {
                $hasVideo = true;
            }
        }

        $message = 'Media uploaded successfully';

        return $this->sendResponse($message, ['urls' => $uploadedUrls], 201);
    }



    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return $this->sendResponse('Logged out successfully');
    }

    public function profile()
    {
        return $this->sendResponse('Profile fetched', auth()->user());
    }

    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'old_password' => 'required',
            'new_password' => 'required|min:6',
            'new_password_confirmation' => 'required|same:new_password',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors(), 422);
        }

        if (!Hash::check($request->old_password, auth()->user()->password)) {
            return $this->sendError('Old password is incorrect', 'Invalid password', 401);
        }

        auth()->user()->update([
            'password' => Hash::make($request->new_password)
        ]);

        return $this->sendResponse('Password updated successfully', [], 200);
    }

    public function deactivate()
    {
        $user = auth()->user();
        $user->is_active = false;
        $user->tokens()->delete();
        $user->save();

        return $this->sendResponse('Account deactivated');
    }
}
