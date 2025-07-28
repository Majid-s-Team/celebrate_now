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

        if (! User::find($id)) {
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
            return $this->sendResponse('Unfollowed');
        } else {
            Follow::create([
                'follower_id' => auth()->id(),
                'following_id' => $id
            ]);
            return $this->sendResponse('Followed');
        }
    }

    public function followers()
    {
        $perPage = request()->get('per_page', 10);

        $followers = auth()->user()
            ->followers()
            ->with('follower')
            ->paginate($perPage);

        return $this->sendResponse('Followers fetched successfully', $followers);
    }


    public function following()
    {
        $perPage = request()->get('per_page', 10);

        $following = auth()->user()
            ->following()
            ->with('following')
            ->paginate($perPage);

        return $this->sendResponse('Following fetched successfully', $following);
    }

    // returns all the followings and followers of the authenticated user
   public function myNetwork()
    {
        $perPage = request()->get('per_page', 10);

        $followers = auth()->user()
            ->followers()
            ->with('follower')
            ->paginate($perPage, ['*'], 'followers_page');

        $following = auth()->user()
            ->following()
            ->with('following')
            ->paginate($perPage, ['*'], 'following_page');

        return $this->sendResponse('Network fetched successfully', [
            'followers' => $followers,
            'following' => $following,
        ]);
    }

}
