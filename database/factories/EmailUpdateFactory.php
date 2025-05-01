<?php

namespace Database\Factories;

use App\Models\EmailUpdate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmailUpdate>
 */
class EmailUpdateFactory extends Factory
{
    protected $model = EmailUpdate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $oldEmail = $this->faker->safeEmail();
        $newEmail = $this->faker->unique()->safeEmail();

        return [
            'user_id'   => User::factory(),
            'old_email' => $oldEmail,
            'new_email' => $newEmail,
            'token'     => Str::uuid(),
        ];
    }
}

