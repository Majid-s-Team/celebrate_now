<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\PostTag;
use App\Models\User;
use App\Models\Comment;
use App\Models\CommentLike;
use App\Models\Reply;
use App\Models\Follow;
use App\Models\EventCategory;
use Illuminate\Support\Facades\Auth;

class FollowController extends Controller
{
    // public function follow($id) {
    //     if (auth()->id() == $id) return response()->json(['message' => 'Cannot follow yourself'], 400);
    //     Follow::firstOrCreate(['follower_id' => auth()->id(), 'following_id' => $id]);
    //     return response()->json(['message' => 'Followed']);
    // }

    // public function unfollow($id) {
    //     Follow::where(['follower_id' => auth()->id(), 'following_id' => $id])->delete();
    //     return response()->json(['message' => 'Unfollowed']);
    // }

    public function toggleFollow($id)
    {
        // Check if the user exists
        // If not, return an error response

        if (!User::find($id)) {
            return $this->sendError('User not found', [], 404);
        }
        if (auth()->id() == $id) {
            return $this->sendResponse('Cannot follow yourself', null, 400);
        }

        $follow = Follow::where('follower_id', auth()->id())
            ->where('following_id', $id)
            ->first();

        if ($follow) {
            $follow->delete();
            return $this->sendResponse('User unfollowed successfully.');
        } else {
            Follow::create([
                'follower_id' => auth()->id(),
                'following_id' => $id
            ]);
            return $this->sendResponse('User followed successfully.');
        }
    }

    public function followers()
{
    $perPage = request()->get('per_page', 10);
    $name = request()->get('name');

    $loggedInUser = auth()->user();

    // Base query: only those followers whose user exists (not null or blocked)
    $query = $loggedInUser
        ->followers()
        ->whereHas('follower') // Ensures follower exists
        ->with('follower');

    // Optional: filter by name
    if ($name) {
        $query->whereHas('follower', function ($q) use ($name) {
            $q->where('first_name', 'like', "%{$name}%")
              ->orWhere('last_name', 'like', "%{$name}%");
        });
    }

    // Paginate after filtering
    $followers = $query->paginate($perPage);

    // If no followers after filtering, return response
    if ($followers->isEmpty()) {
        return $this->sendResponse('User has no followers', [], 200);
    }

    // Get IDs of users that the logged-in user is following
    $followingIds = $loggedInUser->following()->pluck('following_id')->toArray();

    // Add is_followed flag to each
    $followers->getCollection()->transform(function ($item) use ($followingIds) {
        $followerUser = $item->follower;
        $followerUser->is_followed = in_array($followerUser->id, $followingIds);
        return $item;
    });

    return $this->sendResponse('Followers fetched successfully', $followers);
}



public function following()
{
    $perPage = request()->get('per_page', 10);
    $name = request()->get('name');
    $loggedInUser = auth()->user();

    // Step 1: Base query for valid followings only
    $query = $loggedInUser
        ->following()
        ->whereHas('following') // only where following exists
        ->with('following');

    // Step 2: Apply name filter if needed
    if ($name) {
        $query->whereHas('following', function ($q) use ($name) {
            $q->where('first_name', 'like', "%{$name}%")
              ->orWhere('last_name', 'like', "%{$name}%");
        });
    }

    // Step 3: Paginate after filtering
    $following = $query->paginate($perPage);

    // Step 4: Check empty pagination
    if ($following->isEmpty()) {
        return $this->sendResponse('User has no Followings', [], 200);
    }

    // Step 5: Find IDs of users who follow back
    $followerIds = $loggedInUser->followers()->pluck('follower_id')->toArray();

    // Step 6: Add is_followed_back flag
    $following->getCollection()->transform(function ($item) use ($followerIds) {
        $followingUser = $item->following;
        $followingUser->is_followed_back = in_array($followingUser->id, $followerIds);
        return $item;
    });

    return $this->sendResponse('Following fetched successfully', $following);
}






    // returns all the followings and followers of the authenticated user

    // modified to include proper pagination , club the followers and following into a single response.



   public function myNetwork()
{
    $perPage = request()->get('per_page', 10);
    $page = request()->get('page', 1);

    // Followers (Only non-null)
    $followersQuery = auth()->user()->followers()->with('follower');
    $rawFollowers = $followersQuery->get()->filter(fn($item) => $item->follower !== null);
    $followersTotal = $rawFollowers->count();
    $followers = $rawFollowers->slice(($page - 1) * $perPage, $perPage)->values()->pluck('follower');

    // Following (Only non-null)
    $followingQuery = auth()->user()->following()->with('following');
    $rawFollowing = $followingQuery->get()->filter(fn($item) => $item->following !== null);
    $followingTotal = $rawFollowing->count();
    $following = $rawFollowing->slice(($page - 1) * $perPage, $perPage)->values()->pluck('following');

    // Combine totals for shared pagination
    $combinedTotal = max($followersTotal, $followingTotal);
    $pagination = [
        'current_page' => (int) $page,
        'last_page' => (int) ceil($combinedTotal / $perPage),
        'per_page' => (int) $perPage,
        'total' => (int) $combinedTotal,
        'has_more_pages' => $page < ceil($combinedTotal / $perPage),
    ];

    return $this->sendResponse('Network fetched successfully', [
        'followers' => $followers,
        'following' => $following,
        'pagination' => $pagination,
    ]);
}



}
