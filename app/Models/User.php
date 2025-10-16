<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\ExcludeBlockedUsersScope;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;


class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes,ExcludeBlockedUsersScope;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'platform_id',
        'platform_type',
        'device_type',
        'device_token',
        'contact_no',
        'profile_type',
        'profile_image',
        'password',
        'role',
        'is_active',
        'dob',

    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = ['email_verified_at' => 'datetime'];


    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function taggedPosts()
    {
        return $this->hasMany(PostTag::class);
    }

    public function postLikes()
    {
        return $this->hasMany(PostLike::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function commentLikes()
    {
        return $this->hasMany(CommentLike::class);
    }

    public function replies()
    {
        return $this->hasMany(Reply::class);
    }

    public function followers()
    {
        return $this->hasMany(Follow::class, 'following_id');
    }

    public function following()
    {
        return $this->hasMany(Follow::class, 'follower_id');
    }
    public function wallet()
{
    return $this->hasOne(Wallet::class);
}

public function sentTransactions()
{
    return $this->hasMany(CoinTransaction::class, 'sender_id');
}

public function receivedTransactions()
{
    return $this->hasMany(CoinTransaction::class, 'receiver_id');
}

 public function blockedUsers()
{
    return $this->hasMany(UserBlock::class, 'blocker_id');
}

public function blockers()
{
    return $this->hasMany(UserBlock::class, 'blocked_id');
}

public static function socialUser(array $params)
{
    // 1️⃣ Check user by platform info
    $user = self::where('platform_type', $params['platform_type'] ?? null)
        ->where('platform_id', $params['platform_id'] ?? null)
        ->whereNull('deleted_at')
        ->first();

        // dd(vars: $user);

    // 2️⃣ If not found, check by email (for Google usually)
    if (!$user && !empty($params['email'])) {
        $user = self::where('email', $params['email'])
            ->whereNull('deleted_at')
            ->first();
    }

    // 3️⃣ Handle image (optional)
    // $uploadedImagePath = null;
    // if (!empty($params['image_url'])) {
    //     try {
    //         $imageContent = @file_get_contents($params['image_url']);
    //         if ($imageContent) {
    //             $filename = 'users/' . uniqid('social_') . '.jpg';
    //             Storage::disk('public')->put($filename, $imageContent);
    //             $uploadedImagePath = 'storage/' . $filename;
    //         }
    //     } catch (\Exception $e) {
    //         // Ignore image download failures silently
    //     }
    // }

    // 4️⃣ Create new user if not found
    if (!$user) {
        $password = Str::random(10);

        $user = User::create([
            'role'   => 'user',
            'first_name'      => $params['first_name'],
            'last_name'      => $params['last_name'],
            'email'           => $params['email'] ?? null,
            'password'        => Hash::make($password),
            'contact_no'       => $params['contact_no'] ?? null,
            'profile_image'       => $params['profile_image'],
            'platform_type'   => $params['platform_type'],
            'platform_id'     => $params['platform_id'],
            'device_type'     => $params['device_type'] ?? null,
            'device_token'    => $params['device_token'] ?? null,
            'is_active'          => 1,
            'created_at'      => Carbon::now(),
        ]);
    }
    else {
        // 5️⃣ Update user fields if already exists
        $updateData = [
            'first_name'         => $params['first_name'] ?? $user->first_name,
            'last_name'         => $params['last_name'] ?? $user->last_name,
            'email'        => $params['email'] ?? $user->email,
            'profile_image'    => $params['profile_image'] ?? $user->profile_image,
            'device_type'  => $params['device_type'] ?? $user->device_type,
            'device_token' => $params['device_token'] ?? $user->device_token,
            'updated_at'   => Carbon::now(),
        ];

        $user->update($updateData);
    }

    // 6️⃣ Return minimal object
    return (object) [
        'id'          => $user->id,
        'created_at'  => $user->created_at,
    ];
}



}
