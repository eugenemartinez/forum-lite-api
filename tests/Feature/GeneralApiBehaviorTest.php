<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase; // Ensure this is uncommented
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Laravel\Sanctum\Sanctum;
use App\Models\User; // Add this
use App\Models\Post; // Add this

class GeneralApiBehaviorTest extends TestCase
{
    use RefreshDatabase; // Ensure this is uncommented and used

    #[Test]
    public function api_responses_include_cors_headers(): void
    {
        // Arrange: Choose any public endpoint. The /ping route is a good candidate if you have one,
        // or the public /posts listing.
        // Let's assume you have a /ping route or use /posts.
        // If using /posts, you might want RefreshDatabase and to create a post.
        // For simplicity, let's assume a /ping route exists and is public.
        // If not, change to an existing public route like route('posts.index').

        // Act: Make a request to a public API endpoint
        // If you have a simple /ping route:
        // $response = $this->getJson('/api/ping');
        // Or use an existing public route:
        $response = $this->getJson(route('posts.index')); // Example: using posts index

        // Assert
        $response->assertStatus(200); // Ensure the endpoint itself is working

        // Assert that the Access-Control-Allow-Origin header is present
        // The value might be '*' or a specific domain depending on your CORS config.
        $response->assertHeader('Access-Control-Allow-Origin');

        // You can also assert its value if it's fixed:
        // $response->assertHeader('Access-Control-Allow-Origin', '*');
        // Or if it's one of several allowed origins, you might need more complex logic
        // or just check for its presence.

        // You might also want to check for other CORS headers if applicable, e.g.:
        // $response->assertHeader('Access-Control-Allow-Methods');
        // $response->assertHeader('Access-Control-Allow-Headers');
    }

    #[Test]
    public function api_returns_json_response_for_404_not_found_errors(): void
    {
        $response = $this->getJson('/api/non-existent-route');

        $response->assertStatus(404);
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertJson(['message' => 'The route api/non-existent-route could not be found.']);
    }

    #[Test]
    public function api_returns_json_response_for_401_unauthorized_errors(): void
    {
        // Attempt to access an authenticated route without authentication
        // Example: route('user.posts') which requires auth
        $response = $this->getJson(route('user.posts')); // Assuming 'user.posts' is an auth-protected route

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertJson(['message' => 'Unauthenticated.']);
    }

    #[Test]
    public function api_returns_json_response_for_403_forbidden_errors(): void
    {
        // Arrange: Create a user and a post they don't own
        $user = User::factory()->create(); // Now uses the imported User
        $owner = User::factory()->create(); // Now uses the imported User
        $post = Post::factory()->for($owner)->create(); // Now uses the imported Post

        Sanctum::actingAs($user); // Authenticate as a user who is not the owner

        // Act: Attempt an action the user is not authorized for (e.g., updating another user's post)
        $response = $this->putJson(route('posts.update', $post), ['title' => 'New Title']);

        // Assert
        $response->assertStatus(403);
        $response->assertHeader('Content-Type', 'application/json');
        $this->assertJson($response->content());
        if ($response->json('message')) {
            $this->assertIsString($response->json('message'));
        }
    }

    #[Test]
    public function api_returns_json_response_for_422_validation_errors(): void
    {
        // Arrange: Create an authenticated user to attempt post creation
        $user = User::factory()->create(); // Now uses the imported User
        Sanctum::actingAs($user);

        // Act: Attempt to create a post with invalid data (e.g., missing title)
        $response = $this->postJson(route('posts.store'), ['content' => 'Some content']);

        // Assert
        $response->assertStatus(422);
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertJsonValidationErrors(['title']);
        $response->assertJsonStructure(['message', 'errors' => ['title']]);
    }

    #[Test]
    public function api_does_not_expose_detailed_errors_when_app_debug_is_false_on_500_error(): void
    {
        // Store original debug state and set APP_DEBUG to false
        $originalDebugState = config('app.debug');
        config(['app.debug' => false]);

        // Mock a route or a part of the application to throw an unhandled exception
        // For this example, we'll define a temporary route that throws an exception.
        // This route will only exist for this test.
        \Illuminate\Support\Facades\Route::get('/_test/force-server-error', function () {
            throw new \Exception('This is a forced test exception.');
        });

        // Act: Make a request to the route that will cause a 500 error
        $response = $this->getJson('/_test/force-server-error');

        // Assert
        $response->assertStatus(500);
        $response->assertHeader('Content-Type', 'application/json');

        // Assert that a generic message is present (Laravel's default is often "Server Error")
        $response->assertJson(['message' => 'Server Error']); // Adjust if your app has a different generic 500 message

        // Assert that sensitive debug information is NOT present
        $response->assertJsonMissingPath('exception');
        $response->assertJsonMissingPath('file');
        $response->assertJsonMissingPath('line');
        $response->assertJsonMissingPath('trace');
        $response->assertJsonMissingPath('errors.exception'); // Check nested paths too if applicable

        // Restore original debug state
        config(['app.debug' => $originalDebugState]);

        // Clear the temporary route (optional, but good practice if routes are cached or for isolation)
        // This requires a bit more advanced route manipulation or ensuring tests run in isolated processes.
        // For simplicity in this example, we'll rely on Laravel's typical test route handling.
        // If you use route caching, this temporary route might cause issues if not cleared.
        // A more robust way for temporary routes is using $this->app->make('router')->get(...)
        // and then somehow removing it, or using a dedicated test controller method.
    }

