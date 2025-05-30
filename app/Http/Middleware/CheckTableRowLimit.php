<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;
use App\Models\Post;
use App\Models\Comment;
use Illuminate\Support\Facades\Route;

class CheckTableRowLimit
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $modelType  Type of model to check ('user', 'post', 'comment')
     */
    public function handle(Request $request, Closure $next, string $modelType): Response
    {
        $limitReached = false;
        $message = 'The maximum number of allowed records has been reached. Cannot create new entries at this time.';

        switch ($modelType) {
            case 'user':
                if (User::count() >= config('app_limits.max_users')) {
                    $limitReached = true;
                }
                break;
            case 'post':
                if (Post::count() >= config('app_limits.max_posts')) {
                    $limitReached = true;
                }
                break;
            case 'comment':
                if (Comment::count() >= config('app_limits.max_comments')) {
                    $limitReached = true;
                }
                break;
            default:
                // If an unknown model type is passed, let the request through or log an error
                // For now, we'll let it through to avoid blocking unrelated routes.
                return $next($request);
        }

        if ($limitReached) {
            // You might want to return a 503 Service Unavailable or 403 Forbidden
            return response()->json(['message' => $message], 503);
        }

        return $next($request);
    }
}
