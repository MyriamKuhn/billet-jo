<?php

namespace Tests\Unit\Services\Auth;

use Tests\TestCase;
use App\Models\User;
use App\Services\Auth\RegistrationService;
use App\Services\Auth\CaptchaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

class RegistrationServiceTest extends TestCase
{
    use RefreshDatabase;

    private RegistrationService $service;
    private $captchaMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock du CaptchaService
        $this->captchaMock = \Mockery::mock(CaptchaService::class);
        $this->service     = new RegistrationService($this->captchaMock);
    }

    public function testRegisterCreatesUserAndDispatchesEventInNonProduction()
    {
        // Forcer l'environnement "testing" (non-production)
        $this->app['env'] = 'testing';

        // On ne doit pas appeler verify()
        $this->captchaMock->shouldNotReceive('verify');

        // Fake des events
        Event::fake();

        $data = [
            'firstname'     => 'Alice',
            'lastname'      => 'Durand',
            'email'         => 'alice@example.com',
            'password'      => 'Str0ngP@ssword2025!',
            'captcha_token' => 'ignored',
        ];

        $user = $this->service->register($data);

        // Vérifier la persistance
        $this->assertDatabaseHas('users', [
            'id'        => $user->id,
            'firstname' => 'Alice',
            'lastname'  => 'Durand',
            'email'     => 'alice@example.com',
            'role'      => 'user',
        ]);

        // Password hash correct
        $this->assertTrue(Hash::check($data['password'], $user->password_hash));

        // Event Registered dispatché
        Event::assertDispatched(Registered::class, fn($e) => $e->user->id === $user->id);
    }

    public function testRegisterThrowsHttpResponseExceptionWhenCaptchaFailsInProduction()
    {
        // Forcer l'environnement "production"
        $this->app['env'] = 'production';

        // Le captcha doit être vérifié et échouer
        $this->captchaMock
            ->shouldReceive('verify')
            ->once()
            ->with('bad-token')
            ->andReturnFalse();

        // On intercepte le warning de log
        Log::shouldReceive('warning')
            ->once()
            ->with('Captcha verification failed', ['token' => 'bad-token']);

        $data = [
            'firstname'     => 'Bob',
            'lastname'      => 'Martin',
            'email'         => 'bob@example.com',
            'password'      => 'AnotherP@ssw0rd!',
            'captcha_token' => 'bad-token',
        ];

        $this->expectException(HttpResponseException::class);

        try {
            $this->service->register($data);
        } catch (HttpResponseException $e) {
            $response = $e->getResponse();
            $this->assertSame(422, $response->getStatusCode());
            $payload = $response->getData(true);
            $this->assertSame('Captcha verification failed', $payload['message']);
            $this->assertSame('captcha_failed', $payload['code']);
            throw $e;
        }
    }

    public function testRegisterCreatesUserWhenCaptchaPassesInProduction()
    {
        // Forcer l'environnement "production"
        $this->app['env'] = 'production';

        // Le captcha doit être vérifié et réussir
        $this->captchaMock
            ->shouldReceive('verify')
            ->once()
            ->with('good-token')
            ->andReturnTrue();

        Event::fake();

        $data = [
            'firstname'     => 'Carol',
            'lastname'      => 'Dupont',
            'email'         => 'carol@example.com',
            'password'      => 'Y3tAn0therP@ss!',
            'captcha_token' => 'good-token',
        ];

        $user = $this->service->register($data);

        // Persisté en base
        $this->assertDatabaseHas('users', [
            'id'        => $user->id,
            'firstname' => 'Carol',
            'lastname'  => 'Dupont',
            'email'     => 'carol@example.com',
            'role'      => 'user',
        ]);
        $this->assertTrue(Hash::check($data['password'], $user->password_hash));

        // Event Registered dispatché
        Event::assertDispatched(Registered::class, fn($e) => $e->user->id === $user->id);
    }
}
