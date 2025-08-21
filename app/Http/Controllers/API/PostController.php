<?php

namespace App\Http\Controllers\API;
use App\Models\PostReport;
use App\Models\ReportReason;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\PostTag;
use App\Models\Comment;
use App\Models\CommentLike;
use App\Models\Reply;
use App\Models\Follow;
use App\Models\PostMedia;
use App\Models\EventCategory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\User;

class PostController extends Controller
{
public function index(Request $request)
{
    $perPage = $request->get('per_page', 10);

    $posts = Post::with(['user', 'tags.user', 'likes', 'comments.user'])
        ->whereHas('user', function ($q) {
            $q->where('is_active', 1);
        })
        ->whereNull('deleted_at')
        ->whereDoesntHave('reports', function ($q) {
            $q->where('user_id', auth()->id());
        })
        ->latest()
        ->paginate($perPage);

        $posts->getCollection()->transform(function ($post) {
            $post->is_liked = $post->likes->contains('user_id', auth()->id());

        $post->comments->transform(function ($comment) {
        $comment->is_liked = $comment->likes->contains('user_id', auth()->id());
        return $comment;
    });
            return $post;
        });

    return $this->sendResponse('Posts fetched successfully', $posts);
}


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'caption' => 'nullable|string',
            'photos' => 'nullable|array',
            'photos.*.url' => 'required|string',
            'photos.*.type' => 'required|in:image,video',

            'event_category_id' => 'nullable|exists:event_categories,id',
            'privacy' => 'required|in:public,private',
            'tag_user_ids' => 'nullable|array',
            'tag_user_ids.*' => 'exists:users,id'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors()->all(), 422);
        }

        $post = Post::create([
            'user_id' => auth()->id(),
            'caption' => $request->caption,
            'photo' => null,
            'event_category_id' => $request->event_category_id,
            'privacy' => $request->privacy
        ]);

        if ($request->has('tag_user_ids')) {
            foreach ($request->tag_user_ids as $userId) {
                PostTag::create(['post_id' => $post->id, 'user_id' => $userId]);
            }
        }

        if ($request->has('photos')) {
            foreach ($request->photos as $media) {
                PostMedia::create([
                    'post_id' => $post->id,
                    'url' => $media['url'],
                    'type' => $media['type']
                ]);
            }
        }

        return $this->sendResponse('Post created successfully', $post->load('media'), 201);
    }

   public function update(Request $request, $id)
{
    $post = Post::with(['media', 'tags'])->findOrFail($id);

    if ($post->user_id != auth()->id()) {
        return $this->sendError('Unauthorized', [], 403);
    }

    $validator = Validator::make($request->all(), [
        'caption' => 'nullable|string',
        'privacy' => 'nullable|in:public,private',
        'event_category_id' => 'nullable|exists:event_categories,id',
        'tag_user_ids' => 'nullable|array',
        'tag_user_ids.*' => 'exists:users,id',
        'photos' => 'nullable|array',
        'photos.*.url' => 'required|string',
        'photos.*.type' => 'required|in:image,video',
    ]);

    if ($validator->fails()) {
        return $this->sendError('Validation Error', $validator->errors()->all(), 422);
    }

    $post->update([
        'caption' => $request->caption,
        'privacy' => $request->privacy,
        'event_category_id' => $request->event_category_id,
    ]);

    // Sync tag users
    if ($request->has('tag_user_ids')) {
        PostTag::where('post_id', $post->id)->delete();
        foreach ($request->tag_user_ids as $userId) {
            PostTag::create([
                'post_id' => $post->id,
                'user_id' => $userId,
            ]);
        }
    }

    // Update photos - delete old, insert new
    if ($request->has('photos')) {
        PostMedia::where('post_id', $post->id)->delete();
        foreach ($request->photos as $media) {
            PostMedia::create([
                'post_id' => $post->id,
                'url' => $media['url'],
                'type' => $media['type'],
            ]);
        }
    }

    return $this->sendResponse('Post updated successfully', $post->fresh()->load(['media', 'tags']));
}

