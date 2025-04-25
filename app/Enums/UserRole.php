<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Employee = 'employee';
    case User = 'user';

    /**
     * Determine if the user is an admin.
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }

    /**
     * Determine if the user is an employee.
     *
     * @return bool
     */
    public function isEmployee(): bool
    {
        return $this === self::Employee;
    }

    /**
     * Determine if the user is a regular user.
     *
     * @return bool
     */
    public function isUser(): bool
    {
        return $this === self::User;
    }
}
