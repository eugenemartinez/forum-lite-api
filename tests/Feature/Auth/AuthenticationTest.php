<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /*
    #[Test]
    public function test_example(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
    }
    */

    #[Test]
    public function user_can_register_with_valid_data(): void
    {
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson(route('register'), $userData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'created_at',
                    'updated_at',
                ],
                'access_token',
                'token_type',
            ])
            ->assertJson([
                'message' => 'User registered successfully',
                'user' => [
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                ],
                'token_type' => 'Bearer',
            ]);

        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $user = User::where('email', 'test@example.com')->first();
        $this->assertTrue(Hash::check('password123', $user->password));
        $this->assertNotNull($response->json('access_token'));
    }

    #[Test]
    public function user_cannot_register_with_missing_required_fields(): void
    {
        $response = $this->postJson(route('register'), []);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);

        $response = $this->postJson(route('register'), [
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name'])
            ->assertJsonMissingValidationErrors(['email', 'password']);

        $response = $this->postJson(route('register'), [
            'name' => 'Test User',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email'])
            ->assertJsonMissingValidationErrors(['name', 'password']);

        $response = $this->postJson(route('register'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password'])
            ->assertJsonMissingValidationErrors(['name', 'email']);

        $response = $this->postJson(route('register'), []);
        $response->assertStatus(422)
                 ->assertJsonPath('message', 'Validation errors');
    }

    #[Test]
    public function user_cannot_register_with_invalid_email_format(): void
    {
        $userData = [
            'name' => 'Test User',
            'email' => 'not-an-email',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson(route('register'), $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email'])
            ->assertJsonPath('message', 'Validation errors');
    }

    #[Test]
    public function user_cannot_register_with_password_too_short(): void
    {
        $shortPassword = 'pass';
        $userData = [
            'name' => 'Test User',
            'email' => 'testpassword@example.com',
            'password' => $shortPassword,
            'password_confirmation' => $shortPassword,
        ];

        $response = $this->postJson(route('register'), $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password'])
            ->assertJsonPath('message', 'Validation errors');

        $this->assertDatabaseMissing('users', [
            'email' => 'testpassword@example.com',
        ]);
    }

    #[Test]
    public function user_cannot_register_with_duplicate_email(): void
    {
        $existingUser = User::factory()->create([
            'email' => 'duplicate@example.com',
        ]);

        $newUserData = [
            'name' => 'Another User',
            'email' => 'duplicate@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson(route('register'), $newUserData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email'])
            ->assertJsonPath('message', 'Validation errors');

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'email' => $existingUser->email,
            'name' => $existingUser->name,
        ]);
        $this->assertDatabaseMissing('users', [
            'name' => 'Another User',
        ]);
    }

    #[Test]
    public function registration_is_rate_limited(): void
    {
        $maxAttempts = 10;
        $testEmail = 'fixedratelimit@example.com';

        $baseUserData = [
            'name' => 'Rate Limit Test User',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        for ($i = 1; $i <= $maxAttempts + 1; $i++) {
            $userData = array_merge($baseUserData, ['email' => $testEmail, 'name' => $baseUserData['name'] . ' ' . $i]);
            $response = $this->postJson(route('register'), $userData);

            if ($i === 1) {
                $response->assertStatus(201);
                $this->assertDatabaseHas('users', ['email' => $testEmail]);
            } elseif ($i <= $maxAttempts) {
                $response->assertStatus(422);
                $response->assertJsonValidationErrors(['email']);
            } else {
                $response->assertStatus(429);
                $response->assertJson(['message' => 'Too Many Attempts.']);

                $this->assertNotNull($response->headers->get('Retry-After'));
                $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));
                $this->assertEquals($maxAttempts, $response->headers->get('X-RateLimit-Limit'));
                $this->assertEquals(0, $response->headers->get('X-RateLimit-Remaining'));
            }
        }
    }

    #[Test]
    public function registration_is_limited_by_user_table_capacity(): void
    {
        $testSpecificUserLimit = 2;

        Config::set('app_limits.max_users', $testSpecificUserLimit);

        for ($i = 1; $i <= $testSpecificUserLimit; $i++) {
            User::factory()->create([
                'email' => "user{$i}_capacity@example.com",
            ]);
        }

        $this->assertDatabaseCount('users', $testSpecificUserLimit);

        $newUserData = [
            'name' => 'Over The Limit User',
            'email' => 'overlimit_capacity@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson(route('register'), $newUserData);

        $response->assertStatus(503);
        $response->assertJson([
            'message' => 'The maximum number of allowed records has been reached. Cannot create new entries at this time.',
        ]);

        $this->assertDatabaseCount('users', $testSpecificUserLimit);
        $this->assertDatabaseMissing('users', [
            'email' => 'overlimit_capacity@example.com',
        ]);
    }

    #[Test]
    public function user_can_login_with_valid_credentials(): void
    {
        $password = 'password123';
        $user = User::factory()->create([
            'password' => Hash::make($password),
        ]);

        $loginData = [
            'email' => $user->email,
            'password' => $password,
        ];

        $response = $this->postJson(route('login'), $loginData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'created_at',
                    'updated_at',
                ],
                'access_token',
                'token_type',
            ])
            ->assertJson([
                'message' => 'User logged in successfully',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'token_type' => 'Bearer',
            ]);

        $this->assertNotNull($response->json('access_token'));
    }

    #[Test]
    public function user_cannot_login_with_invalid_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correct-password'),
        ]);

        $loginData = [
            'email' => $user->email,
            'password' => 'wrong-password',
        ];

        $response = $this->postJson(route('login'), $loginData);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Invalid login details',
            ]);

        $response->assertJsonMissingPath('access_token');
        $response->assertJsonMissingPath('user.id');
    }

    #[Test]
    public function user_cannot_login_with_non_existent_email(): void
    {
        $loginData = [
            'email' => 'nonexistent@example.com',
            'password' => 'any-password',
        ];

        $response = $this->postJson(route('login'), $loginData);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Invalid login details',
            ]);

        $response->assertJsonMissingPath('access_token');
        $response->assertJsonMissingPath('user.id');
    }

    #[Test]
    public function user_cannot_login_with_missing_email_or_password(): void
    {
        $response = $this->postJson(route('login'), [
            'password' => 'some-password',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email'])
            ->assertJsonMissingValidationErrors(['password'])
            ->assertJsonPath('message', 'Validation errors');

        $response = $this->postJson(route('login'), [
            'email' => 'test@example.com',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password'])
            ->assertJsonMissingValidationErrors(['email'])
            ->assertJsonPath('message', 'Validation errors');

        $response = $this->postJson(route('login'), []);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password'])
            ->assertJsonPath('message', 'Validation errors');
    }

    #[Test]
    public function login_is_rate_limited(): void
    {
        $maxAttempts = 10;
        $testEmail = 'ratelimitlogin@example.com';

        $loginData = [
            'email' => $testEmail,
            'password' => 'some-password',
        ];

        for ($i = 1; $i <= $maxAttempts + 1; $i++) {
            $response = $this->postJson(route('login'), $loginData);

            if ($i <= $maxAttempts) {
                $response->assertStatus(401);
                $response->assertJson(['message' => 'Invalid login details']);
            } else {
                $response->assertStatus(429);
                $response->assertJson(['message' => 'Too Many Attempts.']);

                $this->assertNotNull($response->headers->get('Retry-After'));
                $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));
                $this->assertEquals($maxAttempts, $response->headers->get('X-RateLimit-Limit'));
                $this->assertEquals(0, $response->headers->get('X-RateLimit-Remaining'));
            }
        }
    }

    #[Test]
    public function authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();
        $newAccessToken = $user->createToken('test-token');
        $tokenModel = $newAccessToken->accessToken;
        $plainTextToken = $newAccessToken->plainTextToken;

        $logoutResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $plainTextToken,
        ])->postJson(route('logout'));

        $logoutResponse->assertStatus(200)
            ->assertJson([
                'message' => 'Logged out successfully',
            ]);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenModel->id,
        ]);

        $this->app['auth']->forgetGuards();

        $this->withHeaders([
            'Authorization' => 'Bearer ' . $plainTextToken,
        ])->getJson(route('user.show'))
            ->assertStatus(401);
    }

    #[Test]
    public function unauthenticated_user_cannot_logout(): void
    {
        $response = $this->postJson(route('logout'));

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Unauthenticated.']);
    }

    #[Test]
    public function authenticated_user_can_fetch_their_details(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson(route('user.show'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                    'created_at',
                    'updated_at',
                ]
            ])
            ->assertJson([
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ]
            ]);
    }

    #[Test]
    public function unauthenticated_user_cannot_fetch_user_details(): void
    {
        $response = $this->getJson(route('user.show'));

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Unauthenticated.']);
    }
}
