<?php

namespace Tests\Unit\Services\Auth;

use Tests\TestCase;
use App\Models\User;
use App\Services\Auth\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use PragmaRX\Google2FA\Google2FA;

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
        $qrCodeUrl = 'otpauth://totp/App:'.$user->email.'?secret='.$secret;

        $this->google2fa
            ->shouldReceive('generateSecretKey')
            ->once()
            ->andReturn($secret);

        $this->google2fa
            ->shouldReceive('getQRCodeUrl')
            ->withArgs(function ($appName, $email, $sec) use ($secret, $user) {
                return $appName === config('app.name')
                    && $email === $user->email
                    && $sec === $secret;
            })
            ->once()
            ->andReturn($qrCodeUrl);

        // 3) Exécution
        $result = $this->service->enable($user);

        // 4) Assertions sur le retour
        $this->assertSame([
            'secret'    => $secret,
            'qrCodeUrl' => $qrCodeUrl,
        ], $result);

        // 5) Vérification que l'utilisateur a bien été mis à jour en base
        $this->assertDatabaseHas('users', [
            'id'              => $user->id,
            'twofa_enabled'   => true,
            'twofa_secret'    => $secret,
        ]);
    }

    public function testEnableThrowsHttpResponseExceptionWhenAlreadyEnabled()
    {
        // 1) Préparation : un utilisateur avec 2FA déjà activée
        $user = User::factory()->create([
            'twofa_enabled' => true,
            'twofa_secret'  => 'OLDSECRET',
        ]);

        $this->expectException(HttpResponseException::class);

        try {
            // 2) Appel
            $this->service->enable($user);
        } catch (HttpResponseException $e) {
            // 3) Vérifier le contenu de la réponse JSON
            $response = $e->getResponse();
            $this->assertSame(400, $response->getStatusCode());

            $data = $response->getData(true);
            $this->assertSame('Two-factor authentication is already enabled', $data['message']);
            $this->assertSame('twofa_already_enabled', $data['code']);

            // on relance pour que PHPUnit marque le test comme réussi sur expectException
            throw $e;
        }
    }
}

