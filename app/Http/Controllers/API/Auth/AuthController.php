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
use Illuminate\Support\Facades\Validator;



class AuthController extends Controller

{
    use ImageUploadTrait;

      public function register(Request $request)
{
    // Manual validation so we can catch errors and use sendError
    $validator = \Validator::make($request->all(), [
        'first_name' => 'required|string',
        'last_name' => 'required|string',
        'email' => 'required|email|unique:users',
        'contact_no' => 'nullable|string|unique:users',
        'profile_type' => ['required', \Illuminate\Validation\Rule::in(['private', 'public'])],
        'dob' => 'nullable|date',
        'password' => 'required|confirmed|min:6',
        'profile_image' => 'nullable|url',
    ]);

    if ($validator->fails()) {
        return $this->sendError('Validation Error', $validator->errors()->all(), 422);
    }

    /* Check if the user already exists or was deactivated */

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
        'password' => \Hash::make($request->password),
        'role' => 'user',
        'profile_image' => $request->profile_image,
        'is_active' => true,
    ]);

    // return response()->json([
    //     'token' => $user->createToken('API Token')->plainTextToken,
    //     'user' => $user
    // ], 201);
    // Use the generic response method from the base controller
    // to maintain consistency across the application
    // This will return a JSON response with the message, data, and status code
    return $this->sendResponse('User registered successfully', [
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
            // return response()->json(['message' => 'Invalid credentials'], 401);

        // return $this->sendError('Validation Error', $validator->errors()->all(), 422);
        return $this-> sendError('Invalid credentials', [], 401);

        }

        if (!$user->is_active) {
            return response()->json(['message' => 'Account is deactivated. Contact support.'], 403);
        }

        return $this->sendResponse('Login successful', [
            'token' => $user->createToken('API Token')->plainTextToken,
            'user' => $user
        ]);

        
    }

       public function updateProfile(Request $request)
{
    $user = auth()->user();

    if (!$user) {
        return $this->sendError('User not authenticated', [], 401);
    }

    // Manual validation
    $validator = Validator::make($request->all(), [
        'first_name'    => 'required|string',
        'last_name'     => 'required|string',
        'contact_no'    => ['nullable', 'string', Rule::unique('users')->ignore($user->id)],
        'profile_type'  => ['required', Rule::in(['private', 'public'])],
        'dob'           => 'nullable|date',
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
        $validator = Validator::make($request->all(), [
          'key'   => 'required|string|in:profile,cover,post,gallery,logo',
           'image' => 'required|image|max:2048',
       ]);
          if ($validator->fails()) {
        return $this->sendError('Validation failed', $validator->errors(), 422);
       }
        $url = $this->uploadImageByKey($request->file('image'), $request->key);
        return $this->sendResponse('Image uploaded successfully', ['url' => $url], 201);


        
    }

 public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return $this->sendResponse('Logged out successfully');

    }

    public function profile()
    {
        // return response()->json(auth()->user());
        return $this->sendResponse('Profile fetched', auth()->user());

    }

      public function changePassword(Request $request)

    {
        


        $validator = Validator::make($request->all(), [
        'old_password'              => 'required',
        'new_password'              => 'required|min:6',
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

        return $this->sendResponse('Password updated successfully',$request->all(), 200);

    }

    public function deactivate()
    {
        $user = auth()->user();
        $user->is_active = false;
        $user->tokens()->delete();
        $user->save();

        // return response()->json(['message' => 'Account deactivated']);
        return $this->sendResponse('Account deactivated');

    }






















   

  

   
    
  

    

  

    
}





