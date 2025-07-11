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
class EventCategoryController extends Controller
{
    public function index()
    {
        $categories = EventCategory::all();
        return $this->sendResponse('Event categories fetched successfully', $categories);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string']);
        $category = EventCategory::create(['name' => $request->name]);

        return $this->sendResponse('Event category created successfully', $category, 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate(['name' => 'required|string']);
        $category = EventCategory::findOrFail($id);
        $category->update(['name' => $request->name]);

        return $this->sendResponse('Event category updated successfully', $category);
    }

    public function destroy($id)
    {
        $category = EventCategory::findOrFail($id);
        $category->delete();

        return $this->sendResponse('Event category deleted successfully');
    }
}