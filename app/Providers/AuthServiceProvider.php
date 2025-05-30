<?php

namespace App\Providers;

use App\Models\Post;
use App\Policies\PostPolicy;
use App\Models\Comment; // <-- Add this line
use App\Policies\CommentPolicy; // <-- Add this line
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Post::class => PostPolicy::class,
        Comment::class => CommentPolicy::class, // <-- Add this line
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        //
    }
}
