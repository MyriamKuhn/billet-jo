<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\CaptchaService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\App;

class CaptchaServiceTest extends TestCase
{
    public function testItReturnsTrueWhenCaptchaIsSuccessful()
    {
        // Fake the HTTP response to simulate a successful captcha verification
        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true]),
        ]);

        $service = new CaptchaService();
        $token = Str::random(32); // Simulate a valid token

        $this->assertTrue($service->verify($token));
    }

    public function testItReturnsFalseWhenCaptchaFails()
    {
        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']]),
        ]);

        Log::shouldReceive('warning')->once(); // Expect warning to be logged

        $service = new CaptchaService();
        $token = Str::random(32);

        $this->assertFalse($service->verify($token));
    }

    public function testItReturnsFalseAndLogsErrorWhenExceptionIsThrown()
    {
        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => function () {
                throw new \Exception('HTTP failure');
            },
        ]);

        Log::shouldReceive('error')->once(); // Expect error to be logged

        $service = new CaptchaService();
        $token = Str::random(32);

        $this->assertFalse($service->verify($token));
    }
}
