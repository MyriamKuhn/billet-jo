<?php

namespace Tests\Unit;

use App\Http\Requests\AdminProductRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;
use Mockery;

class AdminProductRequestTest extends TestCase
{
    use RefreshDatabase;

    public function testPrepareForValidationThrowsBadRequestOnExtraParams(): void
    {
        $request = AdminProductRequest::create(
            '/api/products/all?foobar=1',
            'GET',
            ['foobar' => '1']
        );
        $user = User::factory()->create(['role' => 'admin']);
        $roleMock = Mockery::mock();
        $roleMock->shouldReceive('isAdmin')->andReturnTrue();
        $user->setRelation('role', $roleMock);
        $request->setUserResolver(fn() => $user);

        $this->expectException(HttpException::class);
        // HttpException thrown by abort(400) has status code accessible via getStatusCode()
        try {
            $request->validateResolved();
        } catch (HttpException $e) {
            $this->assertEquals(400, $e->getStatusCode());
            throw $e;
        }
    }
}

