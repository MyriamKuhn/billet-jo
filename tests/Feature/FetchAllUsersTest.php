<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Enums\UserRole;
use App\Http\Controllers\UserController;

class FetchAllUsersTest extends TestCase
{
    use RefreshDatabase;

    public function testItReturns401IfNotAuthenticated()
    {
        // When
        $response = $this->getJson('/api/user/all');

        // Then
        $response->assertStatus(401)
                ->assertJson([
                    'message' => 'Unauthenticated.',
                ]);
    }

    public function testItReturns403IfNotAdmin()
    {
        // Crée un utilisateur non admin
        $user = User::factory()->create([
            'role' => 'user', // ou un autre rôle non admin
        ]);

        // Authentifie l'utilisateur
        $this->actingAs($user);

        // When
        $response = $this->getJson('/api/user/all');

        // Then
        $response->assertStatus(403)
                ->assertJson([
                    'status' => 'error',
                    'error' => __('user.error_user_unauthorized'),
                ]);
    }

    public function testItReturnsUsersListIfAuthenticatedAndAdmin()
    {
        // Create an admin user
        $adminUser = User::factory()->create([
            'role' => UserRole::Admin->value,
        ]);

        // Create some other users
        $user1 = User::factory()->create([
            'role' => UserRole::User->value,
        ]);
        $user2 = User::factory()->create([
            'role' => UserRole::Employee->value,
        ]);

        // Act as the admin user
        $response = $this->actingAs($adminUser)->getJson('/api/user/all');

        // Then
        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                ])
                 ->assertJsonCount(3, 'data.users');  // Vérifie qu'il y a bien 3 utilisateurs

        // Check if the users' information is in the response
        $response->assertJsonFragment([
            'id' => $user1->id,
            'firstname' => $user1->firstname,
            'lastname' => $user1->lastname,
            'email' => $user1->email,
        ]);
    }
}
