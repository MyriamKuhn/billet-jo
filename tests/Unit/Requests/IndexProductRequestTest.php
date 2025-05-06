<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\IndexProductRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class IndexProductRequestTest extends TestCase
{
    use RefreshDatabase;

    public function testAuthorizeAlwaysAllows(): void
    {
        $request = IndexProductRequest::create('/api/products', 'GET');
        // always true, so no exception should be thrown
        $this->assertTrue($request->authorize());
    }

    public function testPrepareForValidationThrowsBadRequestOnExtraParams(): void
    {
        $params = ['foobar' => 'value'];
        $request = IndexProductRequest::create('/api/products?foobar=value', 'GET', $params);

        $this->expectException(HttpException::class);
        // abort(400) throws HttpException with status code 400
        try {
            $request->validateResolved();
        } catch (HttpException $e) {
            $this->assertEquals(400, $e->getStatusCode());
            throw $e;
        }
    }
}