public function destroy($id)
{
    $post = Post::findOrFail($id);
    if ($post->user_id != auth()->id()) {
        return $this->sendError('Unauthorized', [], 403);
    }

    $post->delete();
    return $this->sendResponse('Post deleted successfully');
}
public function report(Request $request, $id)
{
    $validator = Validator::make($request->all(), [
        'reason_id' => 'nullable|exists:report_reasons,id',
        'other_reason' => 'nullable|string|max:1000'
    ]);

    if ($validator->fails()) {
        return $this->sendError('Validation Error', $validator->errors()->all(), 422);
    }

    $post = Post::findOrFail($id);
    if ($post->user_id == auth()->id()) {
        return $this->sendError('You cannot report your own post.', [], 403);
    }

    $alreadyReported = PostReport::where('post_id', $post->id)
        ->where('user_id', auth()->id())
        ->exists();

    if ($alreadyReported) {
        return $this->sendError('You have already reported this post.', [], 409);
    }

    $report = PostReport::create([
        'post_id' => $post->id,
        'user_id' => auth()->id(),
        'reason_id' => $request->reason_id,
        'other_reason' => $request->other_reason,
    ]);

    return $this->sendResponse('Post reported successfully', $report);
}

public function reportReasons(){
    $reasons = ReportReason::all();
    if ($reasons->isEmpty()) {
        return $this->sendError('No report reasons found', [], 404);
    }
    return $this->sendResponse('Report reasons fetched successfully', $reasons);
}


    // public function store(Request $request)

    // {

    //     // dd(auth()->id(), auth()->user());

    //     //manual validation due to sendError custom method for error responses
    //     $validator = Validator::make($request->all(), [
    //         'caption' => 'nullable|string',
    //         'photo' => 'nullable|string',
    //         'event_category_id' => 'nullable|exists:event_categories,id',
    //         'privacy' => 'required|in:public,private',
    //         'tag_user_ids' => 'nullable|array',
    //         'tag_user_ids.*' => 'exists:users,id'
    //     ]);

    //     if ($validator->fails()) {
    //         return $this->sendError('Validation Error', $validator->errors()->all(), 422);
    //     }

    //     // $request->validate([
    //     //     'caption' => 'nullable|string',
    //     //     'photo' => 'nullable|string',
    //     //     'event_category_id' => 'nullable|exists:event_categories,id',
    //     //     'privacy' => 'required|in:public,private',
    //     //     'tag_user_ids' => 'nullable|array',
    //     //     'tag_user_ids.*' => 'exists:users,id'
    //     // ]);

    //     $post = Post::create([
    //         'user_id' => auth()->id(),
    //         'caption' => $request->caption,
    //         'photo' => $request->photo,
    //         'event_category_id' => $request->event_category_id,
    //         'privacy' => $request->privacy
    //     ]);

    //     if ($request->has('tag_user_ids')) {
    //         foreach ($request->tag_user_ids as $userId) {
    //             PostTag::create(['post_id' => $post->id, 'user_id' => $userId]);
    //         }
    //     }

    //     // return response()->json($post);
    //     return $this->sendResponse('Post created successfully', $post, 201);


    // }

    /**
     * Display the specified resource.
     * fucntion work :
     * busniess lo

     */
    public function show($id)
    {
        // return Post::with(['user', 'tags.user', 'likes', 'comments.user'])->findOrFail($id);
        $post = Post::withCount(['likes', 'comments'])

            ->with([
                'user',
                'tags',
                'tags.user',
                'likes',
                'likes.user',
                'comments' => function ($query) {
                    $query->withCount('replies')
                        ->with(['user', 'likes.user', 'replies.user']);
                },
                'media'
            ])->find($id);



        if (!$post) {
            return $this->sendError('Post not found', [], 404);
        }
        $post->is_liked = $post->likes->contains('user_id', auth()->id());
         $post->comments->transform(function ($comment) {
        $comment->is_liked = $comment->likes->contains('user_id', auth()->id());
        return $comment;
    });

        return $this->sendResponse('Post fetched successfully', $post);
    }

    // public function like($id) {
    //     PostLike::firstOrCreate(['user_id' => auth()->id(), 'post_id' => $id]);
    //     return response()->json(['message' => 'Post liked']);
    // }

    public function like($id)
    {

        // Yeh line check karti hai ke current user ne is post ko like kiya hai ya nahi
        $like = PostLike::where('user_id', auth()->id())->where('post_id', $id)->first();

        // Agar post nahi milta to error return karte hain
        $post = Post::find($id);
        if (!$post) {
            return $this->sendError('Post not found', [], 404);
        }
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


    //new taguser function in order to use sendError custom method for error responses and also returns error if post not found
    public function tagUsers(Request $request, $id)
    {
        $post = Post::find($id);
        if (!$post) {
            return $this->sendError('No Record Found', 'Post id : ' . $id . ' not found', 404);
        }
        //manual validation due to sendError custom method for error responses
        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors()->all(), 422);
        }

        foreach ($request->user_ids as $userId) {
            PostTag::firstOrCreate(['post_id' => $id, 'user_id' => $userId]);
        }

        return $this->sendResponse('Users tagged successfully');
    }


    public function likedUsers($id)
    {
        // return PostLike::with('user')->where('post_id', $id)->get();
        $users = PostLike::with('user')->where('post_id', $id)->get();
        return $this->sendResponse('Liked users fetched', $users);
    }
    // modified to return the user object as well
   public function myPosts(Request $request)
{
    $user = auth()->user();

    $perPage = $request->query('per_page', 10);
    $page = $request->query('page', 1);

    $postsQuery = Post::withCount(['likes', 'comments'])
        ->with([
            'user',
            'tags',
            'tags.user',
            'likes',
            'likes.user',
            'comments' => function ($query) {
                $query->withCount('replies')
                      ->with(['user', 'likes.user', 'replies.user']);
            },
            'media'
        ])
        ->where('user_id', $user->id);

    $paginatedPosts = $postsQuery->latest()->paginate($perPage, ['*'], 'page', $page);

    $paginatedPosts->getCollection()->transform(function ($post) {
    $post->is_liked = $post->likes->contains('user_id', auth()->id());
       $post->comments->transform(function ($comment) {
            $comment->is_liked = $comment->likes->contains('user_id', auth()->id());
            return $comment;
        });
    return $post;
});

    return $this->sendResponse('My posts fetched', $paginatedPosts);
}


    // public function myPostsByCategory($categoryId)
    // {
    //     $posts = Post::with(['tags.user', 'comments.user', 'comments.likes', 'comments.replies', 'likes'])
    //         ->where('user_id', auth()->id())
    //         ->where('event_category_id', $categoryId)
    //         ->get()
    //         ->map(function ($post) {
    //             return [
    //                 'id' => $post->id,
    //                 'caption' => $post->caption,
    //                 'event_category_id' => $post->event_category_id,
    //                 'privacy' => $post->privacy,
    //                 'tagged_users' => $post->tags->pluck('user'),
    //                 'likes_count' => $post->likes->count(),
    //                 'comments_count' => $post->comments->count(),
    //                 'comments' => $post->comments->map(function ($comment) {
    //                     return [
    //                         'id' => $comment->id,
    //                         'body' => $comment->body,
    //                         'user' => $comment->user,
    //                         'likes_count' => $comment->likes->count(),
    //                         'replies_count' => $comment->replies->count(),
    //                         'replies' => $comment->replies->map(function ($reply) {
    //                             return [
    //                                 'id' => $reply->id,
    //                                 'body' => $reply->body,
    //                                 'user' => $reply->user,
    //                             ];
    //                         }),
    //                     ];
    //                 }),
    //             ];
    //         });

    //     return $this->sendResponse('My posts by category fetched', $posts);
    // }

    //function to show posts of users I follow
    // removed formatPostWithCounts function as it was not used
    // and added the ability to filter by category
    // and return the posts with likes, comments, replies, media, and counts

