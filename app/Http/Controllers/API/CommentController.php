<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Post;
use App\Models\PostLike;
use App\Models\PostTag;
use App\Models\Comment;
use App\Models\CommentLike;
use App\Models\ReplyLike;
use App\Models\Reply;
use App\Models\Follow;
use App\Models\EventCategory;
use Illuminate\Support\Facades\Auth;

class CommentController extends Controller {

    public function store(Request $request, $postId) {
        // $request->validate(['body' => 'required|string', 'emojis' => 'nullable|array']);

        //manual validation due to sendError custom method for error responses
        $validator = \Validator::make($request->all(), [
            'body' => 'required|string',
            'emojis' => 'nullable|array'
        ]);
        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        //post check and validation
        if (!Post::where('id', $postId)->exists()) {
            return $this->sendError('Post not found', [], 404);
        }

        $comment = Comment::create([
            'post_id' => $postId,
            'user_id' => auth()->id(),
            'body' => $request->body,
            'emojis' => $request->emojis
        ]);
        // return response()->json($comment);
        return $this->sendResponse('Comment posted successfully', $comment, 201);

    }

    // public function like($id) {
    //     CommentLike::firstOrCreate(['comment_id' => $id, 'user_id' => auth()->id()]);
    //     return response()->json(['message' => 'Comment liked']);
    // }

    public function like($id) {
        // Check if the comment exists
        // If not, return an error response
        if (!Comment::where('id', $id)->exists()) {
            return $this->sendError('Comment not found', [], 404);
        }

        $like = CommentLike::where('comment_id', $id)->where('user_id', auth()->id())->first();
        if ($like) {
            $like->delete();
            // return response()->json(['message' => 'Unliked']);
            return $this->sendResponse('Comment unliked');

        } else {
            CommentLike::create(['comment_id' => $id, 'user_id' => auth()->id()]);
            // return response()->json(['message' => 'Liked']);
            return $this->sendResponse('Comment liked');

        }
    }

    public function reply(Request $request, $id) {

        // $request->validate(['body' => 'required|string', 'emojis' => 'nullable|array']);


        // Check if the comment exists
        // If not, return an error response
        if (!Comment::where('id', $id)->exists()) {
            return $this->sendError('Comment not found', [], 404);
        }

        //manual validation due to sendError custom method for error responses

        $validator = \Validator::make($request->all(), [
            'body' => 'required|string',
            'emojis' => 'nullable|array'
        ]);
        if($validator->fails()){
            return $this->sendError('Validation Error', $validator->errors(),422);
        }


        $reply = Reply::create([
            'comment_id' => $id,
            'user_id' => auth()->id(),
            'body' => $request->body,
            'emojis' => $request->emojis
        ]);
        // return response()->json($reply);
        return $this->sendResponse('Reply posted successfully', $reply, 201);

    }

//Returns the comments on the of a post
public function postComments(Request $request, $id)
{
    $perPage = $request->get('per_page', 10);
    $post = Post::find($id);

    if (!$post) {
        return $this->sendError('Post not found', [], 404);
    }

    // Fetch comments with all needed relationships + likes_count + replies_count
    $commentsPaginated = $post->comments()
        ->with([
            'user',
            'likes',
            'replies' => function ($query) {
                $query->with(['user', 'likes'])->withCount('likes'); // replies.likes_count
            }
        ])
        ->withCount(['likes', 'replies']) // comment.likes_count, comment.replies_count
        ->paginate($perPage);

    // Transform comments
    $commentsPaginated->getCollection()->transform(function ($comment) {
        $comment->is_liked = $comment->likes->contains('user_id', auth()->id());

        $comment->replies->transform(function ($reply) {
            $reply->is_liked = $reply->likes->contains('user_id', auth()->id());
            return $reply;
        });

        return $comment;
    });

    // Total comment count on the post
    $commentsCount = $post->comments()->count();

    // Final formatted response
    $commentsData = [
        'data' => $commentsPaginated->items(),
        'pagination' => [
            'current_page' => $commentsPaginated->currentPage(),
            'last_page' => $commentsPaginated->lastPage(),
            'per_page' => $commentsPaginated->perPage(),
            'total' => $commentsPaginated->total(),
            'has_more_pages' => $commentsPaginated->hasMorePages(),
        ]
    ];

    return $this->sendResponse('Post comments fetched successfully.', [
        'post' => $post,
        'comments_count' => $commentsCount,
        'comments' => $commentsData,
    ]);
}




    public function likeReply($id)
    {
        // Check if the reply exists
        if (!Reply::where('id', $id)->exists()) {
            return $this->sendError('Reply not found', [], 404);
        }

        $like = ReplyLike::where('reply_id', $id)->where('user_id', auth()->id())->first();
        if ($like) {
            $like->delete();
            return $this->sendResponse('Reply unliked');
        } else {
            ReplyLike::create(['reply_id' => $id, 'user_id' => auth()->id()]);
            return $this->sendResponse('Reply liked');
        }
    }

}
