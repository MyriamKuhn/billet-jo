<?php

namespace Tests\Unit\Services\Auth;

use Tests\TestCase;
use App\Models\User;
use App\Services\Auth\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use PragmaRX\Google2FA\Google2FA;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class TwoFactorServiceTest extends TestCase
{
    use RefreshDatabase;

    private Google2FA $google2fa;
    private TwoFactorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // On mocke Google2FA pour contrôler les retours
        $this->google2fa = \Mockery::mock(Google2FA::class);
        $this->service  = new TwoFactorService($this->google2fa);
    }

    public function testEnableGenerateAndReturnSecretAndQrUrlWhenNotEnabled()
    {
        // 1) Préparation : un utilisateur sans 2FA
        $user = User::factory()->create([
            'twofa_enabled' => false,
            'twofa_secret'  => null,
        ]);

        // 2) On définit les valeurs générées par Google2FA
        $secret    = 'SECRET123456';
        $qrCodeUrl = 'otpauth://totp/'.config('app.name').':'.$user->email.'?secret='.$secret;

        $this->google2fa
            ->shouldReceive('generateSecretKey')
            ->once()
            ->andReturn($secret);

        $this->google2fa
            ->shouldReceive('getQRCodeUrl')
            ->withArgs(fn($appName, $email, $sec) =>
                $appName === config('app.name')
            && $email   === $user->email
            && $sec     === $secret
            )
            ->once()
            ->andReturn($qrCodeUrl);

        // 3) Exécution : on appelle prepareEnable(), pas enable()
        $result = $this->service->prepareEnable($user);

        // 4) Assertions sur le retour (on peut ignorer expires_at si besoin)
        $this->assertArrayHasKey('secret',    $result);
        $this->assertArrayHasKey('qrCodeUrl', $result);
        $this->assertEquals($secret,    $result['secret']);
        $this->assertEquals($qrCodeUrl, $result['qrCodeUrl']);

        // 5) Vérification que l’utilisateur a bien été mis à jour
        $this->assertDatabaseHas('users', [
            'id'               => $user->id,
            'twofa_secret_temp'=> $secret,
            'twofa_enabled'    => false, // toujours false à ce stade
        ]);
    }

    public function testPrepareEnableThrowsHttpResponseExceptionWhenAlreadyEnabled()
    {
        // 1) Préparation : un utilisateur avec 2FA déjà activée
        $user = User::factory()->create([
            'twofa_enabled' => true,
            'twofa_secret'  => 'OLDSECRET',
        ]);

        $this->expectException(HttpResponseException::class);

        try {
            // 2) Appel de la bonne méthode
            $this->service->prepareEnable($user);
        } catch (HttpResponseException $e) {
            // 3) Vérifier le statut et le payload JSON
            $response = $e->getResponse();
            $this->assertSame(400, $response->getStatusCode());

            $data = $response->getData(true);
            $this->assertSame(
                'Two-factor authentication is already enabled',
                $data['message']
            );
            $this->assertSame('twofa_already_enabled', $data['code']);

            // Re‑lancer l’exception pour satisfaire expectException
            throw $e;
        }
    }

    public function testConfirmEnableThrowsWhenNoTempSetupOrExpired(): void
    {
        $user = User::factory()->create([
            'twofa_enabled'         => false,
            'twofa_secret_temp'     => null,
            'twofa_temp_expires_at' => null,
        ]);

        $this->expectException(HttpResponseException::class);
        try {
            $this->service->confirmEnable($user, '123456');
        } catch (HttpResponseException $e) {
            $resp = $e->getResponse();
            $this->assertSame(400, $resp->getStatusCode());
            $data = $resp->getData(true);
            $this->assertSame('twofa_no_setup_in_progress_or_expired', $data['code']);
            throw $e;
        }
    }

    public function testConfirmEnableThrowsWhenOtpInvalid(): void
    {
        // Préparer un user avec secret_temp et expiry dans le futur
        $user = User::factory()->create([
            'twofa_enabled'         => false,
            'twofa_secret_temp'     => 'TEMPSECRET',
            'twofa_temp_expires_at' => Carbon::now()->addMinutes(5),
        ]);

        // Stub verifyKey → false
        $this->google2fa
            ->shouldReceive('verifyKey')
            ->with('TEMPSECRET', '000000')
            ->once()
            ->andReturnFalse();

        $this->expectException(HttpResponseException::class);
        try {
            $this->service->confirmEnable($user, '000000');
        } catch (HttpResponseException $e) {
            $resp = $e->getResponse();
            $this->assertSame(400, $resp->getStatusCode());
            $data = $resp->getData(true);
            $this->assertSame('twofa_invalid_otp', $data['code']);
            throw $e;
        }
    }

    public function testConfirmEnableActivatesAndReturnsRecoveryCodes(): void
    {
        $user = User::factory()->create([
            'twofa_enabled'         => false,
            'twofa_secret_temp'     => 'TEMPSECRET',
            'twofa_temp_expires_at' => Carbon::now()->addMinutes(5),
        ]);

        // Stub verifyKey → true
        $this->google2fa
            ->shouldReceive('verifyKey')
            ->with('TEMPSECRET', '123456')
            ->once()
            ->andReturnTrue();

        $result = $this->service->confirmEnable($user, '123456');

        $this->assertArrayHasKey('recovery_codes', $result);
        $this->assertCount(8, $result['recovery_codes']);

        // Refresh user from DB
        $user->refresh();
        $this->assertTrue($user->twofa_enabled);
        $this->assertNull($user->twofa_secret_temp);
        $this->assertNull($user->twofa_temp_expires_at);
        $this->assertNotNull($user->twofa_secret);
        $this->assertIsArray($user->twofa_recovery_codes);
        $this->assertCount(8, $user->twofa_recovery_codes);
    }

    public function testDisableTwoFactorThrowsWhenNotEnabled(): void
    {
        $user = User::factory()->create([
            'twofa_enabled' => false,
        ]);

        $this->expectException(HttpResponseException::class);
        try {
            $this->service->disableTwoFactor($user, 'ANY');
        } catch (HttpResponseException $e) {
            $resp = $e->getResponse();
            $this->assertSame(400, $resp->getStatusCode());
            $data = $resp->getData(true);
            $this->assertSame('twofa_not_enabled', $data['code']);
            throw $e;
        }
    }

    public function testDisableTwoFactorWithValidOtp(): void
    {
        $user = User::factory()->create([
            'twofa_enabled' => true,
            'twofa_secret'  => 'SECRETKEY',
            'twofa_recovery_codes' => null,
        ]);

        $this->google2fa
            ->shouldReceive('verifyKey')
            ->with('SECRETKEY', '654321')
            ->once()
            ->andReturnTrue();

        $this->service->disableTwoFactor($user, '654321');

        $user->refresh();
        $this->assertFalse($user->twofa_enabled);
        $this->assertNull($user->twofa_secret);
    }

    public function testDisableTwoFactorConsumesRecoveryCode(): void
    {
        // Préparer un user avec un code de récupération hashé
        $plain = 'RECOVERCODE';
        $hash  = Hash::make($plain);
        $user = User::factory()->create([
            'twofa_enabled'      => true,
            'twofa_secret'       => null,
            'twofa_recovery_codes'=> [$hash],
        ]);

        // Pas de vérification OTP → verifyKey ne doit pas être appelé
        $this->google2fa->shouldReceive('verifyKey')->never();

        // Doit réussir avec le recovery code
        $this->service->disableTwoFactor($user, $plain);

        $user->refresh();
        $this->assertFalse($user->twofa_enabled);
        $this->assertNull($user->twofa_recovery_codes);
    }

    public function testDisableTwoFactorThrowsWhenCodeInvalid(): void
    {
        $user = User::factory()->create([
            'twofa_enabled'       => true,
            'twofa_secret'        => 'SECRETKEY',
            'twofa_recovery_codes'=> [Hash::make('GOODCODE')],
        ]);

        // OTP invalide et recovery invalide
        $this->google2fa
            ->shouldReceive('verifyKey')
            ->with('SECRETKEY', 'WRONG')
            ->once()
            ->andReturnFalse();

        $this->expectException(HttpResponseException::class);
        try {
            $this->service->disableTwoFactor($user, 'WRONG');
        } catch (HttpResponseException $e) {
            $resp = $e->getResponse();
            $this->assertSame(400, $resp->getStatusCode());
            $data = $resp->getData(true);
            $this->assertSame('twofa_invalid_code', $data['code']);
            throw $e;
        }
    }

    public function testVerifyOtpBehavior(): void
    {
        $user = User::factory()->create([
            'twofa_enabled' => false,
            'twofa_secret'  => null,
        ]);

        // Désactivé → false
        $this->assertFalse($this->service->verifyOtp($user, 'ANY'));

        // Activer et stubber Google2FA
        $user->forceFill([
            'twofa_enabled' => true,
            'twofa_secret'  => 'SECRETXYZ',
        ])->save();

        $this->google2fa
            ->shouldReceive('verifyKey')
            ->with('SECRETXYZ', 'CODE123')
            ->once()
            ->andReturnTrue();

        $this->assertTrue($this->service->verifyOtp($user, 'CODE123'));

        // Mauvais code → false
        $this->google2fa
            ->shouldReceive('verifyKey')
            ->with('SECRETXYZ', 'BAD')
            ->once()
            ->andReturnFalse();

        $this->assertFalse($this->service->verifyOtp($user, 'BAD'));
    }

    public function testConfirmEnableThrowsWhenAlreadyEnabledViaConfirm(): void
    {
        // Préparer un user déjà en 2FA (même si secret_temp est rempli, l'exception passe avant)
        $user = User::factory()->create([
            'twofa_enabled'         => true,
            'twofa_secret_temp'     => 'SOMESECRET',
            'twofa_temp_expires_at' => Carbon::now()->addMinutes(5),
        ]);

        $this->expectException(HttpResponseException::class);
        try {
            $this->service->confirmEnable($user, '123456');
        } catch (HttpResponseException $e) {
            $resp = $e->getResponse();
            $this->assertSame(400, $resp->getStatusCode());
            $data = $resp->getData(true);
            $this->assertSame('twofa_already_enabled', $data['code']);
            throw $e;
        }
    }

    public function testDisableTwoFactorClearsTemporaryFields(): void
    {
        // Préparer un user avec OTP valide et champs temp non nuls
        $user = User::factory()->create([
            'twofa_enabled'         => true,
            'twofa_secret'          => 'SECRETKEY',
            'twofa_secret_temp'     => 'TEMPSECRET',
            'twofa_temp_expires_at' => Carbon::now()->addMinutes(10),
            'twofa_recovery_codes'  => null,
        ]);

        // Stub OTP valide
        $this->google2fa
            ->shouldReceive('verifyKey')
            ->with('SECRETKEY', 'VALIDCODE')
            ->once()
            ->andReturnTrue();

        // Désactivation
        $this->service->disableTwoFactor($user, 'VALIDCODE');

        $user->refresh();
        // Les champs temporaires doivent être purgés
        $this->assertNull($user->twofa_secret_temp);
        $this->assertNull($user->twofa_temp_expires_at);
    }
}