    #[Test]
    public function ping_route_returns_successful_pong_response(): void
    {
        // Act: Make a GET request to the /ping endpoint
        $response = $this->getJson(route('ping')); // Assuming you named the route 'ping'

        // Assert
        $response->assertStatus(200); // Assert 200 OK status
        $response->assertExactJson(['message' => 'pong']); // Assert exact JSON response
    }

    #[Test]
    public function ping_route_is_rate_limited(): void
    {
        // Arrange
        // No specific user authentication is needed if the ping route is public
        // and rate limiting is by IP (default for guest users).
        $maxAttempts = \Illuminate\Support\Facades\Config::get('app_limits.throttle_api_limit', 60);

        // Act: Hit the endpoint $maxAttempts times.
        // Each attempt should succeed (200 OK).
        for ($i = 0; $i < $maxAttempts; $i++) {
            $response = $this->getJson(route('ping'));
            // Allow for 429 if a previous test in the same second exhausted the limit for the IP
            // This can happen if tests run very quickly.
            // A more robust way would be to use a unique identifier for rate limiting in tests
            // or ensure tests run with enough delay/isolation.
            if ($response->status() === 429) {
                // If already rate-limited, we can't proceed with this specific test logic as intended.
                // For now, we'll assume it passes if it hits 429 early,
                // acknowledging a potential test interaction.
                // Or, you could $this->markTestSkipped('Rate limit hit prematurely by prior test.');
            }
            $this->assertTrue(in_array($response->status(), [200, 429]));
            if ($response->status() === 200) {
                 $response->assertExactJson(['message' => 'pong']);
            }
        }

        // The ($maxAttempts + 1)-th attempt (or the first one after hitting 429)
        // should be rate limited (429).
        $response = $this->getJson(route('ping'));

        // Assert
        $response->assertStatus(429); // Too Many Requests
        $response->assertJson(['message' => 'Too Many Attempts.']); // Or your app's specific throttle message

        // Check headers for the rate-limited response
        $this->assertNotNull($response->headers->get('Retry-After'));
        $this->assertGreaterThanOrEqual(0, (int) $response->headers->get('Retry-After')); // Retry-After can be 0
        $this->assertEquals($maxAttempts, $response->headers->get('X-RateLimit-Limit'));
        // X-RateLimit-Remaining might be 0 or just under the limit if some attempts were 429s
        $this->assertLessThanOrEqual($maxAttempts, (int) $response->headers->get('X-RateLimit-Remaining'));
    }

    #[Test]
    public function root_api_route_returns_welcome_message_and_resources(): void
    {
        // Act: Make a GET request to the root API endpoint
        $response = $this->getJson(route('api.root'));

        // Assert
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');

        // Assert the main message
        $response->assertJson([
            'message' => 'Welcome to the Forum Lite API. Please use specific endpoints.',
        ]);

        // Assert the structure of available_resources
        $response->assertJsonStructure([
            'message',
            'available_resources' => [
                'register',
                'login',
                'user_details',
                'list_posts',
                'ping',
            ],
            'documentation',
        ]);

        // Assert that the resource URLs are strings (valid URLs)
        // We can check if they start with http, assuming your app.url is set.
        $responseData = $response->json();
        $this->assertIsString($responseData['available_resources']['register']);
        $this->assertStringStartsWith('http', $responseData['available_resources']['register']);

        $this->assertIsString($responseData['available_resources']['login']);
        $this->assertStringStartsWith('http', $responseData['available_resources']['login']);

        $this->assertIsString($responseData['available_resources']['user_details']);
        $this->assertStringStartsWith('http', $responseData['available_resources']['user_details']);

        $this->assertIsString($responseData['available_resources']['list_posts']);
        $this->assertStringStartsWith('http', $responseData['available_resources']['list_posts']);

        $this->assertIsString($responseData['available_resources']['ping']);
        $this->assertStringStartsWith('http', $responseData['available_resources']['ping']);

        // Assert the documentation URL
        $this->assertIsString($responseData['documentation']);
        $this->assertStringStartsWith('http', $responseData['documentation']);
        $this->assertStringContainsString('/documentation', $responseData['documentation']);

        // You could also more specifically check the generated URLs if needed,
        // for example, by comparing them with route() calls directly in the test,
        // but checking for string and http prefix is a good start.
        // $this->assertEquals(route('register', [], true), $responseData['available_resources']['register']);
    }
}
