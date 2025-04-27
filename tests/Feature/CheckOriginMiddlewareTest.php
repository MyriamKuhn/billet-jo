<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Tests\TestCase;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Middleware\CheckOriginMiddleware;

class CheckOriginMiddlewareTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function testAllowsRequestFromAllowedOrigin(): void
    {
        $middleware = new CheckOriginMiddleware();

        $request = Request::create('/api/auth/register', 'POST', [], [], [], [
            'HTTP_ORIGIN' => 'http://localhost:3000',
        ]);

        $response = $middleware->handle($request, function () {
            return response('OK', 200);
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    public function testDeniesRequestFromDisallowedOrigin(): void
    {
        $middleware = new CheckOriginMiddleware();

        $request = Request::create('/api/auth/register', 'POST', [], [], [], [
            'HTTP_ORIGIN' => 'https://random-site.com',
        ]);

        $response = $middleware->handle($request, function () {
            return response('OK', 200);
        });

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertJson($response->getContent());
        $this->assertStringContainsString('Unauthorized origin', $response->getContent());
    }
}
