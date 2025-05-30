<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\PostResource;
use OpenApi\Annotations as OA; // <--- ENSURE THIS IS PRESENT

class PostController extends Controller
{
    /**
     * @OA\Get(
     *      path="/posts",
     *      operationId="getPostsList",
     *      tags={"Posts"},
     *      summary="Get list of posts",
     *      description="Returns a paginated list of posts. Can be filtered by a search term.",
     *      @OA\Parameter(
     *          name="search",
     *          in="query",
     *          description="Search term to filter posts by title or content",
     *          required=false,
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Parameter(
     *          name="page",
     *          in="query",
     *          description="Page number for pagination",
     *          required=false,
     *          @OA\Schema(type="integer", default=1)
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/PostResource")),
     *              @OA\Property(property="links", ref="#/components/schemas/PaginationLinks"),
     *              @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
     *          )
     *      )
     * )
     */
    public function index(Request $request)
    {
        $query = Post::with('user:id,name,email,created_at,updated_at');

        // Check for search query parameter
        if ($request->has('search')) {
            $searchTerm = $request->query('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', "%{$searchTerm}%")
                  ->orWhere('content', 'like', "%{$searchTerm}%");
            });
        }

        $posts = $query->orderBy('created_at', 'desc')
                       ->paginate(10); // Default pagination is 10

        return PostResource::collection($posts);
    }

    /**
     * @OA\Post(
     *      path="/posts",
     *      operationId="storePost",
     *      tags={"Posts"},
     *      summary="Create a new post",
     *      description="Stores a new post in the database.",
     *      security={{"bearerAuth":{}}},
     *      @OA\RequestBody(
     *          required=true,
     *          description="Post data",
     *          @OA\JsonContent(
     *              required={"title","content"},
     *              @OA\Property(property="title", type="string", maxLength=255, example="New Post Title"),
     *              @OA\Property(property="content", type="string", example="Content of the new post.")
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Post created successfully",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="message", type="string", example="Post created successfully"),
     *              @OA\Property(property="data", ref="#/components/schemas/PostResource")
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(ref="#/components/schemas/UnauthenticatedResponse")
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
     *      )
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $post = Post::create([
            'user_id' => Auth::id(),
            'title' => $request->title,
            'content' => $request->content,
        ]);

        $post->load('user:id,name,email,created_at,updated_at');

        return (PostResource::make($post))
                ->additional(['message' => 'Post created successfully'])
                ->response()
                ->setStatusCode(201);
    }

    /**
     * @OA\Get(
     *      path="/posts/{post}",
     *      operationId="getPostById",
     *      tags={"Posts"},
     *      summary="Get a single post",
     *      description="Returns a single post by its ID.",
     *      @OA\Parameter(
     *          name="post",
     *          in="path",
     *          required=true,
     *          description="ID of the post to retrieve",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(ref="#/components/schemas/PostResource")
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Post not found",
     *          @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")
     *      )
     * )
     */
    public function show(Post $post)
    {
        // Load the user relationship and the count of comments
        $post->load('user:id,name,email,created_at,updated_at')
             ->loadCount('comments'); // <-- Add this line

        return PostResource::make($post);
    }

    /**
     * @OA\Put(
     *      path="/posts/{post}",
     *      operationId="updatePost",
     *      tags={"Posts"},
     *      summary="Update an existing post",
     *      description="Updates an existing post by its ID.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(
     *          name="post",
     *          in="path",
     *          required=true,
     *          description="ID of the post to update",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          description="Post data to update. At least one field (title or content) is required.",
     *          @OA\JsonContent(
     *              @OA\Property(property="title", type="string", maxLength=255, example="Updated Post Title", nullable=true),
     *              @OA\Property(property="content", type="string", example="Updated content of the post.", nullable=true)
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Post updated successfully",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="message", type="string", example="Post updated successfully"),
     *              @OA\Property(property="data", ref="#/components/schemas/PostResource")
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(ref="#/components/schemas/UnauthenticatedResponse")
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden - User cannot update this post",
     *          @OA\JsonContent(ref="#/components/schemas/ForbiddenResponse")
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Post not found",
     *          @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
     *      )
     * )
     */
    public function update(Request $request, Post $post)
    {
        $this->authorize('update', $post);

        $validator = Validator::make($request->all(), [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'content' => ['sometimes', 'required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        if ($request->has('title')) {
            $post->title = $request->title;
        }
        if ($request->has('content')) {
            $post->content = $request->content;
        }
        $post->save();

        $post->load('user:id,name,email,created_at,updated_at');

        return (PostResource::make($post))
                ->additional(['message' => 'Post updated successfully'])
                ->response()
                ->setStatusCode(200);
    }

    /**
     * @OA\Delete(
     *      path="/posts/{post}",
     *      operationId="deletePost",
     *      tags={"Posts"},
     *      summary="Delete a post",
     *      description="Deletes a post by its ID.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(
     *          name="post",
     *          in="path",
     *          required=true,
     *          description="ID of the post to delete",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Post deleted successfully",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="message", type="string", example="Post deleted successfully")
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(ref="#/components/schemas/UnauthenticatedResponse")
     *      ),
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden - User cannot delete this post",
     *          @OA\JsonContent(ref="#/components/schemas/ForbiddenResponse")
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Post not found",
     *          @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")
     *      )
     * )
     */
    public function destroy(Post $post)
    {
        $this->authorize('delete', $post);
        $post->delete();
        return response()->json(['message' => 'Post deleted successfully'], 200);
    }
}
