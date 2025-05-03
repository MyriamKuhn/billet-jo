<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Enums\UserRole;
use App\Http\Controllers\UserController;
use Illuminate\Database\Eloquent\Builder;
use Mockery;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

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

    public function testIndexThrowsExceptionAndReturns500()
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin->value,
        ]);

        // Renommer temporairement la table users pour provoquer une exception
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('RENAME TABLE users TO users_backup');

        try {
            $this->actingAs($admin)
                ->getJson(route('user.all'))
                ->assertStatus(500)
                ->assertJson([
                    'status' => 'error',
                    'error' => __('validation.error_unknown'),
                ]);
        } finally {
            // Restaurer la table pour ne pas casser les autres tests
            DB::statement('RENAME TABLE users_backup TO users');
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}
