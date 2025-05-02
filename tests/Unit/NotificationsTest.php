<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Notifications\EmailUpdatedNotification;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use App\Notifications\VerifyNewEmailNotification;

class NotificationsTest extends TestCase
{
    public function testToArrayReturnsEmptyArrayEMailUpdated()
    {
        // Création d'une instance de la notification avec des paramètres fictifs
        $notification = new EmailUpdatedNotification('new@example.com', 'old@example.com', 'raw_token');

        // Vérifie que la méthode toArray retourne un tableau vide
        $this->assertEquals([], $notification->toArray(new \App\Models\User));
    }

    public function testToArrayReturnsEmptyArrayResetPassword()
    {
        // Création d'une instance de la notification avec un token fictif
        $notification = new ResetPasswordNotification('dummy-token');

        // Vérifie que la méthode toArray retourne un tableau vide
        $this->assertEquals([], $notification->toArray(new \App\Models\User));
    }

    public function testToArrayReturnsEmptyArrayVerifyEMail()
    {
        // Création d'une instance de la notification
        $notification = new VerifyEmailNotification();

        // Vérifie que la méthode toArray retourne un tableau vide
        $this->assertEquals([], $notification->toArray(new \App\Models\User));
    }
}
