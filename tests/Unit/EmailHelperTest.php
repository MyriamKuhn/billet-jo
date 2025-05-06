<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\Config;
use App\Helpers\EmailHelper;

class EmailHelperTest extends TestCase
{
    public function testHashTokenReturnsHmacSha256UsingAppKey()
    {
        // Arrange: set a known app key
        Config::set('app.key', 'my-secret-key');
        $raw = 'test-token';

        // Act
        $hashed = EmailHelper::hashToken($raw);

        // Assert
        $expected = hash_hmac('sha256', $raw, 'my-secret-key');
        $this->assertEquals($expected, $hashed);
    }

    public function testVerifyTokenReturnsTrueForValidPair()
    {
        Config::set('app.key', 'another-key');
        $raw  = 'another-test';
        $hash = EmailHelper::hashToken($raw);

        $this->assertTrue(EmailHelper::verifyToken($raw, $hash));
    }

    public function testVerifyTokenReturnsFalseForInvalidPair()
    {
        Config::set('app.key', 'another-key');
        $raw  = 'another-test';
        $hash = EmailHelper::hashToken($raw);

        $this->assertFalse(EmailHelper::verifyToken($raw . 'x', $hash));
    }

    public function testMakeTokenPairGeneratesCorrectLengthAndValidHash()
    {
        Config::set('app.key', 'key123');
        $length = 32;

        [$raw, $hash] = EmailHelper::makeTokenPair($length);

        // Raw token length
        $this->assertIsString($raw);
        $this->assertEquals($length, strlen($raw));

        // Hash matches raw
        $this->assertIsString($hash);
        $this->assertTrue(EmailHelper::verifyToken($raw, $hash));
    }

    public function testMakeTokenPairDefaultLengthIs60()
    {
        Config::set('app.key', 'key123');

        [$raw, $hash] = EmailHelper::makeTokenPair();

        $this->assertEquals(60, strlen($raw));
        $this->assertTrue(EmailHelper::verifyToken($raw, $hash));
    }
}