public function followingPosts(Request $request)
{
    $user = auth()->user();
    $categoryId = $request->query('category_id');
    $search = $request->query('search');
    $perPage = $request->query('per_page', 10);
    $page = $request->query('page', 1);

    $followingIds = $user->following()->pluck('following_id')->toArray();

    $postsQuery = Post::withCount(['likes', 'comments'])
        ->with([
            'user',
            'tags',
            'tags.user',
            'likes',
            'likes.user',
            'media',
            'comments' => function ($query) {
                $query->withCount('replies')
                      ->with([
                          'user',
                          'likes.user',
                          'replies.user'
                      ]);
            }
        ])
        ->whereIn('user_id', $followingIds)
    ->whereHas('user', fn ($q) => $q->where('is_active', 1))
    ->whereDoesntHave('reports', fn ($q) => $q->where('user_id', auth()->id()))
        ->where(function ($q) {
            $q->where('privacy', 'public')
              ->orWhere('privacy', 'private');
        });

    if ($categoryId) {
        $postsQuery->where('event_category_id', $categoryId);
    }

    if ($search) {
        $postsQuery->whereHas('user', function ($q) use ($search) {
            $q->where('first_name', 'like', "%{$search}%")
              ->orWhere('last_name', 'like', "%{$search}%");
        });
    }

    $paginatedPosts = $postsQuery->latest()->paginate($perPage, ['*'], 'page', $page);

    // Add isFollow and is_likedto each post
    $paginatedPosts->getCollection()->transform(function ($post) use ($followingIds) {
    $post->isFollow = in_array($post->user_id, $followingIds);
    $post->is_liked = $post->likes->contains('user_id', auth()->id());
     $post->comments->transform(function ($comment) {
        $comment->is_liked = $comment->likes->contains('user_id', auth()->id());
        return $comment;
    });
    return $post;
});

    // dd($paginatedPosts->getCollection());

    return $this->sendResponse('Following posts fetched', $paginatedPosts);
}




    // public function allPosts(Request $request)
