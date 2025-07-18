<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

use App\Http\Controllers\API\Auth\AuthController;
use App\Http\Controllers\API\Auth\OTPController;
use App\Http\Controllers\API\PostController;
use App\Http\Controllers\API\CommentController;
use App\Http\Controllers\API\FollowController;
use App\Http\Controllers\API\EventCategoryController;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

    Route::post('/change-password', [AuthController::class, 'changePassword'])->middleware('auth:sanctum');
    Route::put('/update-profile', [AuthController::class, 'updateProfile'])->middleware('auth:sanctum');
    Route::get('/profile', [AuthController::class, 'profile'])->middleware('auth:sanctum');
    Route::delete('/deactivate', [AuthController::class, 'deactivate'])->middleware('auth:sanctum');
    Route::post('/upload-image', [AuthController::class, 'uploadImage']);

    Route::post('/get-otp', [OTPController::class, 'getOtp']);
    Route::post('/reset-password', [OTPController::class, 'resetPassword']);


Route::middleware('auth:sanctum')->group(function () {


//    Route::post('/follow/{id}', [FollowController::class, 'follow']);
//     Route::post('/unfollow/{id}', [FollowController::class, 'unfollow']);
    Route::post('/follow-toggle/{id}', [FollowController::class, 'toggleFollow']);

    Route::get('/followers', [FollowController::class, 'followers']);
    Route::get('/following', [FollowController::class, 'following']);
    Route::get('/my-network', [FollowController::class, 'myNetwork']);

    // Event Category Routes
    Route::apiResource('event-categories', EventCategoryController::class);

 // Post Routes
    Route::post('/posts', [PostController::class, 'store']);
    Route::get('/posts', [PostController::class, 'myPosts']);
    Route::get('/posts/{id}', [PostController::class, 'show']);
    // Route::post('/posts/{id}/like', [PostController::class, 'like']);
    Route::post('/posts/{id}/like', [PostController::class, 'like']);
    Route::get('/posts/{id}/liked-users', [PostController::class, 'likedUsers']);

   // Show Posts of Users I Follow (Public + Their Private)
    Route::get('/feed/following-posts', [PostController::class, 'followingPosts']);

    // Show All Posts (with filters)
    Route::get('/feed/all-posts', [PostController::class, 'allPosts']);

    // Show Post with Comments + Likes + Replies + Tagged Users
    Route::get('/posts/{id}/details', [PostController::class, 'postDetails']);


    Route::post('/posts/{id}/tag', [PostController::class, 'tagUsers']);
    Route::get('/posts/{id}/with-counts', [PostController::class, 'publicPostsWithFollowersFollowing']);

    // Comment Routes
    Route::post('/posts/{id}/comment', [CommentController::class, 'store']);
    Route::post('/comments/{id}/like', [CommentController::class, 'like']);



    Route::post('/comments/{id}/reply', [CommentController::class, 'reply']);
});


});
