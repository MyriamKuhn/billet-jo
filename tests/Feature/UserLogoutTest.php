<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Tests\TestCase;

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
                'message' => __('validation.no_active_token'),
            ]);
    }
}