// {
//     $categoryId = $request->query('category_id');

    //     $query = Post::with(['media'])
//         ->where('privacy', 'public');

    //     if ($categoryId) {
//         $query->where('event_category_id', $categoryId);
//     }




    //     $posts = $query->latest()->get()->map(function ($post) {
//         //changed the function from formatPostWithCounts to formatPostCount
//         // to include the post count as well
//         return $this->formatPostCount($post);
//     });

    //     // return response()->json($posts);
//             return $this->sendResponse('All public posts fetched', $posts);

    // }

    public function allPosts(Request $request)
{
    $user = auth()->user();
    $categoryId = $request->query('category_id');
    $search = $request->query('search');
    $perPage = $request->get('per_page', 10); // Default 10 per page

    $followingIds = $user ? $user->following()->pluck('following_id')->toArray() : [];

    $query = Post::withCount(['likes', 'comments'])
        ->with([
            'user',
            'tags',
            'tags.user',
            'likes',
            'likes.user',
            'comments' => function ($query) {
                $query->withCount('replies')
                    ->with(['user', 'likes.user', 'replies.user']);
            },
            'media'
        ])
    ->where('privacy', 'public')
        ->whereHas('user', fn ($q) => $q->where('is_active', 1))
        ->whereDoesntHave('reports', fn ($q) => $q->where('user_id', auth()->id()));
    if ($categoryId) {
        $query->where('event_category_id', $categoryId);
    }
    //search parameter fixed for searching for complete name
    if ($search) {
    $query->whereHas('user', function ($q) use ($search) {
        $q->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"])
          ->orWhereRaw("CONCAT(last_name, ' ', first_name) LIKE ?", ["%{$search}%"]) // reverse order (optional)
          ->orWhere('first_name', 'like', "%{$search}%")
          ->orWhere('last_name', 'like', "%{$search}%");
    });
}


    $posts = $query->latest()->paginate($perPage);

  $posts->getCollection()->transform(function ($post) use ($followingIds) {
    $post->isFollow = in_array($post->user_id, $followingIds);
    $post->is_liked = $post->likes->contains('user_id', auth()->id());
    return $post;
});

    return $this->sendResponse('All public posts fetched', $posts);
}


    public function postDetails($id)
    {
            $post = Post::withCount(['likes', 'comments'])
        ->where('privacy', 'public')
        ->whereHas('user', fn ($q) => $q->where('is_active', 1))
        ->whereDoesntHave('reports', fn ($q) => $q->where('user_id', auth()->id()))
        ->where('id', $id)
        ->first();


        if (!$post) {
            return $this->sendError('Post not found', [], 404);
        }

        // Reload with full relationships including reply/user/likes nesting
        $post->load([
            'user',
            'tags',
            'tags.user',
            'likes',
            'likes.user',
            'comments' => function ($query) {
                $query->withCount('replies') // count replies per comment
                    ->with(['user', 'likes.user', 'replies.user']); // eager load nested stuff
            },
            'media'
        ]);

        $post->is_liked = $post->likes->contains('user_id', auth()->id());
           $post->comments->transform(function ($comment) {
        $comment->is_liked = $comment->likes->contains('user_id', auth()->id());
        return $comment;
    });

        return $this->sendResponse('Post details fetched', $post
        );
    }

    // not using this as all returns should be similar
