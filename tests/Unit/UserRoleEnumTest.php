<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use PHPUnit\Framework\TestCase;

class UserRoleEnumTest extends TestCase
{
    public function testIsAdmin()
    {
        $this->assertTrue(UserRole::Admin->isAdmin());
        $this->assertFalse(UserRole::Employee->isAdmin());
        $this->assertFalse(UserRole::User->isAdmin());
    }

    public function testIsEmployee()
    {
        $this->assertTrue(UserRole::Employee->isEmployee());
        $this->assertFalse(UserRole::Admin->isEmployee());
        $this->assertFalse(UserRole::User->isEmployee());
    }

    public function testIsUser()
    {
        $this->assertTrue(UserRole::User->isUser());
        $this->assertFalse(UserRole::Admin->isUser());
        $this->assertFalse(UserRole::Employee->isUser());
    }
}

