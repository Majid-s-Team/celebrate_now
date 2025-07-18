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
}