// private function formatPostWithCounts($post)
// {
//     return [
//         'id' => $post->id,
//         'user' => $post->user,
//         'caption' => $post->caption,
//         'photo' => $post->photo,
//         'privacy' => $post->privacy,
//         'event_category' => $post->category?->name,
//         'tagged_users' => $post->tags->pluck('user'),
//         'likes_count' => $post->likes->count(),
//         'comments_count' => $post->comments->count(),
//         'comments' => $post->comments->map(function ($comment) {
//             return [
//                 'id' => $comment->id,
//                 'body' => $comment->body,
//                 'user' => $comment->user,
//                 'likes_count' => $comment->likes->count(),
//                 'replies_count' => $comment->replies->count(),
//                 'replies' => $comment->replies->map(function ($reply) {
//                     return [
//                         'id' => $reply->id,
//                         'body' => $reply->body,
//                         'user' => $reply->user,
//                         'emojis' => $reply->emojis
//                     ];
//                 }),
//             ];
//         })
//     ];
// }

    // new function to acutally count the posts as well

    // private function formatPostCount($post)
// {
//     return [
//         'id' => $post->id,
//         'user' => $post->user,
//         'caption' => $post->caption,
//         'photo' => $post->photo,
//         'privacy' => $post->privacy,
//         'event_category' => $post->category?->name,
//         'tagged_users' => $post->tags->pluck('user'),
//         'post_count' => $post->count(),
//         'likes_count' => $post->likes->count(),
//         'comments_count' => $post->comments->count(),
//         'comments' => $post->comments->map(function ($comment) {
//             return [
//                 'id' => $comment->id,
//                 'body' => $comment->body,
//                 'user' => $comment->user,
//                 'likes_count' => $comment->likes->count(),
//                 'replies_count' => $comment->replies->count(),
//                 'replies' => $comment->replies->map(function ($reply) {
//                     return [
//                         'id' => $reply->id,
//                         'body' => $reply->body,
//                         'user' => $reply->user,
//                         'emojis' => $reply->emojis
//                     ];
//                 }),
//             ];
//         })
//     ];
// }



    // returns public posts of a user with followers and following
