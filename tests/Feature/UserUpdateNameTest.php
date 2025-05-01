<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;

class UserUpdateNameTest extends TestCase
{
    use RefreshDatabase;

    public function testItUpdatesUserFirstnameAndLastnameSuccessfully()
    {
        $user = User::factory()->create([
            'firstname' => 'Old',
            'lastname' => 'Name',
        ]);

        $payload = [
            'firstname' => 'New',
            'lastname' => 'Name',
        ];

        $response = $this->actingAs($user)
                        ->putJson('/api/user/update', $payload);

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'data' => [
                        'user' => [
                            'firstname' => 'New',
                            'lastname' => 'Name',
                        ],
                    ],
                ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'firstname' => 'New',
            'lastname' => 'Name',
        ]);
    }

    public function testItReturnsValidationErrorsIfFirstnameOrLastnameAreMissing()
    {
        // Given
        $user = User::factory()->create();

        // When
        $response = $this->actingAs($user)
                        ->putJson('/api/user/update', []);

        // Then
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['firstname', 'lastname']);
    }

    public function testItReturnsUnauthorizedIfNotAuthenticated()
    {
        // When
        $response = $this->putJson('/api/user/update', [
            'firstname' => 'Someone',
            'lastname' => 'Else',
        ]);

        // Then
        $response->assertStatus(401)
                ->assertJson([
                    'message' => 'Unauthenticated.',
                ]);
    }

    public function testItReturnsCustomUnauthorizedResponseIfNoUserFound()
    {
        // Given: le middleware auth est désactivé
        $this->withoutMiddleware();

        // Et la route existe pour ce test (utile si route chargée via middleware)
        Route::put('/api/user/update', [UserController::class, 'updateName']);

        // When: appel sans utilisateur authentifié
        $response = $this->putJson('/api/user/update', [
            'firstname' => 'Ghost',
            'lastname' => 'User',
        ]);

        // Then: la réponse personnalisée de ton contrôleur est bien retournée
        $response->assertStatus(401)
                ->assertJson([
                    'status' => 'error',
                    'errors' => __('validation.error_unauthorized'),
                ]);
    }

    public function testItReturns500IfUpdateThrowsException()
    {
        // Given
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('update')->once()->andThrow(new \Exception('DB error'));

        // On force l'utilisateur authentifié à être ce mock
        $this->actingAs($user);

        // When
        $response = $this->putJson('/api/user/update', [
            'firstname' => 'Crash',
            'lastname' => 'Test',
        ]);

        // Then
        $response->assertStatus(500)
                ->assertJson([
                    'status' => 'error',
                    'error' => __('validation.error_unknown'),
                ]);
    }
}
