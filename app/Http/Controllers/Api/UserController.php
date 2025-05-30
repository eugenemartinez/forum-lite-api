<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\PostResource;
use App\Http\Resources\CommentResource;
use OpenApi\Annotations as OA;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        //
    }

    /**
     * @OA\Get(
     *      path="/user/posts",
     *      operationId="getAuthenticatedUserPosts",
     *      tags={"User Profile"},
     *      summary="Get posts of the authenticated user",
     *      description="Returns a paginated list of posts created by the currently authenticated user.",
     *      security={{"bearerAuth":{}}},
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
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(ref="#/components/schemas/UnauthenticatedResponse")
     *      )
     * )
     */
    public function posts(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $posts = $user->posts()
                        ->with('user:id,name,email,created_at,updated_at')
                        ->withCount('comments') // Add this line
                        ->orderBy('created_at', 'desc')
                        ->paginate(10);

        return PostResource::collection($posts);
    }

    /**
     * @OA\Get(
     *      path="/user/comments",
     *      operationId="getAuthenticatedUserComments",
     *      tags={"User Profile"},
     *      summary="Get comments of the authenticated user",
     *      description="Returns a paginated list of comments made by the currently authenticated user.",
     *      security={{"bearerAuth":{}}},
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
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(ref="#/components/schemas/UnauthenticatedResponse")
     *      )
     * )
     */
    public function comments(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $comments = $user->comments()
                          ->with(['user:id,name,email,created_at,updated_at', 'post:id,title'])
                          ->orderBy('created_at', 'desc')
                          ->paginate(10);

        return CommentResource::collection($comments);
    }
}
