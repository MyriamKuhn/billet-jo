<?php

namespace Tests\Unit\Requests;

use Tests\TestCase;
use App\Http\Requests\SalesStatsRequest;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SalesStatsRequestTest extends TestCase
{
    public function testAuthorizeReturnsTrueForAdminAndFalseForNonAdmin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $user  = User::factory()->create(['role' => UserRole::User]);

        // Simulate authenticated admin
        $this->be($admin);
        $request = new SalesStatsRequest();
        $this->assertTrue($request->authorize());

        // Simulate authenticated normal user
        $this->be($user);
        $request = new SalesStatsRequest();
        $this->assertFalse($request->authorize());
    }

    public function testRulesAcceptValidAndRejectInvalidInputs(): void
    {
        $request = new SalesStatsRequest();

        $validData = [
            'q'          => 'search',
            'sort_by'    => 'sales_count',
            'sort_order' => 'asc',
            'per_page'   => 50,
            'page'       => 2,
        ];
        $validator = Validator::make($validData, $request->rules());
        $this->assertFalse($validator->fails());

        $invalidData = [
            'q'          => ['not','string'],
            'sort_by'    => 'invalid_field',
            'sort_order' => 'upwards',
            'per_page'   => 0,
            'page'       => 0,
        ];
        $validator = Validator::make($invalidData, $request->rules());
        $this->assertTrue($validator->fails());
        $errors = $validator->errors()->keys();
        $this->assertContains('q', $errors);
        $this->assertContains('sort_by', $errors);
        $this->assertContains('sort_order', $errors);
        $this->assertContains('per_page', $errors);
        $this->assertContains('page', $errors);
    }

    public function testValidatedFiltersReturnsOnlyExpectedKeys(): void
    {
        $stub = new class extends SalesStatsRequest {
            /**
             * Override with matching signature
             *
             * @param  string|null  $key
             * @param  mixed        $default
             * @return array<string, mixed>
             */
            public function validated($key = null, $default = null): array
            {
                return [
                    'q'          => 'abc',
                    'sort_by'    => 'product_name',
                    'sort_order' => 'desc',
                    'per_page'   => 10,
                    'page'       => 3,
                    'extra'      => 'should_be_removed',
                ];
            }
        };

        $filters = $stub->validatedFilters();

        $this->assertSame(
            [
                'q'          => 'abc',
                'sort_by'    => 'product_name',
                'sort_order' => 'desc',
                'per_page'   => 10,
                'page'       => 3,
            ],
            $filters
        );
    }
}
