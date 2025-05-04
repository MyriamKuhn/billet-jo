<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;

class GetUsersInformationsTest extends TestCase
{
    use RefreshDatabase;

    public function testAuthenticatedUserCanAccessProfile()
    {
        $user = User::factory()->create(['twofa_enabled' => true]);
        $this->actingAs($user);

        $response = $this->getJson('/api/user/info');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'user' => [
                    'firstname' => $user->firstname,
                    'lastname' => $user->lastname,
                    'email' => $user->email,
                    'twofa_enabled' => true, // Vérification que 2FA est bien activé
                ],
            ]);
    }

    public function testGuestUserCannotAccessProfile()
    {
        $response = $this->getJson('/api/user/info');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Authentication required',
                'code' => 'unauthenticated',
            ]);
    }

    public function testServerErrorReturns500OnProfile()
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        // Mock de l'auth() pour générer une exception
        $this->mock(\Illuminate\Contracts\Auth\Factory::class, function ($mock) {
            $mock->shouldReceive('guard')->andThrow(new \Exception('Unexpected error'));
        });

        $response = $this->getJson('/api/user/info');

        $response->assertStatus(500);
    }

    public function testAuthenticatedUserWith2faDisabledCanAccessProfile()
    {
        $user = User::factory()->create([
            'twofa_enabled' => false,
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/api/user/info');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'user' => [
                    'firstname' => $user->firstname,
                    'lastname' => $user->lastname,
                    'email' => $user->email,
                    'twofa_enabled' => false,
                ],
            ]);
    }

}
