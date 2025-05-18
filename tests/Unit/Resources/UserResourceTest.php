<?php

namespace Tests\Unit\Resources;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;
use App\Models\User;
use App\Enums\UserRole;
use App\Http\Resources\UserResource;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    public function testToArrayIncludesAllFieldsWithEmailVerified(): void
    {
        // Prepare dates
        $createdAt = Carbon::create(2025, 5, 10, 12, 0, 0, 'UTC');
        $updatedAt = Carbon::create(2025, 5, 11, 15, 30, 0, 'UTC');
        $verifiedAt = Carbon::create(2025, 5, 12, 9, 45, 0, 'UTC');

        // Create user
        $user = User::factory()->create([
            'firstname'         => 'John',
            'lastname'          => 'Doe',
            'email'             => 'john.doe@example.com',
            'role'              => UserRole::Admin,
            'twofa_enabled'     => true,
            'is_active'         => false,
            'email_verified_at' => $verifiedAt,
            'created_at'        => $createdAt,
            'updated_at'        => $updatedAt,
        ]);

        $resource = new UserResource($user);
        $array = $resource->toArray(request());

        $this->assertSame($user->id, $array['id']);
        $this->assertSame('John', $array['firstname']);
        $this->assertSame('Doe', $array['lastname']);
        $this->assertSame('john.doe@example.com', $array['email']);
        $this->assertSame(UserRole::Admin->value, $array['role']);
        $this->assertTrue($array['twofa_enabled']);
        $this->assertFalse($array['is_active']);

        // Date fields should be Carbon instances equal to originals
        $this->assertInstanceOf(Carbon::class, $array['email_verified_at']);
        $this->assertTrue($array['email_verified_at']->equalTo($verifiedAt));

        $this->assertInstanceOf(Carbon::class, $array['created_at']);
        $this->assertTrue($array['created_at']->equalTo($createdAt));

        $this->assertInstanceOf(Carbon::class, $array['updated_at']);
        $this->assertTrue($array['updated_at']->equalTo($updatedAt));
    }

    public function testToArrayHandlesNullEmailVerified(): void
    {
        $createdAt = Carbon::create(2025, 6, 1, 8, 0, 0, 'UTC');
        $updatedAt = Carbon::create(2025, 6, 2, 10, 15, 0, 'UTC');

        $user = User::factory()->create([
            'email_verified_at' => null,
            'created_at'        => $createdAt,
            'updated_at'        => $updatedAt,
        ]);

        $resource = new UserResource($user);
        $array = $resource->toArray(request());

        $this->assertNull($array['email_verified_at']);

        // Dates should be Carbon instances equal to originals
        $this->assertInstanceOf(Carbon::class, $array['created_at']);
        $this->assertTrue($array['created_at']->equalTo($createdAt));

        $this->assertInstanceOf(Carbon::class, $array['updated_at']);
        $this->assertTrue($array['updated_at']->equalTo($updatedAt));
    }
}

