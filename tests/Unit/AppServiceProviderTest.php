<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Http\Client\Factory as HttpClient;
use App\Services\Auth\CaptchaService;
use ReflectionClass;

class AppServiceProviderTest extends TestCase
{
    public function testCaptchaServiceIsRegisteredWithConfigValues()
    {
        // 1) On définit la config comme on veut
        $fakeSecret = 'my-dummy-secret';
        $fakeUrl    = 'https://verify.test/endpoint';
        Config::set('services.recaptcha.secret', $fakeSecret);
        Config::set('services.recaptcha.site_verify_url', $fakeUrl);

        // 2) On résout le service depuis le container
        /** @var CaptchaService $service */
        $service = $this->app->make(CaptchaService::class);

        // 3) Vérifie le type
        $this->assertInstanceOf(CaptchaService::class, $service);

        // 4) Grâce à la réflexion, on lit les propriétés protégées
        $ref       = new ReflectionClass($service);
        $propSecret = $ref->getProperty('secret');
        $propSecret->setAccessible(true);
        $propUrl    = $ref->getProperty('verifyUrl');
        $propUrl->setAccessible(true);
        $propHttp   = $ref->getProperty('http');
        $propHttp->setAccessible(true);

        // 5) Assertions sur les valeurs
        $this->assertEquals($fakeSecret, $propSecret->getValue($service));
        $this->assertEquals($fakeUrl,    $propUrl->getValue($service));

        // 6) S’assurer qu’un HttpClient a bien été injecté
        $this->assertInstanceOf(HttpClient::class, $propHttp->getValue($service));
    }
}

