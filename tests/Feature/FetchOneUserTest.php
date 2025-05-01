<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class FetchOneUserTest extends TestCase
{
    use RefreshDatabase;

    public function testShowSuccess()
    {
        $authUser = User::factory()->create([
            'role' => 'employee',
        ]);

        $user = User::factory()->create(); // Créer un autre utilisateur pour afficher

        // Se connecter en tant qu'utilisateur authentifié
        $this->actingAs($authUser);

        $response = $this->getJson(route('user.show', $user->id));

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'user' => [
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'email' => $user->email,
            ],
        ]);
    }

    public function testShowUnauthorized()
    {
        $authUser = User::factory()->create([
            'role' => "user", // Supposons que 2 est un rôle non autorisé
        ]);

        $user = User::factory()->create(); // Créer un autre utilisateur pour afficher

        // Se connecter en tant qu'utilisateur non autorisé
        $this->actingAs($authUser);

        $response = $this->getJson(route('user.show', $user->id));

        $response->assertStatus(403);
        $response->assertJson([
            'status' => 'error',
            'error' => __('user.error_user_unauthorized'),
        ]);
    }

    public function testShowUserNotFound()
    {
        $authUser = User::factory()->create([
            'role' => "admin",
        ]);

        // Se connecter en tant qu'utilisateur authentifié
        $this->actingAs($authUser);

        // Essayer d'afficher un utilisateur qui n'existe pas
        $response = $this->getJson(route('user.show', 999));

        $response->assertStatus(404);
    }
}
