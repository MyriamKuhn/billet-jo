<?php

namespace App\Enums;

/**
 * Defines all possible roles a user can have in the system,
 * along with helper methods to check role membership.
 */
enum UserRole: string
{
    /**
     * A user with full administrative privileges.
     */
    case Admin = 'admin';
    /**
     * A user who is an employee with elevated access,
     * but not full admin rights.
     */
    case Employee = 'employee';
    /**
     * A standard end‑user with no special privileges.
     */
    case User = 'user';

    /**
     * Check if this role is Administrator.
     *
     * @return bool  True if the role is Admin, false otherwise.
     */
    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }

    /**
     * Check if this role is Employee.
     *
     * @return bool  True if the role is Employee, false otherwise.
     */
    public function isEmployee(): bool
    {
        return $this === self::Employee;
    }

    /**
     * Check if this role is a regular User.
     *
     * @return bool  True if the role is User, false otherwise.
     */
    public function isUser(): bool
    {
        return $this === self::User;
    }
}
