<?php
namespace Tests\Unit\Services\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;
use App\Services\Auth\AuthService;
use PragmaRX\Google2FA\Google2FA;
use Illuminate\Contracts\Auth\PasswordBroker;
use App\Services\CartService;
use Psr\Log\LoggerInterface;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Notification;
use App\Models\EmailUpdate;
use App\Helpers\EmailHelper;
use App\Notifications\VerifyNewEmailNotification;
use App\Notifications\EmailUpdatedNotification;
use Laravel\Sanctum\PersonalAccessToken;
use App\Services\Auth\TwoFactorService;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testLoginWithInvalidCredentialsThrowsException()
    {
        $service = $this->makeAuthService();

        $this->expectException(HttpResponseException::class);

        $service->login([
            'email'    => 'nonexistent@example.com',
            'password' => 'wrong',
            'remember' => false,
        ]);
    }

    public function testLoginWhenAccountDisabledThrowsException()
    {
        $user = User::factory()->create(['is_active' => false]);
        $service = $this->makeAuthService();

        $this->expectException(HttpResponseException::class);

        $service->login([
            'email' => $user->email,
            'password' => 'password',
            'remember' => false,
        ]);
    }

    public function testLoginWhenEmailNotVerifiedSendsVerificationAndThrows()
    {
        $user = User::factory()->create(['is_active' => true]);
        // Mark as unverified by clearing email_verified_at
        $user->email_verified_at = null;
        $user->save();

        $service = $this->makeAuthService();

        // Spy notification
        Notification::fake();

        $this->expectException(HttpResponseException::class);

        $service->login([
            'email' => $user->email,
            'password' => 'password',
            'remember' => false,
        ]);

        Notification::assertSentTo(
            $user,
            \Illuminate\Auth\Notifications\VerifyEmail::class
        );
    }

    public function testLoginRequiresTwoFactorCode()
    {
        $user = User::factory()->create(['is_active' => true, 'twofa_enabled' => true]);
        $user->markEmailAsVerified();
        Hash::shouldReceive('check')->andReturn(true);

        $service = $this->makeAuthService();

        $this->expectException(HttpResponseException::class);
        $service->login([
            'email' => $user->email,
            'password' => 'password',
            'remember' => false,
        ]);
    }

    public function testLoginWithInvalidTwoFactorCodeThrowsException()
    {
        $user = User::factory()->create([
            'is_active'     => true,
            'twofa_enabled' => true,
        ]);
        $user->markEmailAsVerified();
        Hash::shouldReceive('check')->andReturn(true);

        // 1) on mocke TwoFactorService, pas Google2FA
        $mock2faSvc = Mockery::mock(\App\Services\Auth\TwoFactorService::class);
        $mock2faSvc
            ->shouldReceive('verifyOtp')
            ->once()
            ->withArgs(fn($calledUser, $code) =>
                $calledUser->id === $user->id && $code === '123456'
            )
            ->andReturn(false);

        // 2) on l’injecte dans AuthService
        $service = $this->makeAuthService(
            twoFactorService: $mock2faSvc
        );

        $this->expectException(HttpResponseException::class);

        $service->login([
            'email'     => $user->email,
            'password'  => 'password',
            'twofa_code'=> '123456',
            'remember'  => false,
        ]);
    }

    public function testSuccessfulLoginMergesCartAndReturnsData()
    {
        $user = User::factory()->create(['is_active' => true, 'twofa_enabled' => false]);
        $user->markEmailAsVerified();
        Hash::shouldReceive('check')->andReturn(true);

        $mock2fa = Mockery::mock(Google2FA::class);

        $cartService = Mockery::mock(CartService::class);
        $cartService->shouldReceive('mergeGuestIntoUser')->with($user->id)->once();

        $service = $this->makeAuthService(google2fa: $mock2fa, cartService: $cartService);

        $result = $service->login([
            'email' => $user->email,
            'password' => 'password',
            'remember' => true,
        ]);

        $this->assertArrayHasKey('token', $result);
        $this->assertEquals('Logged in successfully', $result['message']);
        $this->assertEquals($user->id, $result['user']['id']);
    }

    public function testLogoutWithNoActiveTokenThrowsException()
    {
        $this->expectException(HttpResponseException::class);

        $service = $this->makeAuthService();
        $user = Mockery::mock(User::class);
        $user->shouldReceive('currentAccessToken')->andReturn(null);

        $service->logout($user);
    }

    public function testSuccessfulLogoutDeletesToken()
    {
        $token = Mockery::mock();
        $token->shouldReceive('delete')->once();

        $service = $this->makeAuthService();
        $user = Mockery::mock(User::class);
        $user->shouldReceive('currentAccessToken')->andReturn($token);

        $result = $service->logout($user);
        $this->assertEquals(['message' => 'Logged out successfully'], $result);
    }

    public function testSendResetLinkFailureThrowsException()
    {
        $this->expectException(HttpResponseException::class);

        $broker = Mockery::mock(PasswordBroker::class);
        $broker->shouldReceive('sendResetLink')->andReturn('failed');

        $service = $this->makeAuthService(passwordBroker: $broker);
        $service->sendResetLink('test@example.com');
    }

    public function testSendResetLinkSuccessReturnsMessage()
    {
        $broker = Mockery::mock(PasswordBroker::class);
        $broker->shouldReceive('sendResetLink')->andReturn(PasswordBroker::RESET_LINK_SENT);

        $service = $this->makeAuthService(passwordBroker: $broker);
        $result = $service->sendResetLink('test@example.com');

        $this->assertEquals(['message' => 'Password reset link sent'], $result);
    }

    public function testResetPasswordResponses()
    {
        $broker = Mockery::mock(PasswordBroker::class);
        // PASSWORD_RESET
        $broker->shouldReceive('reset')->andReturn(PasswordBroker::PASSWORD_RESET);
        $service = $this->makeAuthService(passwordBroker: $broker);
        $this->assertEquals(['message' => 'Password has been reset successfully'],
            $service->resetPassword(['token'=>'token','email'=>'e','password'=>'p','password_confirmation'=>'p'])
        );

        // INVALID_TOKEN
        $broker = Mockery::mock(PasswordBroker::class);
        $broker->shouldReceive('reset')->andReturn(PasswordBroker::INVALID_TOKEN);
        $service = $this->makeAuthService(passwordBroker: $broker);
        $this->expectException(HttpResponseException::class);
        $service->resetPassword(['token'=>'token','email'=>'e','password'=>'p','password_confirmation'=>'p']);
    }

    public function testUpdatePasswordWithInvalidCurrentPasswordFails()
    {
        $this->expectException(HttpResponseException::class);
        $service = $this->makeAuthService();
        $user = User::factory()->create(['password_hash' => Hash::make('secret')]);

        $service->updatePassword($user, ['current_password'=>'wrong','password'=>'new','password_confirmation'=>'new']);
    }

    public function testUpdatePasswordSuccess()
    {
        $service = $this->makeAuthService();
        $user = User::factory()->create(['password_hash' => Hash::make('secret')]);

        $result = $service->updatePassword($user, ['current_password'=>'secret','password'=>'new','password_confirmation'=>'new']);
        $this->assertEquals(['message' => 'Password changed successfully'], $result);
        $this->assertTrue(Hash::check('new', $user->fresh()->password_hash));
    }

    public function testUpdateEmailStoresRecordAndSendsNotifications()
    {
        $service = $this->makeAuthService();
        // Create user with random email to avoid unique constraint issues
        $user = User::factory()->create();
        $oldEmail = $user->email;
        $newEmail = 'new_' . $user->id . '@example.com';

        Notification::fake();

        $result = $service->updateEmail($user, $newEmail);

        $this->assertEquals(['message' => 'Email change request sent'], $result);
        $this->assertDatabaseHas('email_updates', [
            'user_id'   => $user->id,
            'old_email' => $oldEmail,
            'new_email' => $newEmail,
        ]);

        // Assert verification notification sent to new email route
        Notification::assertSentTo(
            Notification::route('mail', $newEmail),
            VerifyNewEmailNotification::class
        );

        // Assert notification sent to old email user
        Notification::assertSentTo(
            $user,
            EmailUpdatedNotification::class
        );
    }

    public function testDisableTwoFactorScenarios()
    {
        // 1) On mocke Google2FA
        $google2fa = Mockery::mock(Google2FA::class);

        // 2) On instancie le service correct
        $service = new TwoFactorService($google2fa);

        // Prépare un user sans 2FA
        $user = User::factory()->create(['twofa_enabled' => false]);

        // a) cas non activé → exception
        $this->expectException(HttpResponseException::class);
        $service->disableTwoFactor($user, 'code');

        // b) cas activé mais code invalide
        $user->twofa_enabled = true;
        $user->twofa_secret  = 'secret';
        $google2fa
            ->shouldReceive('verifyKey')
            ->once()
            ->with('secret', 'wrong')
            ->andReturn(false);
        $this->expectException(HttpResponseException::class);
        $service->disableTwoFactor($user, 'wrong');

        // c) cas activé et code valide → plus d’exception, deuxfa_disabled
        $google2fa
            ->shouldReceive('verifyKey')
            ->once()
            ->with('secret', '123456')
            ->andReturn(true);
        // on réactive avant de tester la réussite
        $user->twofa_enabled = true;
        $user->twofa_secret  = 'secret';
        $service->disableTwoFactor($user, '123456');

        $fresh = $user->fresh();
        $this->assertFalse($fresh->twofa_enabled, '2FA doit être désactivée');
        $this->assertNull($fresh->twofa_secret, 'Le secret doit être effacé');
    }

    /**
     * Helper to instantiate AuthService with mocked dependencies
     */
    protected function makeAuthService(
        Google2FA $google2fa             = null,
        PasswordBroker $passwordBroker   = null,
        CartService $cartService         = null,
        LoggerInterface $logger          = null,
        TwoFactorService $twoFactorService = null   // ← ajouté
    ): AuthService {
        return new AuthService(
            $google2fa           ?? Mockery::mock(Google2FA::class),
            $passwordBroker      ?? Mockery::mock(PasswordBroker::class),
            $cartService         ?? Mockery::mock(CartService::class),
            $logger              ?? Mockery::mock(LoggerInterface::class),
            $twoFactorService    ?? Mockery::mock(TwoFactorService::class)  // ← aussi
        );
    }

    public function testLoginWhenCartMergeThrowsLogsWarningAndStillReturnsSuccess()
    {
        $user = User::factory()->create([
            'is_active'    => true,
            'twofa_enabled'=> false,
        ]);
        // Simuler email vérifié
        $user->email_verified_at = now();
        $user->save();

        // Valider le mot de passe
        Hash::shouldReceive('check')->andReturn(true);

        // Stub Google2FA
        $mock2fa = Mockery::mock(Google2FA::class);

        // Simuler une exception lors du merge du panier
        $cartService = Mockery::mock(CartService::class);
        $cartService
            ->shouldReceive('mergeGuestIntoUser')
            ->with($user->id)
            ->andThrow(new \Exception('Cart merge failed'));

        // Vérifier qu’on logge l’erreur
        $logger = Mockery::mock(LoggerInterface::class);
        $logger
            ->shouldReceive('warning')
            ->once()
            ->with(
                'Failed to merge guest cart on login',
                Mockery::on(function ($context) use ($user) {
                    return $context['user_id'] === $user->id
                        && str_contains($context['error'], 'Cart merge failed');
                })
            );

        $service = $this->makeAuthService(
            google2fa:   $mock2fa,
            cartService: $cartService,
            logger:      $logger
        );

        $result = $service->login([
            'email'    => $user->email,
            'password' => 'password',
            'remember' => false,
        ]);

        $this->assertEquals('Logged in successfully', $result['message']);
        $this->assertArrayHasKey('token', $result);
    }

    public function testResetPasswordInvokesCallbackAndUpdatesUserPassword()
    {
        // Préparation d’un utilisateur avec un ancien mot de passe
        $user = User::factory()->create([
            'password_hash' => Hash::make('oldpassword'),
        ]);
        $newPassword = 'newsecret';
        $data = [
            'token'                 => 'dummy-token',
            'email'                 => $user->email,
            'password'              => $newPassword,
            'password_confirmation' => $newPassword,
        ];

        // Stub du PasswordBroker qui exécute bien le callback avec notre user et le nouveau mot de passe
        $broker = Mockery::mock(PasswordBroker::class);
        $broker->shouldReceive('reset')
            ->once()
            ->with(
                $data,
                Mockery::on(function ($callback) use ($user, $newPassword) {
                    // On appelle la closure comme dans le service
                    $callback($user, $newPassword);
                    return true;
                })
            )
            ->andReturn(PasswordBroker::PASSWORD_RESET);

        $service = $this->makeAuthService(passwordBroker: $broker);

        // Appel de la méthode
        $result = $service->resetPassword($data);

        // Assertions
        $this->assertEquals(['message' => 'Password has been reset successfully'], $result);
        $this->assertTrue(Hash::check($newPassword, $user->fresh()->password_hash));
    }

    public function testResetPasswordThrowsExceptionOnInvalidUser()
    {
        // Préparation des données simulant un utilisateur non trouvé
        $data = [
            'token'                 => 'dummy-token',
            'email'                 => 'nonexistent@example.com',
            'password'              => 'irrelevant',
            'password_confirmation' => 'irrelevant',
        ];

        // Stub du PasswordBroker pour renvoyer INVALID_USER
        $broker = Mockery::mock(PasswordBroker::class);
        $broker->shouldReceive('reset')
            ->once()
            ->with(
                $data,
                Mockery::type('callable')
            )
            ->andReturn(PasswordBroker::INVALID_USER);

        $service = $this->makeAuthService(passwordBroker: $broker);

        try {
            $service->resetPassword($data);
            $this->fail('Expected HttpResponseException was not thrown.');
        } catch (HttpResponseException $e) {
            $response = $e->getResponse();
            // Vérifier le code HTTP 404
            $this->assertEquals(404, $response->status());
            // Vérifier le contenu JSON de la réponse
            $this->assertEquals([
                'message' => 'No user found with this email',
                'code'    => 'user_not_found',
            ], $response->getData(true));
        }
    }

    public function testDisableTwoFactorThrowsOnInvalidCode()
    {
        // Création d’un user avec 2FA activée
        $user = User::factory()->create([
            'twofa_enabled' => true,
            'twofa_secret'  => 'totp-secret',
        ]);

        // Mock Google2FA pour retourner false sur la vérif
        $mock2fa = Mockery::mock(Google2FA::class);
        $mock2fa
            ->shouldReceive('verifyKey')
            ->with('totp-secret', 'wrongcode')
            ->andReturn(false);

        $service = new \App\Services\Auth\TwoFactorService($mock2fa);

        try {
            $service->disableTwoFactor($user, 'wrongcode');
            $this->fail('Expected HttpResponseException was not thrown.');
        } catch (HttpResponseException $e) {
            $response = $e->getResponse();
            $this->assertEquals(400, $response->status());
            $this->assertEquals([
                'message' => 'Invalid two-factor authentication code or recovery code',
                'code'    => 'twofa_invalid_code',
            ], $response->getData(true));
        }
    }

    public function testLoginWithTwoFactorSuccess()
    {
        // 1) Création d’un user avec 2FA activée et un mot de passe "secret"
        $user = User::factory()->create([
            'password_hash'   => bcrypt('secret'),
            'is_active'       => true,
            'email_verified_at' => now(),
            'twofa_enabled'   => true,
            'twofa_secret'    => 'valid-secret',
        ]);

        // 2) Mock du PasswordBroker (pas utilisé ici, mais nécessaire au constructeur)
        $passwordBroker = Mockery::mock(\Illuminate\Contracts\Auth\PasswordBroker::class);
        // 3) Mock du CartService et du Logger (on ignore les appels)
        $cartService    = Mockery::mock(\App\Services\CartService::class)->shouldIgnoreMissing();
        $logger         = Mockery::mock(\Psr\Log\LoggerInterface::class)->shouldIgnoreMissing();

        // 4) Mock du TwoFactorService pour valider le code 2FA
        $twoFactorService = Mockery::mock(\App\Services\Auth\TwoFactorService::class);
        $twoFactorService
            ->shouldReceive('verifyOtp')
            ->once()
            ->withArgs(function ($calledUser, $calledCode) use ($user) {
                return $calledUser instanceof \App\Models\User
                    && $calledUser->id === $user->id
                    && $calledCode === 'correctcode';
            })
            ->andReturnTrue();

        // 5) Instanciation du service avec tous les mocks
        $authService = new \App\Services\Auth\AuthService(
            new \PragmaRX\Google2FA\Google2FA(),
            $passwordBroker,
            $cartService,
            $logger,
            $twoFactorService
        );

        // 6) Appel de login() avec 2FA
        $response = $authService->login([
            'email'      => $user->email,
            'password'   => 'secret',
            'twofa_code' => 'correctcode',
            'remember'   => false,
        ]);

        // 7) Assertions
        $this->assertIsArray($response);
        $this->assertEquals('Logged in successfully', $response['message']);
        $this->assertArrayHasKey('token', $response);
        $this->assertEquals($user->id, $response['user']['id']);
        $this->assertTrue($response['user']['twofa_enabled']);
    }
}
