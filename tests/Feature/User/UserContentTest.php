<?php

namespace Tests\Feature\User;

use App\Models\Post;
use App\Models\User;
use App\Models\Comment; // Add this use statement at the top of the file
use Illuminate\Foundation\Testing\RefreshDatabase;
// use Illuminate\Testing\Fluent\AssertableJson; // Not strictly needed for this test yet
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test; // Add this line
use Illuminate\Support\Facades\Config;

class UserContentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function successfully_fetching_posts_for_the_authenticated_user(): void
    {
        // Arrange: Create an authenticated user
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Create posts for this user
        $userPosts = Post::factory()->count(3)->for($user)->create();

        // Create posts for another user (these should not be returned)
        $otherUser = User::factory()->create();
        Post::factory()->count(2)->for($otherUser)->create();

        // Act: Make a GET request to the /user/posts endpoint
        $response = $this->getJson(route('user.posts'));

        // Assert
        $response->assertStatus(200);

        // Assert response structure (pagination, data with PostResource)
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'title',
                    'content',
                    'created_at',
                    'updated_at',
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'created_at',
                        'updated_at',
                    ],
                    'comments_count', // Matched to PostResource
                ]
            ],
            'links' => [
                'first',
                'last',
                'prev',
                'next',
            ],
            'meta' => [
                'current_page',
                'from',
                'last_page',
                'links',
                'path',
                'per_page',
                'to',
                'total',
            ]
        ]);

        // Assert only posts belonging to the authenticated user are returned
        $response->assertJsonCount(3, 'data');

        // Verify the IDs of the returned posts
        $returnedPostIds = collect($response->json('data'))->pluck('id')->all();
        $expectedPostIds = $userPosts->pluck('id')->all();
        $this->assertEqualsCanonicalizing($expectedPostIds, $returnedPostIds);

        // Ensure the user data within the first post is correct
        if (count($userPosts) > 0) {
            $firstUserPostInResponse = $response->json('data.0.user');
            $this->assertEquals($user->id, $firstUserPostInResponse['id']);
            $this->assertEquals($user->name, $firstUserPostInResponse['name']);
            $this->assertEquals($user->email, $firstUserPostInResponse['email']);
        }
    }

    #[Test]
    public function fetching_posts_for_authenticated_user_with_no_posts_returns_empty_data(): void
    {
        // Arrange: Create an authenticated user with no posts
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Ensure no posts exist for this user (or any user, for simplicity in this test)
        // Post::query()->delete(); // Or just don't create any posts for $user

        // Act: Make a GET request to the /user/posts endpoint
        $response = $this->getJson(route('user.posts'));

        // Assert
        $response->assertStatus(200);

        // Assert that the 'data' array is empty
        $response->assertJsonCount(0, 'data');

        // Assert the overall pagination structure is still present
        $response->assertJsonStructure([
            'data', // Should be an empty array
            'links' => [
                'first',
                'last',
                'prev',
                'next',
            ],
            'meta' => [
                'current_page',
                'from',       // Should be null or 0
                'last_page',  // Should be 1 (or 0 depending on pagination logic for empty sets)
                'links',
                'path',
                'per_page',
                'to',         // Should be null or 0
                'total',      // Should be 0
            ]
        ]);

        // Specifically assert the 'total' in meta is 0
        $response->assertJson(['meta' => ['total' => 0]]);
    }

    #[Test]
    public function fetching_authenticated_users_posts_is_paginated_and_sorted_by_latest_first(): void
    {
        // Arrange: Create an authenticated user
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Create more posts than the default pagination limit to test pagination
        // Let's assume the limit is 10 (config('app_limits.pagination_limit', 10))
        $paginationLimit = config('app_limits.pagination_limit', 10);
        $totalPostsToCreate = $paginationLimit + 5; // e.g., 15 posts

        $posts = collect();
        for ($i = 0; $i < $totalPostsToCreate; $i++) {
            // Create posts with slightly different creation times to ensure sorting can be tested
            $posts->push(
                Post::factory()->for($user)->create(['created_at' => now()->subSeconds($i)])
            );
        }

        // Act: Fetch the first page
        $responsePage1 = $this->getJson(route('user.posts'));

        // Assert for Page 1
        $responsePage1->assertStatus(200);
        $responsePage1->assertJsonCount($paginationLimit, 'data'); // Should have $paginationLimit items
        $responsePage1->assertJsonPath('meta.current_page', 1);
        $responsePage1->assertJsonPath('meta.total', $totalPostsToCreate);

        // Verify default sorting (latest first)
        // The first post created (oldest by our loop) should be the last in the full sorted list.
        // The last post created (newest by our loop, subSeconds(0)) should be the first.
        $expectedFirstPostIdOnPage1 = $posts->sortByDesc('created_at')->first()->id;
        $responsePage1->assertJsonPath('data.0.id', $expectedFirstPostIdOnPage1);

        // Act: Fetch the second page
        $responsePage2 = $this->getJson(route('user.posts', ['page' => 2]));

        // Assert for Page 2
        $responsePage2->assertStatus(200);
        $responsePage2->assertJsonCount($totalPostsToCreate - $paginationLimit, 'data'); // Remaining items
        $responsePage2->assertJsonPath('meta.current_page', 2);

        // Verify sorting on page 2
        $expectedFirstPostIdOnPage2 = $posts->sortByDesc('created_at')->slice($paginationLimit)->first()->id;
        $responsePage2->assertJsonPath('data.0.id', $expectedFirstPostIdOnPage2);
    }

    #[Test]
    public function unauthenticated_user_cannot_fetch_user_posts(): void
    {
        // Arrange: No user is authenticated.
        // Sanctum::actingAs() is NOT called.

        // Act: Make a GET request to the /user/posts endpoint without authentication
        $response = $this->getJson(route('user.posts'));

        // Assert
        $response->assertStatus(401); // Assert 401 Unauthorized status
        $response->assertJson(['message' => 'Unauthenticated.']); // Default Laravel message
    }

    #[Test]
    public function fetching_authenticated_users_posts_is_rate_limited(): void
    {
        // Arrange: Create an authenticated user
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Create some posts for the user so the endpoint has data to return
        Post::factory()->count(3)->for($user)->create();

        $maxAttempts = Config::get('app_limits.throttle_api_limit', 60); // Default for 'api' throttle

        // Act: Hit the endpoint $maxAttempts times.
        // Each attempt should succeed (200 OK).
        for ($i = 0; $i < $maxAttempts; $i++) {
            $response = $this->getJson(route('user.posts'));
            $response->assertStatus(200);
        }

        // The ($maxAttempts + 1)-th attempt should be rate limited (429)
        $response = $this->getJson(route('user.posts'));

        // Assert
        $response->assertStatus(429); // Too Many Requests
        $response->assertJson(['message' => 'Too Many Attempts.']);

        // Check headers for the rate-limited response
        $this->assertNotNull($response->headers->get('Retry-After'));
        $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));
        $this->assertEquals($maxAttempts, $response->headers->get('X-RateLimit-Limit'));
        $this->assertEquals(0, $response->headers->get('X-RateLimit-Remaining'));
    }

    #[Test]
    public function successfully_fetching_comments_for_the_authenticated_user(): void
    {
        // Arrange: Create an authenticated user
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Create a post for the comments to belong to
        $post = Post::factory()->create();

        // Create comments for this user
        $userComments = Comment::factory()->count(3)->for($user)->for($post)->create();

        // Create comments for another user on the same post (these should not be returned)
        $otherUser = User::factory()->create();
        Comment::factory()->count(2)->for($otherUser)->for($post)->create();

        // Act: Make a GET request to the /user/comments endpoint
        $response = $this->getJson(route('user.comments')); // Assuming route name 'user.comments'

        // Assert
        $response->assertStatus(200);

        // Assert response structure (pagination, data with CommentResource)
        $response->assertJsonStructure([
            'data' => [
                '*' => [ // Expecting an array of comment objects
                    'id',
                    'content',
                    'created_at',
                    'updated_at',
                    'user' => [ // This should match UserResource structure
                        'id',
                        'name',
                        'email',
                        'created_at',
                        'updated_at',
                    ],
                    // 'post_id' // Optional: if your CommentResource includes post_id directly
                ]
            ],
            'links' => [
                'first',
                'last',
                'prev',
                'next',
            ],
            'meta' => [
                'current_page',
                'from',
                'last_page',
                'links',
                'path',
                'per_page',
                'to',
                'total',
            ]
        ]);

        // Assert only comments belonging to the authenticated user are returned
        $response->assertJsonCount(3, 'data');

        // Verify the IDs of the returned comments
        $returnedCommentIds = collect($response->json('data'))->pluck('id')->all();
        $expectedCommentIds = $userComments->pluck('id')->all();
        $this->assertEqualsCanonicalizing($expectedCommentIds, $returnedCommentIds);

        // Ensure the user data within the first comment is correct
        if (count($userComments) > 0) {
            $firstUserCommentInResponse = $response->json('data.0.user');
            $this->assertEquals($user->id, $firstUserCommentInResponse['id']);
            $this->assertEquals($user->name, $firstUserCommentInResponse['name']);
        }
    }

    #[Test]
    public function fetching_comments_for_authenticated_user_with_no_comments_returns_empty_data(): void
    {
        // Arrange: Create an authenticated user with no comments
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Ensure no comments exist for this user
        // Comment::query()->where('user_id', $user->id)->delete(); // Or just don't create any

        // Act: Make a GET request to the /user/comments endpoint
        $response = $this->getJson(route('user.comments'));

        // Assert
        $response->assertStatus(200);

        // Assert that the 'data' array is empty
        $response->assertJsonCount(0, 'data');

        // Assert the overall pagination structure is still present
        $response->assertJsonStructure([
            'data', // Should be an empty array
            'links' => [
                'first',
                'last',
                'prev',
                'next',
            ],
            'meta' => [
                'current_page',
                'from',
                'last_page',
                'links',
                'path',
                'per_page',
                'to',
                'total',
            ]
        ]);

        // Specifically assert the 'total' in meta is 0
        $response->assertJson(['meta' => ['total' => 0]]);
    }

    #[Test]
    public function fetching_authenticated_users_comments_is_paginated_and_sorted_by_latest_first(): void
    {
        // Arrange: Create an authenticated user
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $post = Post::factory()->create(); // A post for comments to belong to

        // Create more comments than the default pagination limit
        $paginationLimit = config('app_limits.pagination_limit', 10);
        $totalCommentsToCreate = $paginationLimit + 5; // e.g., 15 comments

        $comments = collect();
        for ($i = 0; $i < $totalCommentsToCreate; $i++) {
            // Create comments with slightly different creation times
            $comments->push(
                Comment::factory()->for($user)->for($post)
                         ->create(['created_at' => now()->subSeconds($i)])
            );
        }

        // Act: Fetch the first page
        $responsePage1 = $this->getJson(route('user.comments'));

        // Assert for Page 1
        $responsePage1->assertStatus(200);
        $responsePage1->assertJsonCount($paginationLimit, 'data');
        $responsePage1->assertJsonPath('meta.current_page', 1);
        $responsePage1->assertJsonPath('meta.total', $totalCommentsToCreate);

        // Verify default sorting (latest first)
        $expectedFirstCommentIdOnPage1 = $comments->sortByDesc('created_at')->first()->id;
        $responsePage1->assertJsonPath('data.0.id', $expectedFirstCommentIdOnPage1);

        // Act: Fetch the second page
        $responsePage2 = $this->getJson(route('user.comments', ['page' => 2]));

        // Assert for Page 2
        $responsePage2->assertStatus(200);
        $responsePage2->assertJsonCount($totalCommentsToCreate - $paginationLimit, 'data'); // Remaining items
        $responsePage2->assertJsonPath('meta.current_page', 2);

        // Verify sorting on page 2
        $expectedFirstCommentIdOnPage2 = $comments->sortByDesc('created_at')->slice($paginationLimit)->first()->id;
        $responsePage2->assertJsonPath('data.0.id', $expectedFirstCommentIdOnPage2);
    }

    #[Test]
    public function unauthenticated_user_cannot_fetch_user_comments(): void
    {
        // Arrange: No user is authenticated.
        // Sanctum::actingAs() is NOT called.

        // Act: Make a GET request to the /user/comments endpoint without authentication
        $response = $this->getJson(route('user.comments'));

        // Assert
        $response->assertStatus(401); // Assert 401 Unauthorized status
        $response->assertJson(['message' => 'Unauthenticated.']); // Default Laravel message
    }

    #[Test]
    public function fetching_authenticated_users_comments_is_rate_limited(): void
    {
        // Arrange: Create an authenticated user
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Create some comments for the user so the endpoint has data to return
        $post = Post::factory()->create();
        Comment::factory()->count(3)->for($user)->for($post)->create();

        $maxAttempts = Config::get('app_limits.throttle_api_limit', 60);

        // Act: Hit the endpoint $maxAttempts times.
        for ($i = 0; $i < $maxAttempts; $i++) {
            $response = $this->getJson(route('user.comments'));
            $response->assertStatus(200);
        }

        // The ($maxAttempts + 1)-th attempt should be rate limited
        $response = $this->getJson(route('user.comments'));

        // Assert
        $response->assertStatus(429); // Too Many Requests
        $response->assertJson(['message' => 'Too Many Attempts.']);

        // Check headers for the rate-limited response
        $this->assertNotNull($response->headers->get('Retry-After'));
        $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));
        $this->assertEquals($maxAttempts, $response->headers->get('X-RateLimit-Limit'));
        $this->assertEquals(0, $response->headers->get('X-RateLimit-Remaining'));
    }
}