// this function is used in the api route /posts/{id}/with-counts
//added the ability to search user by query params or ID
// and returns the user object with followers and following counts
// and public posts of that user with likes, comments, replies, media, and counts
public function publicPostsWithFollowersFollowing(Request $request, $id)
{
    try {
        $name = $request->query('first_name');
        $email = $request->query('email');
        $contactNo = $request->query('contact_no');

        $params = array_filter([
            'first_name' => $name,
            'email' => $email,
            'contact_no' => $contactNo
        ], fn($value) => $value !== null && $value !== '');

        if (!empty($params)) {
            $user = User::where(function ($query) use ($params) {
                foreach ($params as $key => $value) {
                    $query->where($key, 'like', '%' . $value . '%');
                }
            })->first();

            if (!$user) {
                return $this->sendError('User not found by provided params', [], 404);
            }
        } else {
            $user = User::find($id);
            if (!$user) {
                return $this->sendError('User not found by id', [], 404);
            }
        }

        $user->loadCount(['posts', 'followers', 'following']);

        $isFollow = $user->followers()
        ->where('follower_id', auth()->id())
        ->exists();

        $followers = $user->followers()->with('follower')->get()->pluck('follower');
        $following = $user->following()->with('following')->get()->pluck('following');

        $perPage = $request->get('per_page', 10);

        $publicPostsQuery = Post::with([
                'likes.user',
                'comments.user',
                'comments.likes.user',
                'comments.replies.user',
                'media',
                'tags.user'
            ])
            ->withCount(['likes', 'comments'])
            ->where('privacy', 'public')
            ->whereHas('user', fn ($q) => $q->where('is_active', 1))
            ->where('user_id', $user->id)
            ->whereDoesntHave('reports', fn ($q) => $q->where('user_id', auth()->id()))
            ->latest();

        if ($perPage === 'all') {
            $publicPosts = $publicPostsQuery->get();

            // Add is_liked flag
            $publicPosts->transform(function ($post) {
                $post->is_liked = $post->likes->contains('user_id', auth()->id());
                  $post->comments->transform(function ($comment) {
        $comment->is_liked = $comment->likes->contains('user_id', auth()->id());
        return $comment;
    });
                return $post;
            });

            return $this->sendResponse('Public posts with followers and following fetched', [
                'user' => $user,
                'is_follow' => $isFollow,
                'followers' => $followers,
                'followers_count' => $followers->count(),
                'following' => $following,
                'following_count' => $following->count(),
                'post_count' => $user->posts_count,
                'public_posts' => $publicPosts,
            ]);
        } else {
            $perPage = is_numeric($perPage) ? (int) $perPage : 10;
            $paginatedPosts = $publicPostsQuery->paginate($perPage);

            // Add is_liked flag for paginated posts collection
            $paginatedPosts->getCollection()->transform(function ($post) {
                $post->is_liked = $post->likes->contains('user_id', auth()->id());
                  $post->comments->transform(function ($comment) {
        $comment->is_liked = $comment->likes->contains('user_id', auth()->id());
        return $comment;
    });
                return $post;
            });

            $responseData = [
    'user' => $user,
    'is_follow' => $isFollow,
    'followerss' => $followers,
    'followers_counts' => $followers->count(),
    'followings' => $following,
    'following_counts' => $following->count(),
    'post_count' => $user->posts_count,
    'posts' => $paginatedPosts->items(),
    'pagination' => [
        'current_page' => $paginatedPosts->currentPage(),
        'last_page' => $paginatedPosts->lastPage(),
        'per_page' => $paginatedPosts->perPage(),
        'total' => $paginatedPosts->total(),
        'has_more_pages' => $paginatedPosts->hasMorePages(),
    ],
];
            // dd($following->toArray());

            // return $this->sendResponse(
            //     'Public posts with followers and following fetched',
            //      [
            //         'user' => $user,
            //         'followerss' => $followers,
            //         'followers_counts' => $followers->count(),
            //         'followings' => $following,
            //         'following_counts' => $following->count(),
            //         'post_count' => $user->posts_count,
            //         'pagination' => $paginatedPosts
            //      ],
            //     200,

            // );


            return $this->sendResponse('Public posts with followers and following fetched.', $responseData);
        }
    } catch (\Exception $e) {
        return $this->sendError('Something went wrong', [$e->getMessage()], 500);
    }
}

}
