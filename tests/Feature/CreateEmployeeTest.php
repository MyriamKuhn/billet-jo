<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;

class CreateEmployeeTest extends TestCase
{
    use RefreshDatabase;

    public function testAdminCanCreateEmployee()
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $response = $this->postJson('/api/user/create', [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => 'Str0ng!Password2024',
            'password_confirmation' => 'Str0ng!Password2024',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'user' => [
                        'firstname' => 'John',
                        'lastname' => 'Doe',
                        'email' => 'john.doe@example.com',
                        'role' => 'employee',
                    ]
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john.doe@example.com',
            'role' => 'employee',
        ]);
    }

    public function testNonAdminCannotCreateEmployee()
    {
        $user = User::factory()->create(['role' => 'user']);
        $this->actingAs($user);

        $response = $this->postJson('/api/user/create', [
            'firstname' => 'Jane',
            'lastname' => 'Smith',
            'email' => 'jane.smith@example.com',
            'password' => 'Str0ng!Password2024',
            'password_confirmation' => 'Str0ng!Password2024',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
            ]);

        $this->assertDatabaseMissing('users', [
            'email' => 'jane.smith@example.com',
        ]);
    }

    public function testUnauthenticatedUserCannotCreateEmployee()
    {
        $response = $this->postJson('/api/user/create', [
            'firstname' => 'Unauth',
            'lastname' => 'User',
            'email' => 'unauth@example.com',
            'password' => 'Str0ng!Password2024',
            'password_confirmation' => 'Str0ng!Password2024',
        ]);

        $response->assertStatus(401);
    }

    public function testValidationErrorWhenDataIsInvalid()
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $response = $this->postJson('/api/user/create', [
            'firstname' => '',
            'lastname' => '',
            'email' => 'invalid-email',
            'password' => 'short',
            'password_confirmation' => 'different',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['firstname', 'lastname', 'email', 'password']);
    }

    public function testEmailMustBeUnique()
    {
        $admin = User::factory()->admin()->create();
        $existingUser = User::factory()->create(['email' => 'duplicate@example.com']);
        $this->actingAs($admin);

        $response = $this->postJson('/api/user/create', [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'duplicate@example.com',
            'password' => 'Str0ng!Password2024',
            'password_confirmation' => 'Str0ng!Password2024',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
