<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\PostTag;
use App\Models\Comment;
use App\Models\CommentLike;
use App\Models\Reply;
use App\Models\Follow;
use App\Models\EventCategory;
use Illuminate\Support\Facades\Auth;

class PostController extends Controller
{
    public function index()
    {
        // return Post::with(['user', 'tags.user', 'likes', 'comments.user'])->get();
        $posts = Post::with(['user', 'tags.user', 'likes', 'comments.user'])->get();
        return $this->sendResponse('Posts fetched successfully', $posts);
    }

    public function store(Request $request)
    {

        // dd(auth()->id(), auth()->user());

        $request->validate([
            'caption' => 'nullable|string',
            'photo' => 'nullable|string',
            'event_category_id' => 'nullable|exists:event_categories,id',
            'privacy' => 'required|in:public,private',
            'tag_user_ids' => 'nullable|array',
            'tag_user_ids.*' => 'exists:users,id'
        ]);

        $post = Post::create([
            'user_id' => auth()->id(),
            'caption' => $request->caption,
            'photo' => $request->photo,
            'event_category_id' => $request->event_category_id,
            'privacy' => $request->privacy
        ]);

        if ($request->has('tag_user_ids')) {
            foreach ($request->tag_user_ids as $userId) {
                PostTag::create(['post_id' => $post->id, 'user_id' => $userId]);
            }
        }

        // return response()->json($post);
        return $this->sendResponse('Post created successfully', $post, 201);

    }

    /**
     * Display the specified resource.
     * fucntion work : 
     * busniess lo

     */
    public function show($id)
    {
        // return Post::with(['user', 'tags.user', 'likes', 'comments.user'])->findOrFail($id);
        $post = Post::with(['user', 'tags.user', 'likes', 'comments.user'])->find($id);
        if (!$post) {
            return $this->sendError('Post not found', [], 404);
        }
        return $this->sendResponse('Post fetched successfully', $post);
    }

    // public function like($id) {
    //     PostLike::firstOrCreate(['user_id' => auth()->id(), 'post_id' => $id]);
    //     return response()->json(['message' => 'Post liked']);
    // }

    public function like($id)
    {
        $like = PostLike::where('user_id', auth()->id())->where('post_id', $id)->first();
        if ($like) {
            $like->delete();
            // return response()->json(['message' => 'Unliked']);
            return $this->sendResponse('Post unliked');

        } else {
            PostLike::create(['user_id' => auth()->id(), 'post_id' => $id]);
            // return response()->json(['message' => 'Liked']);
                        return $this->sendResponse('Post Liked');

        }
    }

    public function tagUsers(Request $request, $id)
    {
        $request->validate(['user_ids' => 'required|array']);
        foreach ($request->user_ids as $userId) {
            PostTag::firstOrCreate(['post_id' => $id, 'user_id' => $userId]);
        }
        // return response()->json(['message' => 'Users tagged']);
        return $this->sendResponse('Users tagged successfully');

    }
    public function likedUsers($id)
    {
        // return PostLike::with('user')->where('post_id', $id)->get();
        $users = PostLike::with('user')->where('post_id', $id)->get();
        return $this->sendResponse('Liked users fetched', $users);
    }

    public function myPosts()
    {
        // return Post::withCount(['likes', 'comments'])
        //     ->with(['tags.user'])
        //     ->where('user_id', auth()->id())
        //     ->get();
        $posts = Post::withCount(['likes', 'comments'])
            ->with(['tags.user'])
            ->where('user_id', auth()->id())
            ->get();

        return $this->sendResponse('My posts fetched', $posts);
    }

    public function myPostsByCategory($categoryId)
    {
        // return Post::withCount(['likes', 'comments'])
        //     ->with(['tags.user'])
        //     ->where('user_id', auth()->id())
        //     ->where('event_category_id', $categoryId)
        //     ->get();

           $posts = Post::withCount(['likes', 'comments'])
            ->with(['tags.user'])
            ->where('user_id', auth()->id())
            ->where('event_category_id', $categoryId)
            ->get();

        return $this->sendResponse('My posts by category fetched', $posts);
    }

    public function followingPosts(Request $request)
{
    $categoryId = $request->query('category_id');

    $followingIds = auth()->user()->following()->pluck('following_id');

    $query = Post::with(['user', 'tags.user', 'likes', 'comments.replies', 'comments.likes'])
        ->whereIn('user_id', $followingIds)
        ->where(function ($q) {
            $q->where('privacy', 'public')
              ->orWhere('privacy', 'private');
        });

    if ($categoryId) {
        $query->where('event_category_id', $categoryId);
    }

    $posts = $query->latest()->get()->map(function ($post) {
        return $this->formatPostWithCounts($post);
    });

    // return response()->json($posts);
            return $this->sendResponse('Following posts fetched', $posts);

}

public function allPosts(Request $request)
{
    $categoryId = $request->query('category_id');

    $query = Post::with(['user', 'tags.user', 'likes', 'comments.replies', 'comments.likes'])
        ->where('privacy', 'public');

    if ($categoryId) {
        $query->where('event_category_id', $categoryId);
    }

    $posts = $query->latest()->get()->map(function ($post) {
        return $this->formatPostWithCounts($post);
    });

    // return response()->json($posts);
            return $this->sendResponse('All public posts fetched', $posts);

}

public function postDetails($id)
{
    $post = Post::with(['user', 'tags.user', 'likes.user', 'comments.user', 'comments.replies.user', 'comments.likes.user'])
        ->findOrFail($id);

    // return response()->json($this->formatPostWithCounts($post));
            return $this->sendResponse('Post details fetched', $this->formatPostWithCounts($post));

}

private function formatPostWithCounts($post)
{
    return [
        'id' => $post->id,
        'user' => $post->user,
        'caption' => $post->caption,
        'photo' => $post->photo,
        'privacy' => $post->privacy,
        'event_category' => $post->category?->name,
        'tagged_users' => $post->tags->pluck('user'),
        'likes_count' => $post->likes->count(),
        'comments_count' => $post->comments->count(),
        'comments' => $post->comments->map(function ($comment) {
            return [
                'id' => $comment->id,
                'body' => $comment->body,
                'user' => $comment->user,
                'likes_count' => $comment->likes->count(),
                'replies_count' => $comment->replies->count(),
                'replies' => $comment->replies->map(function ($reply) {
                    return [
                        'id' => $reply->id,
                        'body' => $reply->body,
                        'user' => $reply->user,
                        'emojis' => $reply->emojis
                    ];
                }),
            ];
        })
    ];
}

}