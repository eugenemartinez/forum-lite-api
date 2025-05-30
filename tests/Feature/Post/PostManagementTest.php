<?php

namespace Tests\Feature\Post;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Post;
use App\Models\Comment; // Add this import
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Config;

class PostManagementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_can_fetch_a_list_of_posts(): void
    {
        // Arrange: Create a user and some posts
        $user = User::factory()->create();
        Post::factory()->count(3)->for($user)->create(); // Create 3 posts for this user
        Post::factory()->count(2)->create(); // Create 2 posts by other users

        // Act: Make a GET request to the posts index endpoint
        $response = $this->getJson(route('posts.index'));

        // Assert
        $response->assertStatus(200);

        // Assert pagination structure
        $response->assertJsonStructure([
            'data' => [
                '*' => [ // '*' means each item in the data array
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
                    // 'comments_count' is not loaded by default in index, only in show
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

        // Assert that we received 5 posts in total (across all pages, checked by meta.total)
        $response->assertJsonCount(5, 'data'); // Assuming default pagination is >= 5, or check meta.total
        $this->assertEquals(5, $response->json('meta.total'));


        // Assert that the posts are sorted by created_at descending (most recent first)
        // This will be more explicitly tested in a dedicated sorting test.
        // For now, we can check if the first post in the response is one of the recently created ones.
        $responseData = $response->json('data');
        $this->assertTrue(strtotime($responseData[0]['created_at']) >= strtotime($responseData[1]['created_at']));
    }

    #[Test]
    public function posts_are_paginated_correctly(): void
    {
        // Arrange: Create more posts than the default pagination count (e.g., 15 posts for a page size of 10)
        $perPage = 10; // Matches your controller's paginate(10)
        Post::factory()->count($perPage + 5)->create();

        // Act: Request the first page
        $responsePage1 = $this->getJson(route('posts.index'));

        // Assert: First page
        $responsePage1->assertStatus(200)
            ->assertJsonCount($perPage, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', $perPage)
            ->assertJsonPath('meta.total', $perPage + 5);

        $firstPageIds = collect($responsePage1->json('data'))->pluck('id');

        // Act: Request the second page
        $responsePage2 = $this->getJson(route('posts.index') . '?page=2');

        // Assert: Second page
        $responsePage2->assertStatus(200)
            ->assertJsonCount(5, 'data') // Remaining 5 posts
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', $perPage)
            ->assertJsonPath('meta.total', $perPage + 5);

        $secondPageIds = collect($responsePage2->json('data'))->pluck('id');

        // Assert that items on page 2 are different from page 1
        $this->assertEmpty($firstPageIds->intersect($secondPageIds)->all(), "Posts from page 1 should not appear on page 2.");

        // Act: Request a page beyond the last page
        $responsePage3 = $this->getJson(route('posts.index') . '?page=3'); // Assuming only 2 pages of data

        // Assert: Empty data for a page that doesn't exist
        $responsePage3->assertStatus(200)
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.current_page', 3)
            ->assertJsonPath('meta.total', $perPage + 5);
    }

    #[Test]
    public function posts_are_sorted_by_created_at_descending_by_default(): void
    {
        // Arrange: Create posts with varying creation times
        $oldestPost = Post::factory()->create(['created_at' => now()->subDays(2)]);
        $middlePost = Post::factory()->create(['created_at' => now()->subDays(1)]);
        $newestPost = Post::factory()->create(['created_at' => now()]); // Most recent

        // Act: Fetch posts
        $response = $this->getJson(route('posts.index'));

        // Assert
        $response->assertStatus(200);

        $responseData = $response->json('data');

        // Check that the order of IDs in the response matches the expected order (newest to oldest)
        $this->assertEquals([
            $newestPost->id,
            $middlePost->id,
            $oldestPost->id,
        ], collect($responseData)->pluck('id')->all());
    }

    #[Test]
    public function listing_posts_is_rate_limited(): void
    {
        // The 'api' throttle is typically 60 attempts per minute.
        // We'll use a lower number for faster testing if we could configure it per test,
        // but for now, we'll assume the default.
        // To make this test run faster and be more reliable without hitting external services
        // or waiting a full minute, it's best if the 'api' throttle limit
        // can be temporarily lowered for testing, or if we test just over the threshold.
        // For now, let's simulate just enough to trigger it.

        $maxAttempts = Config::get('app_limits.throttle_api_limit', 60); // Get from a config or use default
        $decayMinutes = 1; // Standard decay for 'api' throttle

        // Create some posts so the endpoint has data
        Post::factory()->count(3)->create();

        // Hit the endpoint $maxAttempts times, expecting 200 OK
        for ($i = 0; $i < $maxAttempts; $i++) {
            $response = $this->getJson(route('posts.index'));
            $response->assertStatus(200);
        }

        // The ($maxAttempts + 1)-th attempt should be rate limited (429)
        $response = $this->getJson(route('posts.index'));
        $response->assertStatus(429); // Too Many Requests
        $response->assertJson(['message' => 'Too Many Attempts.']); // Default Laravel message

        // Check headers for the rate-limited response
        $this->assertNotNull($response->headers->get('Retry-After'));
        $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));
        $this->assertEquals($maxAttempts, $response->headers->get('X-RateLimit-Limit'));
        // Remaining should be 0 after hitting the limit
        $this->assertEquals(0, $response->headers->get('X-RateLimit-Remaining'));
    }

    #[Test]
    public function user_can_fetch_an_existing_post_with_details_and_comment_count(): void
    {
        // Arrange: Create a user, a post by this user, and some comments for the post
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->create();
        Comment::factory()->count(3)->for($post)->for(User::factory())->create(); // 3 comments by some other users
        Comment::factory()->count(2)->for($post)->for($user)->create(); // 2 comments by the post author

        // Act: Make a GET request to the post show endpoint
        // No authentication needed if policy allows guests
        $response = $this->getJson(route('posts.show', ['post' => $post->id]));

        // Assert
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
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
                'comments_count', // Ensure this is present
            ]
        ]);

        $response->assertJson([
            'data' => [
                'id' => $post->id,
                'title' => $post->title,
                'content' => $post->content,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'comments_count' => 5, // 3 + 2 comments
            ]
        ]);
    }

    #[Test]
    public function fetching_a_non_existent_post_returns_404_not_found(): void
    {
        // Arrange: Determine an ID that is guaranteed not to exist.
        // For example, if posts are auto-incrementing, find the max ID and add 1, or use a large number.
        $nonExistentPostId = 99999; // Assuming this ID won't exist
        // A more robust way if you have existing posts:
        // $maxId = Post::max('id') ?? 0;
        // $nonExistentPostId = $maxId + 1;

        // Act: Make a GET request to the post show endpoint with the non-existent ID
        $response = $this->getJson(route('posts.show', ['post' => $nonExistentPostId]));

        // Assert
        $response->assertStatus(404);
    }

    #[Test]
    public function viewing_a_single_post_is_rate_limited(): void
    {
        // Arrange: Create a post to view
        $post = Post::factory()->create();

        $maxAttempts = Config::get('app_limits.throttle_api_limit', 60); // Get from a config or use default

        // Act: Hit the endpoint $maxAttempts times, expecting 200 OK
        for ($i = 0; $i < $maxAttempts; $i++) {
            $response = $this->getJson(route('posts.show', ['post' => $post->id]));
            $response->assertStatus(200);
        }

        // The ($maxAttempts + 1)-th attempt should be rate limited (429)
        $response = $this->getJson(route('posts.show', ['post' => $post->id]));

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
    public function authenticated_user_can_create_a_post_with_valid_data(): void
    {
        // Arrange: Create an authenticated user
        $user = User::factory()->create();
        $this->actingAs($user); // Authenticate the user for the next request

        $postData = [
            'title' => 'My First Awesome Post',
            'content' => 'This is the exciting content of my very first post!',
        ];

        // Act: Make a POST request to the posts store endpoint
        $response = $this->postJson(route('posts.store'), $postData);

        // Assert
        $response->assertStatus(201); // Assert 201 Created status

        // Assert response structure matches PostResource (including the user)
        $response->assertJsonStructure([
            'data' => [
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
                // 'comments_count' is not typically included on create, but PostResource might add it.
                // If PostResource always adds comments_count (even if 0), uncomment below:
                // 'comments_count',
            ]
        ]);

        // Assert the response data matches the input and the authenticated user
        $response->assertJson([
            'data' => [
                'title' => $postData['title'],
                'content' => $postData['content'],
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                // If PostResource adds comments_count:
                // 'comments_count' => 0,
            ]
        ]);

        // Assert post is created in the database with correct user_id
        $this->assertDatabaseHas('posts', [
            'title' => $postData['title'],
            'content' => $postData['content'],
            'user_id' => $user->id,
        ]);

        // Optionally, check the ID from the response matches the one in the DB
        $createdPostId = $response->json('data.id');
        $this->assertDatabaseHas('posts', ['id' => $createdPostId, 'user_id' => $user->id]);
    }

    #[Test]
    public function authenticated_user_cannot_create_a_post_with_missing_required_fields(): void
    {
        // Arrange: Create an authenticated user
        $user = User::factory()->create();
        $this->actingAs($user);

        // Case 1: Missing title
        $responseMissingTitle = $this->postJson(route('posts.store'), [
            'content' => 'This post has content but no title.',
        ]);

        $responseMissingTitle->assertStatus(422)
            ->assertJsonValidationErrors(['title'])
            ->assertJsonMissingValidationErrors(['content']); // Ensure only title is the error

        // Case 2: Missing content
        $responseMissingContent = $this->postJson(route('posts.store'), [
            'title' => 'This Post Has A Title',
        ]);

        $responseMissingContent->assertStatus(422)
            ->assertJsonValidationErrors(['content'])
            ->assertJsonMissingValidationErrors(['title']);

        // Case 3: Missing both title and content
        $responseMissingBoth = $this->postJson(route('posts.store'), []);

        $responseMissingBoth->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'content']);

        // Assert that no posts were created in any of these invalid attempts
        $this->assertDatabaseCount('posts', 0);
    }

    #[Test]
    public function unauthenticated_user_cannot_create_a_post(): void
    {
        // Arrange: Prepare valid post data, but do not authenticate any user
        $postData = [
            'title' => 'A Post by a Ghost',
            'content' => 'This content should never make it to the database.',
        ];

        // Act: Make a POST request to the posts store endpoint without authentication
        $response = $this->postJson(route('posts.store'), $postData);

        // Assert
        $response->assertStatus(401); // Assert 401 Unauthorized status
        $response->assertJson(['message' => 'Unauthenticated.']); // Default Laravel message

        // Assert that no post was created in the database
        $this->assertDatabaseCount('posts', 0);
        // Or more specifically, that this particular post wasn't created:
        $this->assertDatabaseMissing('posts', [
            'title' => $postData['title'],
        ]);
    }

    #[Test]
    public function creating_a_post_is_rate_limited_for_authenticated_user(): void
    {
        // Arrange: Create an authenticated user
        $user = User::factory()->create();
        $this->actingAs($user);

        $postData = [
            'title' => 'Rate Limit Test Post',
            'content' => 'Content for rate limit test.',
        ];

        $maxAttempts = Config::get('app_limits.throttle_api_limit', 60); // Default for 'api' throttle

        // Act: Hit the endpoint $maxAttempts times.
        // The first attempt should succeed (201), subsequent ones might fail validation
        // if we tried to create the exact same post (e.g. unique title if that was a rule),
        // but for rate limiting, even failed validation (422) or successful (201) requests count.
        // For simplicity, we'll vary the title slightly to ensure each attempt could be "valid" if not for rate limit.
        for ($i = 0; $i < $maxAttempts; $i++) {
            $currentPostData = array_merge($postData, ['title' => $postData['title'] . ' ' . ($i + 1)]);
            $response = $this->postJson(route('posts.store'), $currentPostData);

            // Expect 201 Created for each attempt until the limit is hit
            // (assuming no other validation failures like unique constraints on title if they existed)
            $response->assertStatus(201);
        }

        // The ($maxAttempts + 1)-th attempt should be rate limited (429)
        $finalPostData = array_merge($postData, ['title' => $postData['title'] . ' ' . ($maxAttempts + 1)]);
        $response = $this->postJson(route('posts.store'), $finalPostData);

        // Assert
        $response->assertStatus(429); // Too Many Requests
        $response->assertJson(['message' => 'Too Many Attempts.']);

        // Check headers for the rate-limited response
        $this->assertNotNull($response->headers->get('Retry-After'));
        $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));
        $this->assertEquals($maxAttempts, $response->headers->get('X-RateLimit-Limit'));
        $this->assertEquals(0, $response->headers->get('X-RateLimit-Remaining'));

        // Ensure only $maxAttempts posts were actually created
        $this->assertDatabaseCount('posts', $maxAttempts);
    }

    #[Test]
    public function creating_a_post_is_limited_by_post_table_capacity(): void
    {
        // Arrange: Create an authenticated user
        $user = User::factory()->create();
        $this->actingAs($user);

        // Set a small, manageable limit for posts for this test
        $testSpecificPostLimit = 2;
        Config::set('app_limits.max_posts', $testSpecificPostLimit); // Assuming 'max_posts' is your config key

        // Create posts up to the test-specific limit
        for ($i = 1; $i <= $testSpecificPostLimit; $i++) {
            Post::factory()->for($user)->create(['title' => "Post {$i} within limit"]);
        }
        $this->assertDatabaseCount('posts', $testSpecificPostLimit);

        // Attempt to register one more post
        $overLimitPostData = [
            'title' => 'Over The Limit Post',
            'content' => 'This post should not be created.',
        ];

        $response = $this->postJson(route('posts.store'), $overLimitPostData);

        // Assert that the middleware blocked the request
        $response->assertStatus(503); // Service Unavailable
        // Assert the specific message from your CheckModelLimit middleware
        $response->assertJson([
            'message' => 'The maximum number of allowed records has been reached. Cannot create new entries at this time.',
        ]);

        // Assert that the extra post was not created
        $this->assertDatabaseCount('posts', $testSpecificPostLimit);
        $this->assertDatabaseMissing('posts', [
            'title' => 'Over The Limit Post',
        ]);
    }

    #[Test]
    public function authenticated_owner_can_update_their_post_with_valid_data(): void
    {
        // Arrange: Create a user (owner) and a post by this user
        $owner = User::factory()->create();
        $post = Post::factory()->for($owner)->create([
            'title' => 'Original Title',
            'content' => 'Original content.',
        ]);

        // Authenticate as the owner
        $this->actingAs($owner);

        // Ensure at least one second passes to guarantee updated_at changes at second-level precision
        sleep(1); // <--- ADD THIS LINE

        $updatedData = [
            'title' => 'Updated Awesome Title',
            'content' => 'This content has been significantly improved.',
        ];

        // Act: Make a PUT request to the posts update endpoint
        $response = $this->putJson(route('posts.update', ['post' => $post->id]), $updatedData);

        // Assert
        $response->assertStatus(200); // Assert 200 OK status

        // Assert response structure matches PostResource
        $response->assertJsonStructure([
            'data' => [
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
                // 'comments_count' might be included by PostResource
                // 'comments_count',
            ]
        ]);

        // Assert the response data matches the updated data and the owner's details
        $response->assertJson([
            'data' => [
                'id' => $post->id,
                'title' => $updatedData['title'],
                'content' => $updatedData['content'],
                'user' => [
                    'id' => $owner->id,
                    'name' => $owner->name,
                    'email' => $owner->email,
                ],
            ]
        ]);

        // Assert post is updated in the database
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => $updatedData['title'],
            'content' => $updatedData['content'],
            'user_id' => $owner->id,
        ]);

        // Ensure the created_at timestamp hasn't changed, but updated_at has.
        $dbPost = Post::find($post->id); // Fetch fresh
        $this->assertEquals($post->created_at->toIso8601String(), $dbPost->created_at->toIso8601String());
        $this->assertNotEquals($post->updated_at->toIso8601String(), $dbPost->updated_at->toIso8601String()); // This should now pass
        // Or more simply, check that updated_at is more recent than the original updated_at
        $this->assertTrue($dbPost->updated_at->gt($post->updated_at)); // This is also a good check
    }

    #[Test]
    public function authenticated_owner_can_partially_update_their_post_and_validation_works_for_provided_invalid_fields(): void
    {
        // Arrange: Create a user (owner) and a post by this user
        $owner = User::factory()->create();
        $post = Post::factory()->for($owner)->create([
            'title' => 'Original Title',
            'content' => 'Original content.',
        ]);
        $this->actingAs($owner);

        $originalTitle = $post->title;
        $originalContent = $post->content;

        // --- Case 1: Update only content (title omitted) ---
        $newContent = 'Updated content only, title should remain original.';
        $responseUpdateContent = $this->putJson(route('posts.update', ['post' => $post->id]), [
            'content' => $newContent,
        ]);

        $responseUpdateContent->assertStatus(200) // Expect 200 OK
            ->assertJson([
                'data' => [
                    'title' => $originalTitle, // Title should be unchanged
                    'content' => $newContent,
                ]
            ]);
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => $originalTitle,
            'content' => $newContent,
        ]);

        // --- Case 2: Update only title (content omitted) ---
        // Refresh post model to get the latest state (after content update)
        $post->refresh();
        $originalContentAfterContentUpdate = $post->content; // This is now $newContent

        $newTitle = 'Updated title only, content should remain as is.';
        $responseUpdateTitle = $this->putJson(route('posts.update', ['post' => $post->id]), [
            'title' => $newTitle,
        ]);

        $responseUpdateTitle->assertStatus(200) // Expect 200 OK
            ->assertJson([
                'data' => [
                    'title' => $newTitle,
                    'content' => $originalContentAfterContentUpdate, // Content should be unchanged from previous step
                ]
            ]);
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => $newTitle,
            'content' => $originalContentAfterContentUpdate,
        ]);

        // --- Case 3: Attempt to update title with empty string (should fail validation) ---
        // Refresh post model
        $post->refresh();
        $currentContent = $post->content; // Content from previous successful update

        $responseInvalidTitle = $this->putJson(route('posts.update', ['post' => $post->id]), [
            'title' => '', // Invalid: empty string, but 'title' is 'required' if present
        ]);
        $responseInvalidTitle->assertStatus(422)
            ->assertJsonValidationErrors(['title'])
            ->assertJsonMissingValidationErrors(['content']); // Content was not sent, so no error for it

        // Ensure post was not changed by the invalid attempt
        $this->assertDatabaseHas('posts', ['id' => $post->id, 'title' => $newTitle, 'content' => $currentContent]);


        // --- Case 4: Attempt to update content with empty string (should fail validation) ---
        $responseInvalidContent = $this->putJson(route('posts.update', ['post' => $post->id]), [
            'content' => '', // Invalid: empty string, but 'content' is 'required' if present
        ]);
        $responseInvalidContent->assertStatus(422)
            ->assertJsonValidationErrors(['content'])
            ->assertJsonMissingValidationErrors(['title']);

        // Ensure post was not changed
        $this->assertDatabaseHas('posts', ['id' => $post->id, 'title' => $newTitle, 'content' => $currentContent]);

        // --- Case 5: Sending empty payload (should result in 200 OK, no changes) ---
        // This is because 'sometimes' means fields are only validated if present.
        // If no fields are present, validation passes, and no updates occur.
        $responseEmptyPayload = $this->putJson(route('posts.update', ['post' => $post->id]), []);
        $responseEmptyPayload->assertStatus(200);
        // Ensure post was not changed by the empty payload
        $this->assertDatabaseHas('posts', ['id' => $post->id, 'title' => $newTitle, 'content' => $currentContent]);
    }

    #[Test]
    public function authenticated_user_cannot_update_a_post_they_do_not_own(): void
    {
        // Arrange: Create the post owner and another user (attacker)
        $owner = User::factory()->create();
        $post = Post::factory()->for($owner)->create([
            'title' => 'Owner Post Title',
            'content' => 'Owner post content.',
        ]);

        $attacker = User::factory()->create();
        $this->actingAs($attacker); // Authenticate as the attacker

        $updatedData = [
            'title' => 'Attacker Updated Title',
            'content' => 'Attacker updated content.',
        ];

        // Act: Attacker attempts to update the owner's post
        $response = $this->putJson(route('posts.update', ['post' => $post->id]), $updatedData);

        // Assert
        $response->assertStatus(403); // Assert 403 Forbidden status

        // Assert that the post was not updated in the database
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Owner Post Title', // Original title
            'content' => 'Owner post content.', // Original content
        ]);
    }

    #[Test]
    public function unauthenticated_user_cannot_update_a_post(): void
    {
        // Arrange: Create a post (owner doesn't matter here as the request is unauthenticated)
        $post = Post::factory()->create([
            'title' => 'Original Title',
            'content' => 'Original content.',
        ]);

        $updatedData = [
            'title' => 'Unauthenticated Update Attempt',
            'content' => 'This should not go through.',
        ];

        // Act: Make a PUT request to the posts update endpoint without authentication
        $response = $this->putJson(route('posts.update', ['post' => $post->id]), $updatedData);

        // Assert
        $response->assertStatus(401); // Assert 401 Unauthorized status
        $response->assertJson(['message' => 'Unauthenticated.']); // Default Laravel message

        // Assert that the post was not updated in the database
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Original Title', // Original title
            'content' => 'Original content.', // Original content
        ]);
    }

    #[Test]
    public function updating_a_non_existent_post_returns_404_not_found(): void
    {
        // Arrange: Authenticate a user (their identity doesn't matter as much as the post not existing)
        $user = User::factory()->create();
        $this->actingAs($user);

        $nonExistentPostId = 99999; // An ID that is guaranteed not to exist
        // A more robust way if you have existing posts:
        // $maxId = Post::max('id') ?? 0;
        // $nonExistentPostId = $maxId + 1;

        $updatedData = [
            'title' => 'Attempt to Update Non-Existent Post',
            'content' => 'This should not work.',
        ];

        // Act: Make a PUT request to the posts update endpoint with the non-existent ID
        $response = $this->putJson(route('posts.update', ['post' => $nonExistentPostId]), $updatedData);

        // Assert
        $response->assertStatus(404);
    }

    #[Test]
    public function updating_a_post_is_rate_limited_for_authenticated_user(): void
    {
        // Arrange: Create an authenticated user and a post they own
        $owner = User::factory()->create();
        $post = Post::factory()->for($owner)->create();
        $this->actingAs($owner);

        $updatedData = [
            'title' => 'Rate Limit Update Title',
            'content' => 'Content for rate limit update test.',
        ];

        $maxAttempts = Config::get('app_limits.throttle_api_limit', 60); // Default for 'api' throttle

        // Act: Hit the endpoint $maxAttempts times.
        // Each attempt should succeed (200 OK) as we are updating the same post.
        for ($i = 0; $i < $maxAttempts; $i++) {
            // Vary data slightly if needed, though for updates to the same resource, it might not matter
            $currentUpdateData = array_merge($updatedData, ['title' => $updatedData['title'] . ' ' . ($i + 1)]);
            $response = $this->putJson(route('posts.update', ['post' => $post->id]), $currentUpdateData);
            $response->assertStatus(200);
        }

        // The ($maxAttempts + 1)-th attempt should be rate limited (429)
        $finalUpdateData = array_merge($updatedData, ['title' => $updatedData['title'] . ' ' . ($maxAttempts + 1)]);
        $response = $this->putJson(route('posts.update', ['post' => $post->id]), $finalUpdateData);

        // Assert
        $response->assertStatus(429); // Too Many Requests
        $response->assertJson(['message' => 'Too Many Attempts.']);

        // Check headers for the rate-limited response
        $this->assertNotNull($response->headers->get('Retry-After'));
        $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));
        $this->assertEquals($maxAttempts, $response->headers->get('X-RateLimit-Limit'));
        $this->assertEquals(0, $response->headers->get('X-RateLimit-Remaining'));

        // Ensure the post's final state reflects the last successful update (the $maxAttempts-th one)
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => $updatedData['title'] . ' ' . $maxAttempts,
        ]);
    }

    #[Test]
    public function authenticated_owner_can_delete_their_post(): void
    {
        // Arrange: Create a user (owner), a post by this user, and some comments on the post
        $owner = User::factory()->create();
        $post = Post::factory()->for($owner)->create();
        Comment::factory(3)->for($post)->for($owner)->create(); // Create 3 comments for this post

        $this->assertDatabaseCount('posts', 1);
        $this->assertDatabaseCount('comments', 3);

        // Authenticate as the owner
        $this->actingAs($owner);

        // Act: Make a DELETE request to the posts destroy endpoint
        $response = $this->deleteJson(route('posts.destroy', ['post' => $post->id]));

        // Assert
        $response->assertStatus(200); // Or 204 if your controller returns that
        $response->assertJson(['message' => 'Post deleted successfully']); // If you return a message

        // Assert post is deleted from the database
        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
        // A more robust check for soft deletes if you were using them:
        // $this->assertSoftDeleted('posts', ['id' => $post->id]);

        // Assert associated comments are deleted (if cascade delete is set up)
        // If you don't have cascade delete, these comments would remain, and this assertion would fail.
        // In that case, you might need to manually delete comments or adjust the assertion.
        $this->assertDatabaseMissing('comments', ['post_id' => $post->id]);
        $this->assertDatabaseCount('comments', 0);
    }

    #[Test]
    public function authenticated_user_cannot_delete_a_post_they_do_not_own(): void
    {
        // Arrange: Create the post owner and another user (attacker)
        $owner = User::factory()->create();
        $post = Post::factory()->for($owner)->create();

        $attacker = User::factory()->create();
        $this->actingAs($attacker); // Authenticate as the attacker

        $this->assertDatabaseHas('posts', ['id' => $post->id]); // Ensure post exists before attempt

        // Act: Attacker attempts to delete the owner's post
        $response = $this->deleteJson(route('posts.destroy', ['post' => $post->id]));

        // Assert
        $response->assertStatus(403); // Assert 403 Forbidden status

        // Assert that the post was not deleted from the database
        $this->assertDatabaseHas('posts', ['id' => $post->id]);
    }

    #[Test]
    public function unauthenticated_user_cannot_delete_a_post(): void
    {
        // Arrange: Create a post (owner doesn't matter here as the request is unauthenticated)
        $post = Post::factory()->create();

        $this->assertDatabaseHas('posts', ['id' => $post->id]); // Ensure post exists

        // Act: Make a DELETE request to the posts destroy endpoint without authentication
        $response = $this->deleteJson(route('posts.destroy', ['post' => $post->id]));

        // Assert
        $response->assertStatus(401); // Assert 401 Unauthorized status
        $response->assertJson(['message' => 'Unauthenticated.']); // Default Laravel message

        // Assert that the post was not deleted from the database
        $this->assertDatabaseHas('posts', ['id' => $post->id]);
    }

    #[Test]
    public function deleting_a_non_existent_post_returns_404_not_found(): void
    {
        // Arrange: Authenticate a user (their identity doesn't matter as much as the post not existing)
        $user = User::factory()->create();
        $this->actingAs($user);

        $nonExistentPostId = 99999; // An ID that is guaranteed not to exist
        // A more robust way if you have existing posts:
        // $maxId = Post::max('id') ?? 0;
        // $nonExistentPostId = $maxId + 1;

        // Act: Make a DELETE request to the posts destroy endpoint with the non-existent ID
        $response = $this->deleteJson(route('posts.destroy', ['post' => $nonExistentPostId]));

        // Assert
        $response->assertStatus(404);
    }

    #[Test]
    public function deleting_a_post_is_rate_limited_for_authenticated_user(): void
    {
        // Arrange: Create an authenticated user
        $owner = User::factory()->create();
        $this->actingAs($owner);

        $maxAttempts = Config::get('app_limits.throttle_api_limit', 60); // Default for 'api' throttle

        // Create posts to be deleted
        $postsToDelete = Post::factory($maxAttempts + 1)->for($owner)->create();

        // Act: Delete posts up to the $maxAttempts limit.
        for ($i = 0; $i < $maxAttempts; $i++) {
            $post = $postsToDelete->get($i);
            $response = $this->deleteJson(route('posts.destroy', ['post' => $post->id]));
            $response->assertStatus(200); // Expect successful deletion
        }

        // The ($maxAttempts + 1)-th attempt to delete another post should be rate limited (429)
        $postOverLimit = $postsToDelete->get($maxAttempts);
        $response = $this->deleteJson(route('posts.destroy', ['post' => $postOverLimit->id]));

        // Assert
        $response->assertStatus(429); // Too Many Requests
        $response->assertJson(['message' => 'Too Many Attempts.']);

        // Check headers for the rate-limited response
        $this->assertNotNull($response->headers->get('Retry-After'));
        $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));
        $this->assertEquals($maxAttempts, $response->headers->get('X-RateLimit-Limit'));
        $this->assertEquals(0, $response->headers->get('X-RateLimit-Remaining'));

        // Ensure only $maxAttempts posts were actually deleted
        // The last post ($postOverLimit) should still exist.
        $this->assertDatabaseCount('posts', 1); // Only the one that hit the rate limit should remain
        $this->assertDatabaseHas('posts', ['id' => $postOverLimit->id]);
    }

}
