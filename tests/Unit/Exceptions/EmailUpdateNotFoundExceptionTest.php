<?php

namespace Tests\Unit\Exceptions;

use Tests\TestCase;
use App\Exceptions\Auth\EmailUpdateNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EmailUpdateNotFoundExceptionTest extends TestCase
{
    public function testExceptionIsInstanceOfNotFoundHttpException()
    {
        $exception = new EmailUpdateNotFoundException();

        $this->assertInstanceOf(
            NotFoundHttpException::class,
            $exception
        );
    }

    public function testDefaultMessageIsEmpty()
    {
        $exception = new EmailUpdateNotFoundException();

        // Par défaut, le message est vide (géré en amont)
        $this->assertEmpty(
            $exception->getMessage(),
            'Expected default exception message to be empty'
        );
    }

    public function testGetErrorCodeReturnsEmailUpdateNotFound()
    {
        $exception = new EmailUpdateNotFoundException();

        $this->assertEquals(
            'email_update_not_found',
            $exception->getErrorCode()
        );
    }

    public function testGetStatusCodeIs404()
    {
        $exception = new EmailUpdateNotFoundException();

        $this->assertEquals(
            404,
            $exception->getStatusCode(),
            'Expected HTTP status code 404 for NotFoundHttpException'
        );
    }
}
