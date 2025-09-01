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
use Illuminate\Support\Facades\Validator;


class EventCategoryController extends Controller
{
    public function index()
    {
        $perPage = request()->get('per_page', 10);
        $categories = EventCategory::paginate($perPage);
        return $this->sendResponse('Event categories fetched successfully', $categories);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors()->all(), 422);
        }
        $category = EventCategory::create(['name' => $request->name]);

        return $this->sendResponse('Event category created successfully', $category, 201);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors()->all(), 422);
        }
        $category = EventCategory::find($id);
        if (!$category) {
            return $this->sendError('Event category not found', 'No Event found for the id : ' . $id, 404);
        }
        $category->update(['name' => $request->name]);

        return $this->sendResponse('Event category updated successfully', $category);
    }

    public function destroy($id)
    {
        $category = EventCategory::find($id);
        if (!$category) {
            return $this->sendError('Event category not found', 'No Event found for the id : ' . $id, 404);
        }
        $category->delete();

        return $this->sendResponse('Event category deleted successfully');
    }
}
