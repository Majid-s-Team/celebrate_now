<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\UserOtp;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendOtpMail;
class OTPController extends Controller
{
    public function getOtp(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        try {
            $user = User::where('email', $request->email)->first();
            //change to sendError due t o custom method in Controller for returning error responses
            if (!$user) {
                return $this->sendError('User not found', null, 404);
            }

            $otp = rand(100000, 999999);
            
            UserOtp::updateOrCreate(
                ['user_id' => $user->id],
                ['otp' => $otp, 'expires_at' => now()->addMinutes(10)]
            );
            
            Mail::to($user->email)->send(new SendOtpMail($user, $otp));
            // return $this->sendResponse('OTP sent (dev)', ['otp' => $otp], 200);
            return $this->sendResponse('OTP sent successfully to email.', [], 200);


        } catch (\Exception $e) {
            return $this->sendResponse('Server Error', ['error' => $e->getMessage()], 500);
        }
    }


    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required',
            'password' => 'required|confirmed|min:6'
        ]);

        $user = User::where('email', $request->email)->firstOrFail();
        $otpRecord = UserOtp::where('user_id', $user->id)->where('otp', $request->otp)->first();

        if (!$otpRecord || Carbon::parse($otpRecord->expires_at)->isPast()) {
            return $this->sendError('Invalid or expired OTP', [], 400);
            // return response()->json(['message' => 'Invalid or expired OTP'], 400);
        }

        $user->update(['password' => Hash::make($request->password)]);
        $otpRecord->delete();

        // return response()->json(['message' => 'Password reset successful']);
        return $this->sendResponse('Password reset successful');

    }
    public function verifyOtpToActivateAccount(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|digits:6',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return $this->sendError('User not found', [], 404);
        }

        $otpRecord = UserOtp::where('user_id', $user->id)
            ->where('otp', $request->otp)
            ->first();

        if (!$otpRecord) {
            return $this->sendError('Invalid OTP', [], 400);
        }

        if (Carbon::parse($otpRecord->expires_at)->isPast()) {
            return $this->sendError('OTP has expired', [], 400);
        }

        $user->is_active = 1;
        $user->email_verified_at = now();
        $user->save();

        $otpRecord->delete();

        return $this->sendResponse('Account activated successfully', [
            'token' => $user->createToken('API Token')->plainTextToken,
            'user' => $user
        ]);
    }

}
