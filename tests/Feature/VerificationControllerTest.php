<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Str;
use App\Services\Auth\EmailVerificationService;
use App\Services\CartService;
use App\Services\Auth\EmailUpdateService;
use App\Exceptions\Auth\UserNotFoundException;
use App\Exceptions\Auth\InvalidVerificationLinkException;
use Illuminate\Routing\Middleware\ValidateSignature;
use App\Exceptions\Auth\AlreadyVerifiedException;
use App\Exceptions\Auth\MissingVerificationTokenException;
use App\Exceptions\Auth\EmailUpdateNotFoundException;

class VerificationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Mock services
        $this->mock(EmailVerificationService::class);
        $this->mock(CartService::class);
        $this->mock(EmailUpdateService::class);
        // Ensure environment is non-production and frontend_url is set
        config()->set('app.env', 'testing');
        config()->set('app.frontend_url', 'http://frontend.test');
    }

    public function testVerifyReturnsJsonOnSuccess()
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $id = $user->id;
        $hash = sha1($user->email);

        $this->mock(EmailVerificationService::class)
            ->shouldReceive('verify')
            ->with($id, $hash)
            ->once()
            ->andReturn($user);

        $this->mock(CartService::class)
            ->shouldReceive('getUserCart')
            ->with($user)
            ->once();

        $url = "/api/auth/email/verify/{$id}/{$hash}?expires=123&signature=abc";

        // ← Ajoute cette ligne :
        $this->withoutMiddleware(ValidateSignature::class);

        $response = $this->getJson($url);
        $response->assertOk()
                ->assertJson([
                    'message'      => 'Email verified successfully',
                    'redirect_url' => config('app.frontend_url') . '/verification-result/success',
                ]);
    }

    public function testResendReturns200()
    {
        $user = User::factory()->create();
        /** @var string $data */
        $data = ['message' => 'Verification email resent'];

        /** @noinspection PhpParamsInspection */
        $this->mock(EmailVerificationService::class)
            ->shouldReceive('resend')
            ->with($user->email)
            ->once()
            ->andReturn($data);

        Sanctum::actingAs($user, ['*']);
        $this->postJson('/api/auth/email/resend', [
            'email' => $user->email,
        ])
            ->assertOk()
            ->assertJson($data);
    }

    public function testVerifyNewReturnsJsonOnSuccess()
    {
        $user = User::factory()->create();
        $token = Str::random(40);

        // Mock uniquement le service de changement d'email
        $this->mock(EmailUpdateService::class)
            ->shouldReceive('verifyNewEmail')
            ->with($token)
            ->once()
            ->andReturn($user);

        $url = "/api/auth/email/change/verify?token={$token}&expires=123&signature=abc";

        // On désactive la validation de signature pour bypasser le middleware
        $this->withoutMiddleware(ValidateSignature::class);

        $response = $this->getJson($url);

        $response->assertOk()
                ->assertJson([
                    'message'      => 'Email updated successfully',
                    'redirect_url' => config('app.frontend_url') . '/verification-result/success',
                ]);
    }

    public function testCancelChangeReturnsJsonOnSuccess()
    {
        $user  = User::factory()->create();
        $token = Str::random(40);

        // Mock EmailUpdateService avec un seul argument
        $this->mock(EmailUpdateService::class)
            ->shouldReceive('cancelEmailUpdate')
            ->with($token)
            ->once()
            ->andReturn($user);

        // Désactiver tous les middleware pour ne pas rater la route
        $this->withoutMiddleware();

        // 1) Variante “manuelle” : URL avec double /auth/
        $url = "/api/auth/auth/email/change/cancel/{$token}?expires=123&signature=abc";

        $response = $this->getJson($url);

        $response->assertOk()
                ->assertJson([
                    'message'      => 'Email update canceled',
                    'redirect_url' => config('app.frontend_url') . '/verification-result/success',
                ]);
    }

    public function testVerifyRedirectsToSuccessOnProduction()
    {
        // 0) Forcer l'environnement "production" dans le container
        $this->app['env'] = 'production';
        // Et définir un frontend_url factice
        config()->set('app.frontend_url', 'https://frontend.test');

        $user = User::factory()->create(['email_verified_at' => null]);
        $id   = $user->id;
        $hash = sha1($user->email);
        $base = config('app.frontend_url') . '/verification-result';

        // 1) Succès : verificationService->verify() retourne un User
        $this->mock(EmailVerificationService::class)
            ->shouldReceive('verify')
            ->with($id, $hash)
            ->once()
            ->andReturn($user);

        // 2) getUserCart() doit être appelé
        $this->mock(CartService::class)
            ->shouldReceive('getUserCart')
            ->with($user)
            ->once();

        $url = "/api/auth/email/verify/{$id}/{$hash}?expires=123&signature=abc";

        // 3) Désactiver la validation de signature Laravel
        $this->withoutMiddleware(ValidateSignature::class);

        // 4) Exécuter la requête : on attend 302 vers /success
        $this->get($url)
            ->assertStatus(302)
            ->assertRedirect("{$base}/success");
    }

    public function testVerifyRedirectsToInvalidWhenUserNotFound()
    {
        // 0) Forcer l’environnement "production" et frontend_url
        $this->app['env'] = 'production';
        config()->set('app.frontend_url', 'https://frontend.test');
        $base = config('app.frontend_url') . '/verification-result';

        $id   = 999;
        $hash = 'fakehash';
        $url  = "/api/auth/email/verify/{$id}/{$hash}?expires=123&signature=abc";

        // 1) Mock qui lance UserNotFoundException
        $this->mock(EmailVerificationService::class)
            ->shouldReceive('verify')
            ->with($id, $hash)
            ->once()
            ->andThrow(new UserNotFoundException());

        // 2) Bypass de la validation de signature
        $this->withoutMiddleware(ValidateSignature::class);

        // 3) On s’attend à être redirigé vers /invalid
        $this->get($url)
            ->assertStatus(302)
            ->assertRedirect("{$base}/invalid");
    }

    public function testVerifyRedirectsToInvalidWhenLinkIsInvalid()
    {
        // 0) Forcer l’environnement et le frontend_url
        $this->app['env'] = 'production';
        config()->set('app.frontend_url', 'https://frontend.test');
        $base = config('app.frontend_url') . '/verification-result';

        $id   = 999;
        $hash = 'fakehash';
        $url  = "/api/auth/email/verify/{$id}/{$hash}?expires=123&signature=abc";

        // 1) Mock qui lance InvalidVerificationLinkException
        $this->mock(EmailVerificationService::class)
            ->shouldReceive('verify')
            ->with($id, $hash)
            ->once()
            ->andThrow(new InvalidVerificationLinkException());

        // 2) Bypass du middleware de signature
        $this->withoutMiddleware(ValidateSignature::class);

        // 3) On s’attend à une redirection 302 vers /invalid
        $this->get($url)
            ->assertStatus(302)
            ->assertRedirect("{$base}/invalid");
    }

    public function testVerifyRedirectsToAlreadyVerifiedIfAlreadyVerifiedException()
    {
        // 0) Forcer l’environnement “production” et frontend_url
        $this->app['env'] = 'production';
        config()->set('app.frontend_url', 'https://frontend.test');
        $base = config('app.frontend_url') . '/verification-result';

        $id   = 1;
        $hash = 'anyhash';
        $url  = "/api/auth/email/verify/{$id}/{$hash}?expires=123&signature=abc";

        // 1) Mock qui lance AlreadyVerifiedException
        $this->mock(EmailVerificationService::class)
            ->shouldReceive('verify')
            ->with($id, $hash)
            ->once()
            ->andThrow(new AlreadyVerifiedException());

        // 2) Bypass de la validation de signature
        $this->withoutMiddleware(ValidateSignature::class);

        // 3) On s’attend à une redirection 302 vers /already-verified
        $this->get($url)
            ->assertStatus(302)
            ->assertRedirect("{$base}/already-verified");
    }

    public function testVerifyRedirectsToErrorOnUnexpectedException()
    {
        // 0) Forcer l’environnement "production" et frontend_url
        $this->app['env'] = 'production';
        config()->set('app.frontend_url', 'https://frontend.test');
        $base = config('app.frontend_url') . '/verification-result';

        $id   = 1;
        $hash = 'anyhash';
        $url  = "/api/auth/email/verify/{$id}/{$hash}?expires=123&signature=abc";

        // 1) Mock qui lance une exception inattendue
        $this->mock(EmailVerificationService::class)
            ->shouldReceive('verify')
            ->with($id, $hash)
            ->once()
            ->andThrow(new \RuntimeException('boom'));

        // 2) Bypass du ValidateSignature middleware
        $this->withoutMiddleware(ValidateSignature::class);

        // 3) Appel et assertion de redirection vers /error
        $this->get($url)
            ->assertStatus(302)
            ->assertRedirect("{$base}/error");
    }

    public function testVerifyNewRedirectsToSuccessOnProduction()
    {
        // 0) Forcer l’environnement et configurer le frontend_url
        $this->app['env'] = 'production';
        config()->set('app.frontend_url', 'https://frontend.test');

        $user  = User::factory()->create();
        $token = 'sometoken';
        $base  = config('app.frontend_url') . '/verification-result';
        $url   = "/api/auth/email/change/verify?token={$token}&expires=123&signature=abc";

        // 1) Mock verifyNewEmail et getUserCart
        $this->mock(EmailUpdateService::class)
            ->shouldReceive('verifyNewEmail')
            ->with($token)
            ->once()
            ->andReturn($user);

        $this->mock(CartService::class)
            ->shouldReceive('getUserCart')
            ->with($user)
            ->once();

        // 2) Bypass signature validation
        $this->withoutMiddleware(ValidateSignature::class);

        // 3) Appel avec get() pour capter la redirection
        $this->get($url)
            ->assertStatus(302)
            ->assertRedirect("{$base}/success");
    }

    public function testVerifyNewRedirectsToInvalidWhenTokenMissing()
    {
        // 0) Forcer l’environnement et frontend_url
        $this->app['env'] = 'production';
        config()->set('app.frontend_url', 'https://frontend.test');
        $base = config('app.frontend_url') . '/verification-result';

        $token = 'badtoken';
        $url   = "/api/auth/email/change/verify?token={$token}&expires=123&signature=abc";

        // Mock qui lance MissingVerificationTokenException
        $this->mock(EmailUpdateService::class)
            ->shouldReceive('verifyNewEmail')
            ->with($token)
            ->once()
            ->andThrow(new MissingVerificationTokenException());

        // Bypass du middleware de signature
        $this->withoutMiddleware(ValidateSignature::class);

        // On s’attend à une redirection 302 vers /invalid
        $this->get($url)
            ->assertStatus(302)
            ->assertRedirect("{$base}/invalid");
    }

    public function testVerifyNewRedirectsToInvalidWhenRequestNotFound()
    {
        // 0) Forcer l’environnement et frontend_url
        $this->app['env'] = 'production';
        config()->set('app.frontend_url', 'https://frontend.test');
        $base = config('app.frontend_url') . '/verification-result';

        $token = 'badtoken';
        $url   = "/api/auth/email/change/verify?token={$token}&expires=123&signature=abc";

        // Mock qui lance EmailUpdateNotFoundException
        $this->mock(EmailUpdateService::class)
            ->shouldReceive('verifyNewEmail')
            ->with($token)
            ->once()
            ->andThrow(new EmailUpdateNotFoundException());

        // Bypass du middleware de signature
        $this->withoutMiddleware(ValidateSignature::class);

        // On s’attend à une redirection 302 vers /invalid
        $this->get($url)
            ->assertStatus(302)
            ->assertRedirect("{$base}/invalid");
    }

    public function testVerifyNewRedirectsToErrorOnUnexpectedException()
    {
        // 0) Forcer l’environnement en production et définir frontend_url
        $this->app['env'] = 'production';
        config()->set('app.frontend_url', 'https://frontend.test');
        $base = config('app.frontend_url') . '/verification-result';

        $token = 'othertoken';
        $url   = "/api/auth/email/change/verify?token={$token}&expires=123&signature=abc";

        // 1) Mock qui lance une exception inattendue
        $this->mock(EmailUpdateService::class)
            ->shouldReceive('verifyNewEmail')
            ->with($token)
            ->once()
            ->andThrow(new \RuntimeException('boom'));

        // 2) Bypass du middleware ValidateSignature
        $this->withoutMiddleware(ValidateSignature::class);

        // 3) Appel et assertion de redirection /error
        $this->get($url)
            ->assertStatus(302)
            ->assertRedirect("{$base}/error");
    }

    public function testCancelChangeRedirectsToSuccessOnProduction()
    {
        // 0) Forcer l’environnement en production et frontend_url
        $this->app['env'] = 'production';
        config()->set('app.frontend_url', 'https://frontend.test');

        $user  = User::factory()->create();
        $token = 'goodtoken';
        $base  = config('app.frontend_url') . '/verification-result';

        // 1) Mock success (one arg only)
        $this->mock(EmailUpdateService::class)
            ->shouldReceive('cancelEmailUpdate')
            ->with($token)
            ->once()
            ->andReturn($user);

        $this->mock(CartService::class)
            ->shouldReceive('getUserCart')
            ->with($user)
            ->once();

        // 2) Bypass signature validation
        $this->withoutMiddleware(ValidateSignature::class);

        // 3) Hit the actual route (double /auth/, no old email)
        $url = "/api/auth/auth/email/change/cancel/{$token}?expires=123&signature=abc";

        // 4) Call & assert redirect
        $this->get($url)
            ->assertStatus(302)
            ->assertRedirect("{$base}/success");
    }

    public function testCancelChangeRedirectsToInvalidWhenRequestNotFound()
    {
        // 0) Forcer l’environnement en production et frontend_url
        $this->app['env'] = 'production';
        config()->set('app.frontend_url', 'https://frontend.test');

        $token = 'badtoken';
        $base  = config('app.frontend_url') . '/verification-result';

        // 1) Mock non‐trouvé : on ne passe QUE le token
        $this->mock(EmailUpdateService::class)
            ->shouldReceive('cancelEmailUpdate')
            ->with($token)
            ->once()
            ->andThrow(new EmailUpdateNotFoundException());

        // 2) Bypass de la validation signée
        $this->withoutMiddleware(ValidateSignature::class);

        // 3) URL corrigée (double /auth/, plus de {oldEmail})
        $url = "/api/auth/auth/email/change/cancel/{$token}?expires=123&signature=abc";

        // 4) Exécution et assertion de redirection vers /invalid
        $this->get($url)
            ->assertStatus(302)
            ->assertRedirect("{$base}/invalid");
    }

    public function testCancelChangeRedirectsToErrorOnUnexpectedException()
    {
        // 0) Forcer l’environnement en production et configurer frontend_url
        $this->app['env'] = 'production';
        config()->set('app.frontend_url', 'https://frontend.test');

        $token = 'othertoken';
        $base  = config('app.frontend_url') . '/verification-result';

        // 1) Mock qui lance une exception inattendue (un seul argument)
        $this->mock(EmailUpdateService::class)
            ->shouldReceive('cancelEmailUpdate')
            ->with($token)
            ->once()
            ->andThrow(new \RuntimeException('boom'));

        $this->mock(CartService::class)
            ->shouldReceive('getUserCart')
            ->never(); // ne doit pas être appelé en cas d’erreur

        // 2) Bypass du middleware de signature
        $this->withoutMiddleware(ValidateSignature::class);

        // 3) URL corrigée (double /auth/, plus de {oldEmail})
        $url = "/api/auth/auth/email/change/cancel/{$token}?expires=123&signature=abc";

        // 4) Appel et assertion de redirection vers /error
        $this->get($url)
            ->assertStatus(302)
            ->assertRedirect("{$base}/error");
    }
}

