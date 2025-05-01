<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;

class UpdateUserByAdminTest extends TestCase
{
    use RefreshDatabase;

    public function testAdminCanUpdateUserSuccessfully()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);

        $payload = [
            'firstname' => 'Updated',
            'lastname' => 'User',
            'email' => 'newemail@example.com',
            'is_active' => false,
            'twofa_enabled' => false,
            'role' => 'employee',
        ];

        $this->actingAs($admin)
            ->patchJson("/api/user/{$user->id}", $payload)
            ->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'user' => [
                        'firstname' => 'Updated',
                        'lastname' => 'User',
                        'email' => 'newemail@example.com',
                        'is_active' => false,
                        'twofa_enabled' => false,
                        'role' => 'employee',
                    ]
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'firstname' => 'Updated',
            'lastname' => 'User',
            'email' => 'newemail@example.com',
            'is_active' => false,
            'twofa_enabled' => false,
            'twofa_secret' => null,
            'role' => 'employee',
        ]);
    }

    public function testNonAdminUserCannotUpdateAnyUser()
    {
        $nonAdmin = User::factory()->create(['role' => 'user']);
        $user = User::factory()->create();

        $this->actingAs($nonAdmin)
            ->patchJson("/api/user/{$user->id}", ['firstname' => 'Hacker'])
            ->assertStatus(403)
            ->assertJson([
                'status' => 'error',
            ]);
    }

    public function testUpdateUserFailsWithInvalidData()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();

        $payload = [
            'email' => 'invalid-email',
            'is_active' => 'not-boolean',
            'role' => 'superadmin', // not allowed
        ];

        $this->actingAs($admin)
            ->patchJson("/api/user/{$user->id}", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'is_active', 'role']);
    }

    public function testUserCanKeepSameEmailWithoutValidationError()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['email' => 'user@example.com']);

        $this->actingAs($admin)
            ->patchJson("/api/user/{$user->id}", ['email' => 'user@example.com'])
            ->assertStatus(200);
    }

    public function testCannotUseExistingEmailOfAnotherUser()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user1 = User::factory()->create(['email' => 'first@example.com']);
        $user2 = User::factory()->create(['email' => 'second@example.com']);

        $this->actingAs($admin)
            ->patchJson("/api/user/{$user2->id}", ['email' => 'first@example.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function testUnauthenticatedUserCannotUpdateAnyUser()
    {
        $user = User::factory()->create();

        $this->patchJson("/api/user/{$user->id}", ['firstname' => 'Nope'])
            ->assertStatus(401);
    }
}
