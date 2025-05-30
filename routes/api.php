<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\UserController;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\DB;

// Root API Route
Route::get('/', function () {
    $baseUrl = config('app.url');
    // Ensure no double slashes if apiPrefix is empty
    $apiRoute = function ($name) {
        // The route() helper generates paths relative to the application root.
        // If apiPrefix is '', route('register') is '/register'.
        // If apiPrefix is 'api', route('register') is '/api/register'.
        // The `true` flag makes route() generate an absolute URL.
        return route($name, [], true);
    };

    return response()->json([
        'message' => 'Welcome to the Forum Lite API. Please use specific endpoints.',
        'available_resources' => [
            'register' => $apiRoute('register'),
            'login' => $apiRoute('login'),
            'user_details' => $apiRoute('user.show'),
            'list_posts' => $apiRoute('posts.index'),
            'ping' => $apiRoute('ping'),
        ],
        'documentation' => $baseUrl . (env('VERCEL_ENV') ? '' : '/api') . '/documentation', // Assuming /docs for Swagger/OpenAPI
    ]);
})->name('api.root');


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Authentication Routes
Route::post('/register', [AuthController::class, 'register'])
    ->middleware(['throttle:auth', 'check.limit:user'])
    ->name('register'); // <-- NAME ADDED
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:auth')
    ->name('login');    // <-- NAME ADDED

// General API routes
Route::middleware('throttle:api')->group(function () {
    // Public Post Routes
    Route::get('/posts', [PostController::class, 'index'])->name('posts.index');
    Route::get('/posts/{post}', [PostController::class, 'show'])->name('posts.show');

    // Public Comment Routes
    Route::get('/posts/{post}/comments', [CommentController::class, 'index'])->name('posts.comments.index');

    // Public Ping Route
    Route::get('/ping', function () {
        return response()->json(['message' => 'pong'], 200);
    })->name('ping');

    // Authenticated Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', function (Request $request) {
            return UserResource::make($request->user());
        })->name('user.show'); // <-- NAME ADDED (consistent with show)
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout'); // <-- NAME ADDED

        // User's own posts and comments (already named)
        Route::get('/user/posts', [UserController::class, 'posts'])->name('user.posts');
        Route::get('/user/comments', [UserController::class, 'comments'])->name('user.comments');

        // Post Management
        Route::post('/posts', [PostController::class, 'store'])
            ->middleware('check.limit:post')
            ->name('posts.store'); // <-- NAME ADDED
        Route::put('/posts/{post}', [PostController::class, 'update'])->name('posts.update'); // Combined PUT/PATCH
        Route::patch('/posts/{post}', [PostController::class, 'update']); // No separate name needed if controller handles both
        Route::delete('/posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');

        // Comment Routes
        Route::post('/posts/{post}/comments', [CommentController::class, 'store'])
            ->middleware('check.limit:comment')
            ->name('posts.comments.store'); // <-- NAME ADDED
        Route::put('/comments/{comment}', [CommentController::class, 'update'])->name('comments.update'); // Combined
        Route::patch('/comments/{comment}', [CommentController::class, 'update']);
        Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
    });
});
