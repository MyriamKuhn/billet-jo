<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Exceptions\Auth\InvalidVerificationLinkException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class InvalidVerificationLinkExceptionTest extends TestCase
{
    public function testExceptionIsInstanceOfBadRequestHttpException()
    {
        $exception = new InvalidVerificationLinkException();

        $this->assertInstanceOf(
            BadRequestHttpException::class,
            $exception
        );
    }

    public function testDefaultMessageIsEmpty()
    {
        $exception = new InvalidVerificationLinkException();

        // Par défaut, le message est vide (géré en amont)
        $this->assertEmpty(
            $exception->getMessage(),
            'Expected default exception message to be empty'
        );
    }

    public function testGetErrorCodeReturnsInvalidVerificationLink()
    {
        $exception = new InvalidVerificationLinkException();

        $this->assertEquals(
            'invalid_verification_link',
            $exception->getErrorCode()
        );
    }

    public function testGetStatusCodeIs400()
    {
        $exception = new InvalidVerificationLinkException();

        $this->assertEquals(
            400,
            $exception->getStatusCode(),
            'Expected HTTP status code 400 for BadRequestHttpException'
        );
    }
}
