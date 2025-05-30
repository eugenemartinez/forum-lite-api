<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\CommentResource;
use OpenApi\Annotations as OA;

class CommentController extends Controller
{
    /**
     * @OA\Get(
     *      path="/posts/{post}/comments",
     *      operationId="getPostComments",
     *      tags={"Comments"},
     *      summary="List comments for a specific post",
     *      description="Returns a paginated list of comments for a given post.",
     *      @OA\Parameter(
     *          name="post",
     *          in="path",
     *          required=true,
     *          description="ID of the post",
     *          @OA\Schema(type="integer")
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
     *              @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/CommentResource")),
     *              @OA\Property(property="links", ref="#/components/schemas/PaginationLinks"),
     *              @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
     *          )
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Post not found",
     *          @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")
     *      )
     * )
     */
    public function index(Request $request, Post $post) // Pass Post $post for route model binding
    {
        $comments = $post->comments()
                         ->with('user:id,name,email,created_at,updated_at') // <-- Include all fields UserResource needs
                         ->orderBy('created_at', 'desc') // Or 'asc' if you prefer oldest first
                         ->paginate(10); // Paginate results

        return CommentResource::collection($comments); // <-- Use CommentResource for collections
    }

    /**
     * @OA\Post(
     *      path="/posts/{post}/comments",
     *      operationId="storePostComment",
     *      tags={"Comments"},
     *      summary="Create a new comment on a post",
     *      description="Stores a new comment for a specific post.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(
     *          name="post",
     *          in="path",
     *          required=true,
     *          description="ID of the post to comment on",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          description="Comment data",
     *          @OA\JsonContent(
     *              required={"content"},
     *              @OA\Property(property="content", type="string", example="This is a great comment!")
     *          )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Comment created successfully",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="message", type="string", example="Comment created successfully"),
     *              @OA\Property(property="data", ref="#/components/schemas/CommentResource")
     *          )
     *      ),
     *      @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthenticatedResponse")),
     *      @OA\Response(response=404, description="Post not found", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")),
     *      @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse"))
     * )
     */
    public function store(Request $request, Post $post) // Pass Post $post for route model binding
    {
        $validator = Validator::make($request->all(), [
            'content' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $comment = $post->comments()->create([
            'user_id' => Auth::id(),
            'content' => $request->content,
        ]);

        $comment->load('user:id,name,email,created_at,updated_at'); // <-- Include all fields UserResource needs

        return (CommentResource::make($comment)) // <-- Use CommentResource
                ->additional(['message' => 'Comment created successfully'])
                ->response()
                ->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Comment $comment)
    {
        // We might not need a direct show for a single comment by its ID,
        // as comments are usually viewed in context of a post.
        // We can implement if needed.
        // If implemented:
        // $comment->load('user:id,name');
        // return CommentResource::make($comment);
    }

    /**
     * @OA\Put(
     *      path="/comments/{comment}",
     *      operationId="updateComment",
     *      tags={"Comments"},
     *      summary="Update an existing comment",
     *      description="Updates an existing comment by its ID.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(
     *          name="comment",
     *          in="path",
     *          required=true,
     *          description="ID of the comment to update",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          description="Comment data to update",
     *          @OA\JsonContent(
     *              required={"content"},
     *              @OA\Property(property="content", type="string", example="Updated comment content.")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Comment updated successfully",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="message", type="string", example="Comment updated successfully"),
     *              @OA\Property(property="data", ref="#/components/schemas/CommentResource")
     *          )
     *      ),
     *      @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthenticatedResponse")),
     *      @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ForbiddenResponse")),
     *      @OA\Response(response=404, description="Comment not found", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")),
     *      @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse"))
     * )
     */
    public function update(Request $request, Comment $comment)
    {
        // Authorize the action
        $this->authorize('update', $comment); // Uses CommentPolicy

        $validator = Validator::make($request->all(), [
            'content' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $comment->content = $request->content;
        $comment->save();

        $comment->load('user:id,name,email,created_at,updated_at'); // <-- Include all fields UserResource needs

        return (CommentResource::make($comment)) // <-- Use CommentResource
                ->additional(['message' => 'Comment updated successfully'])
                ->response()
                ->setStatusCode(200);
    }

    /**
     * @OA\Delete(
     *      path="/comments/{comment}",
     *      operationId="deleteComment",
     *      tags={"Comments"},
     *      summary="Delete a comment",
     *      description="Deletes a comment by its ID.",
     *      security={{"bearerAuth":{}}},
     *      @OA\Parameter(
     *          name="comment",
     *          in="path",
     *          required=true,
     *          description="ID of the comment to delete",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Comment deleted successfully",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="message", type="string", example="Comment deleted successfully")
     *          )
     *      ),
     *      @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthenticatedResponse")),
     *      @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ForbiddenResponse")),
     *      @OA\Response(response=404, description="Comment not found", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse"))
     * )
     */
    public function destroy(Comment $comment)
    {
        // Authorize the action
        $this->authorize('delete', $comment); // Uses CommentPolicy

        $comment->delete();

        return response()->json([
            'message' => 'Comment deleted successfully'
        ], 200); // Or 204 No Content
    }
}
