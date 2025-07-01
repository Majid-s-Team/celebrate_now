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
class EventCategoryController extends Controller {
    public function index() {
        return EventCategory::all();
    }

    public function store(Request $request) {
        $request->validate(['name' => 'required|string']);
        return EventCategory::create(['name' => $request->name]);
    }

    public function update(Request $request, $id) {
        $request->validate(['name' => 'required|string']);
        $cat = EventCategory::findOrFail($id);
        $cat->update(['name' => $request->name]);
        return $cat;
    }

    public function destroy($id) {
        EventCategory::findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted']);
    }
}