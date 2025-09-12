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
use App\Http\Controllers\API\PolicyController;
use App\Http\Controllers\API\EventController;
use App\Http\Controllers\API\PollController;
use App\Http\Controllers\API\CoinController;
use App\Http\Controllers\API\WalletController;
use App\Http\Controllers\API\TransactionController;
use App\Http\Controllers\API\CardController;
use App\Http\Controllers\API\NotificationController;


Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    // Route::post('/verify-otp', action: [AuthController::class, 'verifyOtp']);

    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

    Route::post('/change-password', [AuthController::class, 'changePassword'])->middleware('auth:sanctum');
    Route::put('/update-profile', [AuthController::class, 'updateProfile'])->middleware('auth:sanctum');
    Route::get('/profile', [AuthController::class, 'profile'])->middleware('auth:sanctum');
    Route::delete('/deactivate', [AuthController::class, 'deactivate'])->middleware('auth:sanctum');
    Route::post('/upload-image', [AuthController::class, 'uploadImage']);
    Route::delete('/delete-user/{id}', [AuthController::class, 'softDeleteUser'])->middleware('auth:sanctum');

    Route::post('/get-otp', [OTPController::class, 'getOtp']);
    Route::post('/reset-password', [OTPController::class, 'resetPassword']);
    Route::post('verify-otp-account', [OTPController::class, 'verifyOtpToActivateAccount']);



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
        Route::put('/posts/{id}', [PostController::class, 'update']);
        Route::delete('/posts/{id}', [PostController::class, 'destroy']);
        Route::post('/posts/{id}/report', [PostController::class, 'report']);
        Route::get('/reason', [PostController::class, 'reportReasons']);


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
        Route::get('/posts/{id}/comments', [CommentController::class, 'postComments']); // get comments for a post

        // Reply Routes

        Route::post('/replies/{id}/like', [CommentController::class, 'likeReply']);


        Route::post('/comments/{id}/reply', [CommentController::class, 'reply']);
        //Block/UnBlock route
        Route::post('block', [AuthController::class, 'block']);
        Route::get('getBlockList', [AuthController::class, 'viewBlockList']);
        Route::post('/comments/{id}/reply', [CommentController::class, 'reply']);

        Route::post('/events', [EventController::class, 'store']);
        Route::get('/events/{id}', [EventController::class, 'show']);
        Route::put('/events/{id}', [EventController::class, 'update']);
        Route::get('/events', [EventController::class, 'index']);
        Route::get('/events/{id}', [EventController::class, 'show']);
        Route::delete('/events/{id}', [EventController::class, 'destroy']);
        Route::post('/events/del-members',[EventController::class,'deleteMember']);

        Route::get('/events/{id}/group-members-for-vote', [EventController::class, 'groupMembersForVote']);
        Route::get('/events/{eventId}/members', [EventController::class, 'getEventMembers']);
        Route::get('/user/event-polls', [EventController::class, 'getUserEventPolls']);
        Route::post('/polls/vote', [PollController::class, 'vote']);
        Route::post('/polls/create', [PollController::class, 'createPoll']);
        Route::post('/polls/{pollId}/options/add', [PollController::class, 'addOption']);
        Route::post('/polls/vote', [PollController::class, 'vote']);
        Route::put('/polls/{pollId}', [PollController::class, 'updatePoll']);
        Route::delete('/polls/{pollId}', [PollController::class, 'deletePoll']);

        Route::get('/polls/{id?}', [PollController::class, 'show']);
        Route::get('/events/{eventId}/polls/results', [PollController::class, 'eventPollResults']);
        Route::get('/events/{eventId}/posts', [EventController::class, 'eventPosts']);

        Route::get('/coins/packages', [CoinController::class, 'listPackages']);
        Route::post('/coins/packages', [CoinController::class, 'createPackage']);
        Route::put('/coins/packages/{id}', [CoinController::class, 'updatePackage']);
        Route::delete('/coins/packages/{id}', [CoinController::class, 'deletePackage']);
        Route::get('/coins/wallet', [WalletController::class, 'myWallet']);


        Route::post('/coins/purchase', [CoinController::class, 'purchase']);
        Route::post('/coins/send', [TransactionController::class, 'send']);
        // Route::post('/coins/spend', [TransactionController::class, 'spend']);
        Route::get('/gifts', [TransactionController::class, 'gifts']);

        Route::get('cards', [CardController::class, 'index']);
        Route::post('cards', [CardController::class, 'store']);
        Route::put('cards/{id}', [CardController::class, 'update']);
        Route::delete('cards/{id}', [CardController::class, 'destroy']);

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::patch('notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::patch('notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('notifications/{id}', [NotificationController::class, 'destroy']);
        Route::delete('notifications', [NotificationController::class, 'clearAll']);
        Route::post('notifications', [NotificationController::class, 'create']);

        Route::post('coins/purchase', [CoinController::class, 'purchase']);
        Route::get('/coins/transactions/{eventId}', [TransactionController::class, 'eventTransactions']);
        Route::get('/with-donations', [WalletController::class, 'listWithDonations']);
        Route::get('/surprise-donations', [TransactionController::class, 'surpriseDonations']);
        Route::get('/with-surprise-contributions', [WalletController::class, 'listWithSurpriseContributionsAndTotal']);

    });


});


