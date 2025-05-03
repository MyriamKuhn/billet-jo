<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Tests\TestCase;
use Mockery;

class UserLogoutTest extends TestCase
{
    use RefreshDatabase;

    public function testUserCanLogout()
    {
        // Crée un utilisateur
        $user = User::factory()->create();

        // Crée un token d'authentification
        $token = $user->createToken('TestToken')->plainTextToken;

        // Effectue une requête de logout
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/auth/logout');

        // Vérifie que la réponse est correcte
        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Successfully logged out.',
        ]);

        // Vérifie que le token a été supprimé
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }

    public function testLogoutFailsWithoutActiveToken(): void
    {
        // Crée un utilisateur sans token actif
        $user = User::factory()->create();

        // Simule une requête sans authentification de token
        $response = $this->actingAs($user)->postJson('/api/auth/logout');

        // On fait croire que l'utilisateur n'a pas de token actif
        $user->setRelation('currentAccessToken', null);

        $response->assertStatus(400)
            ->assertJson([
                'status' => 'error',
                'error' => __('You are not authenticated.'),
            ]);
    }

    public function testLogoutUnauthorized()
    {
        // Ne simule pas d'utilisateur authentifié
        $this->withoutMiddleware();  // Ignore le middleware d'authentification pour ce test

        // Effectue la requête de déconnexion sans être authentifié
        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(401)
                ->assertJson([
                    'status' => 'error',
                    'error' => __('validation.error_unauthorized'),
                ]);
    }

    public function testLogoutHandlesExceptionGracefully()
    {
        // Création d’un utilisateur
        $user = User::factory()->create();

        // Création d’un mock du token avec méthode delete() qui échoue
        $mockToken = Mockery::mock(\Laravel\Sanctum\PersonalAccessToken::class);
        $mockToken->shouldReceive('delete')->andThrow(new \Exception('Simulated logout failure'));

        // Création d’un faux user avec currentAccessToken() qui renvoie le faux token
        $mockUser = Mockery::mock(User::class)->makePartial();
        $mockUser->shouldReceive('currentAccessToken')->andReturn($mockToken);

        // Authentification avec l’utilisateur mocké
        $this->be($mockUser);

        // Appel de la route de logout
        $response = $this->postJson('/api/auth/logout');

        // Vérification que l’exception a bien été gérée
        $response->assertStatus(500);
        $response->assertJson([
            'status' => 'error',
            'error' => __('validation.error_unknown'),
        ]);
    }
}
