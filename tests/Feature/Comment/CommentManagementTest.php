<?php

namespace Tests\Feature\Comment;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config; // If you use Config for limits
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CommentManagementTest extends TestCase
{
    use RefreshDatabase;

    // We will add test methods here

    #[Test]
    public function user_can_fetch_a_list_of_comments_for_a_post(): void
    {
        // Arrange: Create a user, a post, and some comments for that post
        $user = User::factory()->create(); // User who creates comments
        $post = Post::factory()->for($user)->create(); // The post to which comments belong

        // Create a few comments for the post
        $comments = Comment::factory(3)->for($post)->for($user)->create();

        // Act: Make a GET request to the list comments endpoint for the specific post
        $response = $this->getJson(route('posts.comments.index', ['post' => $post->id]));

        // Assert
        $response->assertStatus(200);

        // Assert basic pagination structure
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'content',
                    'created_at',
                    'updated_at',
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'created_at', // Ensure UserResource structure is fully represented here too
                        'updated_at', // Ensure UserResource structure is fully represented here too
                    ],
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
            ],
        ]);

        $response->assertJsonCount(3, 'data');

        // Assert that the data matches one of the created comments
        // Make the user fragment match the UserResource output more closely
        $firstComment = $comments->first();
        $commentingUser = $firstComment->user; // Get the actual user model instance from the comment

        $response->assertJsonFragment([
            'id' => $firstComment->id,
            'content' => $firstComment->content,
            'user' => [
                'id' => $commentingUser->id,
                'name' => $commentingUser->name,
                'email' => $commentingUser->email,
                'created_at' => $commentingUser->created_at->toIso8601String(), // Add this
                'updated_at' => $commentingUser->updated_at->toIso8601String(), // Add this
            ]
        ]);
    }

    #[Test]
    public function user_can_fetch_an_empty_list_of_comments_for_a_post_with_no_comments(): void
    {
        // Arrange: Create a user and a post, but no comments for this post
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->create();

        // Act: Make a GET request to the list comments endpoint for the post
        $response = $this->getJson(route('posts.comments.index', ['post' => $post->id]));

        // Assert
        $response->assertStatus(200);

        // Assert basic pagination structure (even if data is empty)
        $response->assertJsonStructure([
            'data', // Expect 'data' key, even if it's an empty array
            'links' => [
                'first',
                'last',
                'prev',
                'next',
            ],
            'meta' => [
                'current_page',
                'from',       // Should be null or 0 if no items
                'last_page',
                'links',
                'path',
                'per_page',
                'to',         // Should be null or 0 if no items
                'total',      // Should be 0
            ],
        ]);

        // Assert that the 'data' array is empty
        $response->assertJsonCount(0, 'data');

        // Assert that the 'total' in meta is 0
        $response->assertJson(['meta' => ['total' => 0]]);
    }

    #[Test]
    public function fetching_comments_for_a_non_existent_post_returns_404_not_found(): void
    {
        // Arrange: Define a post ID that is guaranteed not to exist
        $nonExistentPostId = 99999; // Or use Post::max('id') + 1 if posts might exist

        // Act: Make a GET request to the list comments endpoint with the non-existent post ID
        $response = $this->getJson(route('posts.comments.index', ['post' => $nonExistentPostId]));

        // Assert
        $response->assertStatus(404);
    }

    #[Test]
    public function listing_comments_for_a_post_is_paginated_correctly(): void
    {
        // Arrange: Create a user, a post, and more comments than the per_page limit
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->create();

        // Use the same perPage value as the controller
        $perPage = config('app_limits.comments_per_page', 10);
        $totalCommentsToCreate = 20; // Ensure at least two pages

        $comments = collect();
        for ($i = 0; $i < $totalCommentsToCreate; $i++) {
            // Create comments with slightly different creation times for predictable ordering
            $comments->push(
                Comment::factory()->for($post)->for($user)->create(['created_at' => now()->subMinutes($totalCommentsToCreate - $i)])
            );
        }

        // Sort the local collection in the same order as the controller (created_at DESC)
        $sortedComments = $comments->sortByDesc('created_at')->values();

        // Act: Request the second page
        $response = $this->getJson(route('posts.comments.index', ['post' => $post->id, 'page' => 2]));

        // Assert
        $response->assertStatus(200);

        // Calculate expected count on page 2
        $expectedCountOnPage2 = $totalCommentsToCreate - $perPage;
        if ($expectedCountOnPage2 < 0) $expectedCountOnPage2 = 0; // Should not happen with 20 total and perPage 10/15
        if ($totalCommentsToCreate > $perPage && $totalCommentsToCreate % $perPage !== 0 && $expectedCountOnPage2 === 0) {
             $expectedCountOnPage2 = $totalCommentsToCreate % $perPage;
        } else if ($totalCommentsToCreate <= $perPage) {
             $expectedCountOnPage2 = 0; // No items on page 2 if total <= perPage
        }


        $response->assertJsonCount($expectedCountOnPage2, 'data');
        $response->assertJsonPath('meta.current_page', 2);
        $response->assertJsonPath('meta.per_page', $perPage);
        $response->assertJsonPath('meta.total', $totalCommentsToCreate);

        if ($expectedCountOnPage2 > 0) {
            // Verify that the first comment on page 2 is the expected one
            // (the ($perPage + 1)-th comment when sorted by created_at DESC)
            $expectedFirstCommentOnPage2 = $sortedComments->get($perPage); // Index is $perPage because 0-indexed
            $response->assertJsonFragment(['id' => $expectedFirstCommentOnPage2->id]);

            // Verify that the last comment on page 1 is NOT present on page 2
            $lastCommentOnPage1 = $sortedComments->get($perPage - 1);
            $response->assertJsonMissingExact(['id' => $lastCommentOnPage1->id, 'content' => $lastCommentOnPage1->content]);
        }
    }

    #[Test]
    public function listing_comments_for_a_post_is_sorted_by_created_at_descending_by_default(): void
    {
        // Arrange: Create a user, a post, and a few comments with different creation times
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->create();

        $commentOldest = Comment::factory()->for($post)->for($user)->create(['created_at' => now()->subDays(2)]);
        $commentNewest = Comment::factory()->for($post)->for($user)->create(['created_at' => now()->subDays(0)]); // Today
        $commentMiddle = Comment::factory()->for($post)->for($user)->create(['created_at' => now()->subDays(1)]);

        // Act: Make a GET request to the list comments endpoint
        $response = $this->getJson(route('posts.comments.index', ['post' => $post->id]));

        // Assert
        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');

        // Assert that the comments are in the correct order (newest first)
        $response->assertJsonPath('data.0.id', $commentNewest->id); // First item should be the newest
        $response->assertJsonPath('data.1.id', $commentMiddle->id);
        $response->assertJsonPath('data.2.id', $commentOldest->id); // Last item should be the oldest

        // A more robust way to check order if you have many items and pagination:
        // $commentIds = $response->json('data.*.id');
        // $this->assertEquals([$commentNewest->id, $commentMiddle->id, $commentOldest->id], $commentIds);
    }

    #[Test]
    public function listing_comments_for_a_post_is_rate_limited(): void
    {
        // Arrange: Create a post (comments are not strictly necessary for this rate limit test)
        $post = Post::factory()->create();

        // Get the configured rate limit for the 'api' group
        // The 'api' group in Http/Kernel.php usually has 'throttle:api'
        // The 'api' throttle is defined in Providers/RouteServiceProvider.php
        $maxAttempts = Config::get('app_limits.throttle_api_limit', 60); // Default for 'api' throttle

        // Act: Hit the endpoint $maxAttempts times.
        // Each attempt should succeed (200 OK).
        for ($i = 0; $i < $maxAttempts; $i++) {
            $response = $this->getJson(route('posts.comments.index', ['post' => $post->id]));
            $response->assertStatus(200);
        }

        // The ($maxAttempts + 1)-th attempt should be rate limited (429)
        $response = $this->getJson(route('posts.comments.index', ['post' => $post->id]));

        // Assert
        $response->assertStatus(429); // Too Many Requests
        $response->assertJson(['message' => 'Too Many Attempts.']); // Default Laravel message

        // Check headers for the rate-limited response
        $this->assertNotNull($response->headers->get('Retry-After'));
        $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));
        $this->assertEquals($maxAttempts, $response->headers->get('X-RateLimit-Limit'));
        $this->assertEquals(0, $response->headers->get('X-RateLimit-Remaining'));
    }

    #[Test]
    public function authenticated_user_can_create_a_comment_on_a_post_with_valid_data(): void
    {
        // Arrange: Create an authenticated user and a post
        $user = User::factory()->create();
        $this->actingAs($user);

        $post = Post::factory()->create(); // Post to comment on

        $commentData = [
            'content' => 'This is a new insightful comment.',
        ];

        // Act: Make a POST request to the create comment endpoint
        $response = $this->postJson(route('posts.comments.store', ['post' => $post->id]), $commentData);

        // Assert
        $response->assertStatus(201); // Assert 201 Created status

        // Assert response structure matches CommentResource
        $response->assertJsonStructure([
            'data' => [
                'id',
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
            ],
            'message', // Assuming your controller adds a 'message'
        ]);

        // Assert the content of the created comment in the response
        $response->assertJsonFragment([
            'content' => $commentData['content'],
            // Ensure the user fragment matches the UserResource output
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at->toIso8601String(), // Add this
                'updated_at' => $user->updated_at->toIso8601String(), // Add this
            ]
        ]);
        if ($response->json('message')) { // Optional: check message if present
            $response->assertJson(['message' => 'Comment created successfully']);
        }


        // Assert comment is created in the database with correct user_id and post_id
        $this->assertDatabaseHas('comments', [
            'post_id' => $post->id,
            'user_id' => $user->id,
            'content' => $commentData['content'],
        ]);

        // Optionally, assert the count of comments for the post has increased
        $this->assertDatabaseCount('comments', 1); // Assuming this is the first comment
        $this->assertEquals(1, $post->refresh()->comments()->count());
    }

    #[Test]
    public function creating_a_comment_with_missing_content_field_returns_validation_error(): void
    {
        // Arrange: Create an authenticated user and a post
        $user = User::factory()->create();
        $this->actingAs($user);

        $post = Post::factory()->create();

        $invalidCommentData = [
            // 'content' is intentionally missing
        ];

        // Act: Make a POST request to the create comment endpoint with missing content
        $response = $this->postJson(route('posts.comments.store', ['post' => $post->id]), $invalidCommentData);

        // Assert
        $response->assertStatus(422); // Assert 422 Unprocessable Entity status

        // Assert validation error for the 'content' field
        $response->assertJsonValidationErrors(['content']);
        // A more specific check for the message if you know it:
        // $response->assertJsonValidationErrorFor('content', 'The content field is required.');

        // Assert that no comment was created in the database
        $this->assertDatabaseCount('comments', 0); // Assuming no other comments exist
    }

    #[Test]
    public function creating_a_comment_on_a_non_existent_post_returns_404_not_found(): void
    {
        // Arrange: Create an authenticated user
        $user = User::factory()->create();
        $this->actingAs($user);

        $nonExistentPostId = 99999; // An ID that is guaranteed not to exist

        $commentData = [
            'content' => 'This comment should not be created.',
        ];

        // Act: Make a POST request to the create comment endpoint with a non-existent post ID
        $response = $this->postJson(route('posts.comments.store', ['post' => $nonExistentPostId]), $commentData);

        // Assert
        $response->assertStatus(404); // Assert 404 Not Found status

        // Assert that no comment was created in the database
        $this->assertDatabaseCount('comments', 0); // Assuming no other comments exist
    }

    #[Test]
    public function unauthenticated_user_cannot_create_a_comment(): void
    {
        // Arrange: Create a post (owner doesn't matter here as the request is unauthenticated)
        $post = Post::factory()->create();

        $commentData = [
            'content' => 'This comment should not be created by an unauthenticated user.',
        ];

        // Act: Make a POST request to the create comment endpoint without authentication
        // Note: $this->actingAs() is NOT called
        $response = $this->postJson(route('posts.comments.store', ['post' => $post->id]), $commentData);

        // Assert
        $response->assertStatus(401); // Assert 401 Unauthorized status
        $response->assertJson(['message' => 'Unauthenticated.']); // Default Laravel message

        // Assert that no comment was created in the database
        $this->assertDatabaseCount('comments', 0); // Assuming no other comments exist
    }

    #[Test]
    public function creating_a_comment_is_rate_limited_for_authenticated_user(): void
    {
        // Arrange: Create an authenticated user and a post
        $user = User::factory()->create();
        $this->actingAs($user);

        $post = Post::factory()->create();

        $commentData = [
            'content' => 'This is a rate-limited comment attempt.',
        ];

        $maxAttempts = Config::get('app_limits.throttle_api_limit', 60); // Default for 'api' throttle

        // Act: Hit the endpoint $maxAttempts times.
        // Each attempt should succeed (201 Created).
        for ($i = 0; $i < $maxAttempts; $i++) {
            // Vary data slightly if needed, though for creating new resources, it might not matter as much
            // as long as the request itself is distinct enough for the rate limiter.
            // For simplicity, we'll use the same data, as each successful request creates a new comment.
            $response = $this->postJson(route('posts.comments.store', ['post' => $post->id]), $commentData);
            $response->assertStatus(201);
        }

        // The ($maxAttempts + 1)-th attempt should be rate limited (429)
        $response = $this->postJson(route('posts.comments.store', ['post' => $post->id]), $commentData);

        // Assert
        $response->assertStatus(429); // Too Many Requests
        $response->assertJson(['message' => 'Too Many Attempts.']);

        // Check headers for the rate-limited response
        $this->assertNotNull($response->headers->get('Retry-After'));
        $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));
        $this->assertEquals($maxAttempts, $response->headers->get('X-RateLimit-Limit'));
        $this->assertEquals(0, $response->headers->get('X-RateLimit-Remaining'));

        // Ensure only $maxAttempts comments were actually created for this post by this user
        $this->assertDatabaseCount('comments', $maxAttempts);
        $this->assertEquals($maxAttempts, $post->comments()->where('user_id', $user->id)->count());
    }

    #[Test]
    public function creating_a_comment_is_prevented_if_global_comment_limit_is_reached(): void
    {
        // Arrange: Create an authenticated user
        $user = User::factory()->create();
        $this->actingAs($user);

        // Set a small limit for testing
        $limit = 2;
        Config::set('app_limits.max_comments', $limit); // Ensure this config key matches your middleware

        // Create posts to attach comments to
        $post1 = Post::factory()->create();
        $post2 = Post::factory()->create();
        // Potentially a third post if the limit is hit exactly by comments on different posts
        $post3 = Post::factory()->create();


        $commentData = ['content' => 'Test comment content'];

        // Act: Create comments up to the limit
        for ($i = 0; $i < $limit; $i++) {
            // Alternate posts to ensure comments are distinct if needed, though for a global limit it might not matter
            $currentPost = ($i % 2 == 0) ? $post1 : $post2;
            $response = $this->postJson(route('posts.comments.store', ['post' => $currentPost->id]), $commentData);
            $response->assertStatus(201); // Expect successful creation until limit
        }

        // Attempt to create one more comment, which should be blocked
        $responseOverLimit = $this->postJson(route('posts.comments.store', ['post' => $post3->id]), $commentData);

        // Assert
        $responseOverLimit->assertStatus(503); // Or whatever status your CheckLimit middleware returns
        $responseOverLimit->assertJson(['message' => 'The maximum number of allowed records has been reached. Cannot create new entries at this time.']); // Adjust message as per your middleware

        // Ensure only 'limit' comments were actually created in total
        $this->assertDatabaseCount('comments', $limit);
    }

    #[Test]
    public function authenticated_owner_can_update_their_comment_with_valid_data(): void
    {
        // Arrange: Create an authenticated user (owner) and their comment
        $owner = User::factory()->create();
        $this->actingAs($owner);

        $post = Post::factory()->create(); // Post the comment belongs to
        $comment = Comment::factory()->for($post)->for($owner)->create([
            'content' => 'Original comment content.',
        ]);

        // Introduce a small delay to ensure updated_at will be different from created_at
        // This helps in environments where operations are too fast for timestamp differences.
        sleep(1);

        $updatedData = [
            'content' => 'Updated comment content.',
        ];

        // Act: Make a PUT/PATCH request to the update comment endpoint
        $response = $this->putJson(route('comments.update', ['comment' => $comment->id]), $updatedData);

        // Assert
        $response->assertStatus(200); // Assert 200 OK status

        // Assert response structure matches CommentResource
        $response->assertJsonStructure([
            'data' => [
                'id',
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
            ],
            'message', // Assuming your controller adds a 'message'
        ]);

        // Assert the content of the updated comment in the response
        $response->assertJsonFragment([
            'id' => $comment->id,
            'content' => $updatedData['content'],
            'user' => [
                'id' => $owner->id,
                'name' => $owner->name,
                'email' => $owner->email,
                'created_at' => $owner->created_at->toIso8601String(),
                'updated_at' => $owner->updated_at->toIso8601String(),
            ]
        ]);
        if ($response->json('message')) { // Optional: check message if present
            $response->assertJson(['message' => 'Comment updated successfully']);
        }

        // Assert comment is updated in the database
        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'content' => $updatedData['content'],
            'user_id' => $owner->id, // Ensure user_id remains the same
        ]);

        // Ensure the updated_at timestamp has changed (or is later than created_at)
        $updatedComment = $comment->refresh();
        $this->assertTrue(
            $updatedComment->updated_at->gt($updatedComment->created_at) ||
            $updatedComment->updated_at->eq($updatedComment->created_at->addMicroseconds(1))
        );
        // A simpler check if you don't mind a slight imprecision or if updated_at is guaranteed to change:
        // $this->assertNotEquals($comment->created_at->toIso8601String(), $updatedComment->updated_at->toIso8601String());
    }

    #[Test]
    public function updating_a_comment_with_missing_content_field_returns_validation_error(): void
    {
        // Arrange: Create an authenticated user (owner) and their comment
        $owner = User::factory()->create();
        $this->actingAs($owner);

        $post = Post::factory()->create();
        $comment = Comment::factory()->for($post)->for($owner)->create([
            'content' => 'Original valid content.',
        ]);

        $invalidUpdateData = [
            'content' => '', // Assuming empty content is invalid (e.g., 'required' or 'min:1')
        ];

        // Act: Make a PUT request to update the comment with invalid data
        $response = $this->putJson(route('comments.update', ['comment' => $comment->id]), $invalidUpdateData);

        // Assert
        $response->assertStatus(422); // Assert 422 Unprocessable Entity status

        // Assert validation error for the 'content' field
        $response->assertJsonValidationErrors(['content']);
        // Example: If your rule is 'required', the message might be "The content field is required."
        // $response->assertJsonValidationErrorFor('content', 'The content field is required.');

        // Assert that the comment content in the database has not changed
        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'content' => 'Original valid content.', // Should remain unchanged
        ]);
    }

    #[Test]
    public function authenticated_user_who_is_not_the_owner_cannot_update_a_comment(): void
    {
        // Arrange: Create the comment owner and another authenticated user (non-owner)
        $owner = User::factory()->create();
        $nonOwner = User::factory()->create();

        $post = Post::factory()->create();
        $comment = Comment::factory()->for($post)->for($owner)->create([
            'content' => 'Original content by owner.',
        ]);

        // Act as the non-owner
        $this->actingAs($nonOwner);

        $updatedData = [
            'content' => 'Attempted update by non-owner.',
        ];

        // Act: Non-owner attempts to update the comment
        $response = $this->putJson(route('comments.update', ['comment' => $comment->id]), $updatedData);

        // Assert
        $response->assertStatus(403); // Assert 403 Forbidden status

        // Assert that the comment content in the database has not changed
        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'content' => 'Original content by owner.', // Should remain unchanged
        ]);
    }

    #[Test]
    public function unauthenticated_user_cannot_update_a_comment(): void
    {
        // Arrange: Create a comment (owner doesn't matter as request is unauthenticated)
        $owner = User::factory()->create();
        $post = Post::factory()->create();
        $comment = Comment::factory()->for($post)->for($owner)->create([
            'content' => 'Original content.',
        ]);

        $updatedData = [
            'content' => 'Attempted update by unauthenticated user.',
        ];

        // Act: Make a PUT request to update the comment without authentication
        // Note: $this->actingAs() is NOT called
        $response = $this->putJson(route('comments.update', ['comment' => $comment->id]), $updatedData);

        // Assert
        $response->assertStatus(401); // Assert 401 Unauthorized status
        $response->assertJson(['message' => 'Unauthenticated.']); // Default Laravel message

        // Assert that the comment content in the database has not changed
        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'content' => 'Original content.', // Should remain unchanged
        ]);
    }

    #[Test]
    public function updating_a_non_existent_comment_returns_404_not_found(): void
    {
        // Arrange: Create an authenticated user
        $user = User::factory()->create();
        $this->actingAs($user);

        $nonExistentCommentId = 99999; // An ID that is guaranteed not to exist

        $updatedData = [
            'content' => 'This update should fail because the comment does not exist.',
        ];

        // Act: Make a PUT request to update the non-existent comment
        $response = $this->putJson(route('comments.update', ['comment' => $nonExistentCommentId]), $updatedData);

        // Assert
        $response->assertStatus(404); // Assert 404 Not Found status
    }

    #[Test]
    public function updating_a_comment_is_rate_limited_for_authenticated_owner(): void
    {
        // Arrange: Create an authenticated user (owner) and their comment
        $owner = User::factory()->create();
        $this->actingAs($owner);

        $post = Post::factory()->create();
        $comment = Comment::factory()->for($post)->for($owner)->create([
            'content' => 'Original content for rate limit test.',
        ]);

        $updatedData = [
            'content' => 'Updated content for rate limit test.',
        ];

        $maxAttempts = Config::get('app_limits.throttle_api_limit', 60); // Default for 'api' throttle

        // Act: Hit the endpoint $maxAttempts times.
        // Each attempt should succeed (200 OK).
        for ($i = 0; $i < $maxAttempts; $i++) {
            // To make each request potentially unique for the rate limiter if it considers payload,
            // though for a resource update, the URL itself is often the key.
            // We'll update with slightly different content each time to ensure the update happens.
            $response = $this->putJson(route('comments.update', ['comment' => $comment->id]), [
                'content' => "Updated content attempt " . ($i + 1),
            ]);
            $response->assertStatus(200);
        }

        // The ($maxAttempts + 1)-th attempt should be rate limited (429)
        $response = $this->putJson(route('comments.update', ['comment' => $comment->id]), $updatedData);

        // Assert
        $response->assertStatus(429); // Too Many Requests
        $response->assertJson(['message' => 'Too Many Attempts.']);

        // Check headers for the rate-limited response
        $this->assertNotNull($response->headers->get('Retry-After'));
        $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));
        $this->assertEquals($maxAttempts, $response->headers->get('X-RateLimit-Limit'));
        $this->assertEquals(0, $response->headers->get('X-RateLimit-Remaining'));

        // Ensure the comment's content is the one from the last successful attempt
        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'content' => "Updated content attempt " . $maxAttempts,
        ]);
    }

    #[Test]
    public function authenticated_owner_can_delete_their_comment(): void
    {
        // Arrange: Create an authenticated user (owner) and their comment
        $owner = User::factory()->create();
        $this->actingAs($owner);

        $post = Post::factory()->create();
        $comment = Comment::factory()->for($post)->for($owner)->create();

        $commentId = $comment->id; // Store ID before deletion

        // Act: Make a DELETE request to the delete comment endpoint
        $response = $this->deleteJson(route('comments.destroy', ['comment' => $comment->id]));

        // Assert
        // Common practice is 204 No Content for successful DELETE, but 200 OK with a message is also acceptable.
        $response->assertStatus(200); // Or $response->assertStatus(204);
        if ($response->status() === 200) {
            $response->assertJson(['message' => 'Comment deleted successfully']); // If you return a message
        }


        // Assert comment is deleted from the database
        $this->assertDatabaseMissing('comments', ['id' => $commentId]);
        // Or, more specifically for soft deletes if you use them:
        // $this->assertSoftDeleted('comments', ['id' => $commentId]);
    }

    #[Test]
    public function authenticated_user_who_is_not_the_owner_cannot_delete_a_comment(): void
    {
        // Arrange: Create the comment owner and another authenticated user (non-owner)
        $owner = User::factory()->create();
        $nonOwner = User::factory()->create();

        $post = Post::factory()->create();
        $comment = Comment::factory()->for($post)->for($owner)->create();

        $commentId = $comment->id; // Store ID for assertion

        // Act as the non-owner
        $this->actingAs($nonOwner);

        // Act: Non-owner attempts to delete the comment
        $response = $this->deleteJson(route('comments.destroy', ['comment' => $comment->id]));

        // Assert
        $response->assertStatus(403); // Assert 403 Forbidden status

        // Assert that the comment still exists in the database
        $this->assertDatabaseHas('comments', ['id' => $commentId]);
        // If using soft deletes, you might also check it's not soft-deleted:
        // $this->assertNotSoftDeleted('comments', ['id' => $commentId]);
    }

    #[Test]
    public function unauthenticated_user_cannot_delete_a_comment(): void
    {
        // Arrange: Create a comment (owner doesn't matter as request is unauthenticated)
        $owner = User::factory()->create();
        $post = Post::factory()->create();
        $comment = Comment::factory()->for($post)->for($owner)->create();

        $commentId = $comment->id; // Store ID for assertion

        // Act: Make a DELETE request to delete the comment without authentication
        // Note: $this->actingAs() is NOT called
        $response = $this->deleteJson(route('comments.destroy', ['comment' => $comment->id]));

        // Assert
        $response->assertStatus(401); // Assert 401 Unauthorized status
        $response->assertJson(['message' => 'Unauthenticated.']); // Default Laravel message

        // Assert that the comment still exists in the database
        $this->assertDatabaseHas('comments', ['id' => $commentId]);
    }

    #[Test]
    public function deleting_a_non_existent_comment_returns_404_not_found(): void
    {
        // Arrange: Create an authenticated user
        $user = User::factory()->create();
        $this->actingAs($user);

        $nonExistentCommentId = 99999; // An ID that is guaranteed not to exist

        // Act: Make a DELETE request to delete the non-existent comment
        $response = $this->deleteJson(route('comments.destroy', ['comment' => $nonExistentCommentId]));

        // Assert
        $response->assertStatus(404); // Assert 404 Not Found status
    }

    #[Test]
    public function deleting_a_comment_is_rate_limited_for_authenticated_owner(): void
    {
        // Arrange: Create an authenticated user (owner) and their comments
        $owner = User::factory()->create();
        $this->actingAs($owner);

        $post = Post::factory()->create();

        $maxAttempts = Config::get('app_limits.throttle_api_limit', 60); // Default for 'api' throttle

        // Create $maxAttempts + 1 comments to try deleting
        $commentsToDelete = Comment::factory()->count($maxAttempts + 1)->for($post)->for($owner)->create();

        // Act: Hit the endpoint $maxAttempts times.
        // Each attempt should succeed (200 OK or 204 No Content).
        for ($i = 0; $i < $maxAttempts; $i++) {
            $comment = $commentsToDelete->get($i);
            $response = $this->deleteJson(route('comments.destroy', ['comment' => $comment->id]));
            $response->assertSuccessful(); // Asserts 2xx status code
            if ($response->status() === 200) {
                $response->assertJson(['message' => 'Comment deleted successfully']);
            }
            $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
        }

        // The ($maxAttempts + 1)-th attempt (on a different comment, but by the same user)
        // should be rate limited (429). The rate limiter for 'api' is typically by user ID or IP.
        $commentToFail = $commentsToDelete->get($maxAttempts);
        $response = $this->deleteJson(route('comments.destroy', ['comment' => $commentToFail->id]));

        // Assert
        $response->assertStatus(429); // Too Many Requests
        $response->assertJson(['message' => 'Too Many Attempts.']);

        // Check headers for the rate-limited response
        $this->assertNotNull($response->headers->get('Retry-After'));
        $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));
        $this->assertEquals($maxAttempts, $response->headers->get('X-RateLimit-Limit'));
        $this->assertEquals(0, $response->headers->get('X-RateLimit-Remaining'));

        // Ensure the last comment (the one that hit the rate limit) was NOT deleted
        $this->assertDatabaseHas('comments', ['id' => $commentToFail->id]);
    }
}
