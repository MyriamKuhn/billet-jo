<?php

namespace Tests\Unit\Exceptions;

use Tests\TestCase;
use App\Exceptions\Auth\AlreadyVerifiedException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AlreadyVerifiedExceptionTest extends TestCase
{
    public function testExceptionIsInstanceOfConflictHttpException()
    {
        $exception = new AlreadyVerifiedException();

        $this->assertInstanceOf(
            ConflictHttpException::class,
            $exception
        );
    }

    public function testDefaultMessageIsEmpty()
    {
        $exception = new AlreadyVerifiedException();

        // Par défaut, le message est vide (géré en amont)
        $this->assertEmpty(
            $exception->getMessage(),
            'Expected default exception message to be empty'
        );
    }

    public function testGetErrorCodeReturnsAlreadyVerified()
    {
        $exception = new AlreadyVerifiedException();

        $this->assertEquals(
            'already_verified',
            $exception->getErrorCode()
        );
    }

    public function testGetStatusCodeIs409()
    {
        $exception = new AlreadyVerifiedException();

        // 409 Conflict
        $this->assertEquals(
            409,
            $exception->getStatusCode(),
            'Expected HTTP status code 409 for ConflictHttpException'
        );
    }
}
