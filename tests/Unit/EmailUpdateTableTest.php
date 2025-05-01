<?php

namespace Tests\Unit;

use App\Models\EmailUpdate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmailUpdateTableTest extends TestCase
{
    use RefreshDatabase;

    public function testItCanCreateAnEmailIpdateRecord()
    {
        $user = User::factory()->create();

        $emailUpdate = EmailUpdate::create([
            'user_id'   => $user->id,
            'old_email' => $user->email,
            'new_email' => 'new@example.com',
            'token'     => Str::uuid(),
        ]);

        $this->assertDatabaseHas('email_updates', [
            'user_id'   => $user->id,
            'old_email' => $user->email,
            'new_email' => 'new@example.com',
        ]);
    }

    public function testItBelongsToAUser()
    {
        $emailUpdate = EmailUpdate::factory()->create();

        $this->assertInstanceOf(User::class, $emailUpdate->user);
    }

    public function testUserHasOneEmailUpdate()
    {
        $user = User::factory()->create();
        $emailUpdate = EmailUpdate::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($user->emailUpdate->is($emailUpdate));
    }
}
