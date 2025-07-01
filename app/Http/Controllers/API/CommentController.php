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

class CommentController extends Controller {
    public function store(Request $request, $postId) {
        $request->validate(['body' => 'required|string', 'emojis' => 'nullable|array']);
        $comment = Comment::create([
            'post_id' => $postId,
            'user_id' => auth()->id(),
            'body' => $request->body,
            'emojis' => $request->emojis
        ]);
        return response()->json($comment);
    }

    // public function like($id) {
    //     CommentLike::firstOrCreate(['comment_id' => $id, 'user_id' => auth()->id()]);
    //     return response()->json(['message' => 'Comment liked']);
    // }
    public function like($id) {
        $like = CommentLike::where('comment_id', $id)->where('user_id', auth()->id())->first();
        if ($like) {
            $like->delete();
            return response()->json(['message' => 'Unliked']);
        } else {
            CommentLike::create(['comment_id' => $id, 'user_id' => auth()->id()]);
            return response()->json(['message' => 'Liked']);
        }
    }

    public function reply(Request $request, $id) {
        $request->validate(['body' => 'required|string', 'emojis' => 'nullable|array']);
        $reply = Reply::create([
            'comment_id' => $id,
            'user_id' => auth()->id(),
            'body' => $request->body,
            'emojis' => $request->emojis
        ]);
        return response()->json($reply);
    }
}
