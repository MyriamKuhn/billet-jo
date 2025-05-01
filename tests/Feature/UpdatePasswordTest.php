<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Http\Controllers\AuthController;

class UpdatePasswordTest extends TestCase
{
    use RefreshDatabase;

    public function testUserCanChangePassword()
    {
        // Crée un utilisateur de test
        $user = User::factory()->create([
            'password_hash' => Hash::make('ancien_mdp_123'), // mot de passe de test
        ]);

        // Authentifie l'utilisateur
        $this->actingAs($user);

        // Envoie la requête pour changer le mot de passe
        $response = $this->putJson('/api/auth/update-password', [
            'current_password' => 'ancien_mdp_123',
            'password' => 'Nouveau_mdp_123!',
            'password_confirmation' => 'Nouveau_mdp_123!',
        ]);

        // Vérifie la réponse
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'message' => 'Your password has been successfully changed.'
        ]);
    }

    public function testUserChangePasswordWithNotAllowedPassword()
    {
        // Crée un utilisateur de test
        $user = User::factory()->create([
            'password_hash' => Hash::make('ancien_mdp_123'), // mot de passe de test
        ]);

        // Authentifie l'utilisateur
        $this->actingAs($user);

        // Envoie la requête pour changer le mot de passe
        $response = $this->putJson('/api/auth/update-password', [
            'current_password' => 'ancien_mdp_123',
            'password' => 'nouveau_mdp_123!',
            'password_confirmation' => 'nouveau_mdp_123!',
        ]);

        // Vérifie la réponse
        $response->assertStatus(422);
        $response->assertJson([
            'status' => 'error',
        ]);
    }

    public function testUserChangePasswordWithInvalidCurrentPassword()
    {
        // Crée un utilisateur de test
        $user = User::factory()->create([
            'password_hash' => Hash::make('ancien_mdp_123'), // mot de passe de test
        ]);

        // Authentifie l'utilisateur
        $this->actingAs($user);

        // Envoie la requête pour changer le mot de passe
        $response = $this->putJson('/api/auth/update-password', [
            'current_password' => 'ancien_mdp_456',
            'password' => 'Nouveau_mdp_123!',
            'password_confirmation' => 'Nouveau_mdp_123!',
        ]);

        // Vérifie la réponse
        $response->assertStatus(400);
        $response->assertJson([
            'status' => 'error',
            'error' => 'The current password is incorrect.'
        ]);
    }

    public function testUserChangePasswordWithInvalidConfirmation()
    {
        // Crée un utilisateur de test
        $user = User::factory()->create([
            'password_hash' => Hash::make('ancien_mdp_123'), // mot de passe de test
        ]);

        // Authentifie l'utilisateur
        $this->actingAs($user);

        // Envoie la requête pour changer le mot de passe
        $response = $this->putJson('/api/auth/update-password', [
            'current_password' => 'ancien_mdp_456',
            'password' => 'Nouveau_mdp_123!',
            'password_confirmation' => 'Modifie_mdp_123!',
        ]);

        // Vérifie la réponse
        $response->assertStatus(422);
        $response->assertJsonStructure([
            'status',
            'errors'
        ]);
    }
}
