<?php

namespace Tests\Unit\Services\Auth;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Factory as HttpClient;
use App\Services\Auth\CaptchaService;
use Mockery;

class CaptchaServiceTest extends TestCase
{
    use RefreshDatabase;

    private HttpClient $httpClient;

    protected function setUp(): void
    {
        parent::setUp();

        // Récupère le client HTTP sous-jacent à la façade Http
        $this->httpClient = Http::getFacadeRoot();

        // Réinitialise la façade Log pour permettre les mocks
        Log::swap($this->app->make('log'));
    }

    public function testVerifyReturnsFalseAndLogsErrorWhenSecretNotConfigured()
    {
        Log::shouldReceive('error')
            ->once()
            ->with('reCAPTCHA secret key is not configured');

        Http::fake();

        $service = new CaptchaService(
            '',
            'https://captcha.test/verify',
            $this->httpClient
        );

        $this->assertFalse($service->verify('any-token'));

        Http::assertNothingSent();
    }

    public function testVerifyReturnsFalseAndLogsWarningWhenValidationFailed()
    {
        $payload = ['success' => false, 'score' => 0.3];

        Log::shouldReceive('warning')
            ->once()
            ->with('reCAPTCHA validation failed', $payload);

        Http::fake([
            'https://captcha.test/verify' => Http::response($payload, 200),
        ]);

        $service = new CaptchaService(
            'SECRET',
            'https://captcha.test/verify',
            $this->httpClient
        );

        $this->assertFalse($service->verify('token123'));
    }

    public function testVerifyReturnsTrueWhenSuccess()
    {
        $payload = ['success' => true];

        Http::fake([
            'https://captcha.test/verify' => Http::response($payload, 200),
        ]);

        $service = new CaptchaService(
            'SECRET',
            'https://captcha.test/verify',
            $this->httpClient
        );

        $this->assertTrue($service->verify('valid-token'));
    }

    public function testVerifyReturnsFalseAndLogsErrorOnException()
    {
        Log::shouldReceive('error')
            ->once()
            ->with(
                'Error during reCAPTCHA verification',
                Mockery::on(fn($ctx) => $ctx['exception'] instanceof \Throwable)
            );

        Http::fake([
            'https://captcha.test/verify' => function () {
                throw new \Exception('boom');
            },
        ]);

        $service = new CaptchaService(
            'SECRET',
            'https://captcha.test/verify',
            $this->httpClient
        );

        $this->assertFalse($service->verify('tokenX'));
    }

    public function testVerifyReturnsFalseOnHttpError()
    {
        $url = 'https://captcha.test/verify';

        Http::fake([
            $url => Http::response('error-body', 500),
        ]);

        $service = new CaptchaService(
            'SECRET',
            $url,
            $this->httpClient
        );

        $this->assertFalse($service->verify('token123'));
    }

    public function testVerifyReturnsFalseWhenHttpResponseIsNotSuccessful()
    {
        Http::fake(fn() => Http::response('I\'m a teapot', 418));

        $service = new CaptchaService(
            'dummy-secret',
            'https://any-url.test/verify',
            $this->httpClient
        );

        $this->assertFalse(
            $service->verify('any-token'),
            'Expected verify() to return false when the HTTP response is not successful'
        );
    }

    public function testVerifyReturnsFalseOnHttpErrorWithInjectedHttpClient()
    {
        // 1) On mocke la réponse HTTP pour qu’elle soit « non successful »
        $fakeResponse = Mockery::mock(\Illuminate\Http\Client\Response::class);
        $fakeResponse->shouldReceive('successful')->once()->andReturn(false);
        // Comme on loggue encore le warning, on doit stubber status() et body()
        $fakeResponse->shouldReceive('status')->once()->andReturn(500);
        $fakeResponse->shouldReceive('body')->once()->andReturn('error-body');

        // 2) On mocke le HttpClient pour renvoyer notre fakeResponse
        $mockHttp = Mockery::mock(\Illuminate\Http\Client\Factory::class);
        $mockHttp->shouldReceive('retry')->once()->with(3, 100)->andReturnSelf();
        $mockHttp->shouldReceive('asForm')->once()->andReturnSelf();
        $mockHttp->shouldReceive('post')->once()->andReturn($fakeResponse);

        // 3) Instanciation du service avec le client injecté
        $service = new CaptchaService('dummy-secret', 'any-url', $mockHttp);

        // 4) L’appel doit renvoyer false en passant par la branche HTTP-error
        $this->assertFalse($service->verify('any-token'));
    }
}
