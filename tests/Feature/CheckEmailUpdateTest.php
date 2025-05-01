<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\EmailUpdate;

class CheckEmailUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function testAdminCanRetrieveEmailUpdateIfExists()
    {
        $admin = User::factory()->create(['role' => "admin"]);
        $user = User::factory()->create();
        $emailUpdate = EmailUpdate::factory()->create([
            'user_id' => $user->id,
            'old_email' => 'old@example.com',
            'new_email' => 'new@example.com',
        ]);

        $response = $this->actingAs($admin)->getJson("/api/user/{$user->id}/email-update");

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'old_email' => 'old@example.com',
                    'new_email' => 'new@example.com',
                ],
                'message' => __('user.email_update_found'),
            ]);
    }

    public function testAdminReceivesNullWhenNoEmailUpdateExists()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->getJson("/api/user/{$user->id}/email-update");

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => null,
                'message' => __('user.no_email_update'),
            ]);
    }

    public function testNonAdminUserCannotCheckEmailUpdate()
    {
        $user = User::factory()->create(['role' => 'user']); // utilisateur standard
        $otherUser = User::factory()->create();

        $response = $this->actingAs($user)->getJson("/api/user/{$otherUser->id}/email-update");

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'error' => __('user.error_user_unauthorized'),
            ]);
    }

    public function testUnauthenticatedUserCannotCheckEmailUpdate()
    {
        $user = User::factory()->create();

        $response = $this->getJson("/api/user/{$user->id}/email-update");

        $response->assertStatus(401);
    }
}
