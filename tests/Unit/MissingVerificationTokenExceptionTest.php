<?php

namespace Tests\Unit\Exceptions\Auth;

use Tests\TestCase;
use App\Exceptions\Auth\MissingVerificationTokenException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class MissingVerificationTokenExceptionTest extends TestCase
{
    public function testExceptionIsInstanceOfBadRequestHttpException()
    {
        $exception = new MissingVerificationTokenException();

        $this->assertInstanceOf(
            BadRequestHttpException::class,
            $exception
        );
    }

    public function testDefaultMessageIsEmpty()
    {
        $exception = new MissingVerificationTokenException();

        // Par défaut, le message est vide (géré en amont)
        $this->assertEmpty(
            $exception->getMessage(),
            'Expected default exception message to be empty'
        );
    }

    public function testGetErrorCodeReturnsVerificationTokenMissing()
    {
        $exception = new MissingVerificationTokenException();

        $this->assertEquals(
            'verification_token_missing',
            $exception->getErrorCode()
        );
    }

    public function testGetStatusCodeIs400()
    {
        $exception = new MissingVerificationTokenException();

        $this->assertEquals(
            400,
            $exception->getStatusCode(),
            'Expected HTTP status code 400 for BadRequestHttpException'
        );
    }
}
