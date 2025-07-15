<?php

namespace Tests\Unit\Services\Auth;

use Tests\TestCase;
use App\Models\User;
use App\Models\EmailUpdate;
use App\Services\Auth\EmailUpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Helpers\EmailHelper;
use App\Exceptions\Auth\MissingVerificationTokenException;
use App\Exceptions\Auth\EmailUpdateNotFoundException;

class EmailUpdateServiceTest extends TestCase
{
    use RefreshDatabase;

    private EmailUpdateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EmailUpdateService();
    }

    public function testVerifyNewEmailThrowsWhenTokenIsMissing()
    {
        $this->expectException(MissingVerificationTokenException::class);
        $this->service->verifyNewEmail(null);
    }

    public function testVerifyNewEmailThrowsWhenNoEmailUpdateFound()
    {
        $rawToken = 'someRandomToken';
        $this->expectException(EmailUpdateNotFoundException::class);
        $this->service->verifyNewEmail($rawToken);
    }

    public function testVerifyNewEmailUpdatesUserEmailAndTouchesRecord()
    {
        // 1) Préparation de l’utilisateur
        $user = User::factory()->create([
            'email'             => 'initial@example.com',
            'email_verified_at' => null,
        ]);

        // 2) Création du token et du record EmailUpdate
        $rawToken = 'valid-token';
        $hashed   = EmailHelper::hashToken($rawToken);
        $oldEmail = 'initial@example.com';
        $newEmail = 'updated@example.com';

        $emailUpdate = EmailUpdate::factory()->create([
            'token'     => $hashed,
            'user_id'   => $user->id,
            'old_email' => $oldEmail,
            'new_email' => $newEmail,
        ]);

        $initialUpdatedAt = $emailUpdate->updated_at;

        // 3) Exécution du service
        $returnedUser = $this->service->verifyNewEmail($rawToken);

        // 4) Assertions sur l’utilisateur retourné
        $this->assertSame($user->id, $returnedUser->id);
        $this->assertSame($newEmail, $returnedUser->email);
        $this->assertNotNull($returnedUser->email_verified_at);

        // 5) Recharger le record depuis la base
        $freshEmailUpdate = EmailUpdate::find($emailUpdate->id);

        // 6) Vérifier que updated_at n’a pas régressé
        $this->assertTrue(
            $freshEmailUpdate->updated_at->timestamp >= $initialUpdatedAt->timestamp,
            'EmailUpdate.updated_at should be >= initial value'
        );
    }

    public function testCancelEmailUpdateThrowsWhenNoRecordFound()
    {
        $this->expectException(EmailUpdateNotFoundException::class);
        $this->service->cancelEmailUpdate('nonexistentToken', 'old@example.com');
    }

    public function testCancelEmailUpdateRestoresOldEmailAndDeletesRecord()
    {
        // Préparation de l'utilisateur
        $user = User::factory()->create([
            'email'             => 'before@example.com',
            'email_verified_at' => null,
        ]);

        // Création du record EmailUpdate
        $rawToken = 'cancel-token';
        $hashed   = EmailHelper::hashToken($rawToken);
        $oldEmail = 'before@example.com';
        $newEmail = 'temp@example.com';

        $emailUpdate = EmailUpdate::factory()->create([
            'token'     => $hashed,
            'user_id'   => $user->id,
            'old_email' => $oldEmail,
            'new_email' => $newEmail,
        ]);

        // Exécution du service — note qu'on ne passe plus $oldEmail en second param
        $returnedUser = $this->service->cancelEmailUpdate($rawToken);

        // L'email de l'utilisateur est bien revenu à l'ancienne valeur
        $this->assertSame($oldEmail, $returnedUser->email);
        // email_verified_at reste inchangé (null)
        $this->assertNull($returnedUser->email_verified_at);

        // Le record a bien été supprimé
        $this->assertDatabaseMissing('email_updates', [
            'id' => $emailUpdate->id,
        ]);
    }
}

