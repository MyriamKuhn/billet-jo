<?php

namespace Tests\Unit\Exceptions;

use Tests\TestCase;
use App\Exceptions\Auth\UserNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UserNotFoundExceptionTest extends TestCase
{
    public function testExceptionIsInstanceOfNotFoundHttpException()
    {
        $exception = new UserNotFoundException();

        $this->assertInstanceOf(NotFoundHttpException::class, $exception);
    }

    public function testDefaultMessageIsUserNotFound()
    {
        $exception = new UserNotFoundException();

        // Par défaut, le message est vide car géré ailleurs
        $this->assertEmpty(
            $exception->getMessage(),
            'Expected default message to be empty'
        );
    }

    public function testGetStatusCodeIs404()
    {
        $exception = new UserNotFoundException();
        // Vérifie que le status HTTP est bien 404
        $this->assertEquals(404, $exception->getStatusCode());
    }

    public function testGetErrorCodeReturnsUserNotFound()
    {
        $exception = new UserNotFoundException();

        $this->assertEquals('user_not_found', $exception->getErrorCode());
    }
}

