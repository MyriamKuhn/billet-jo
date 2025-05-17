<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use App\Models\EmailUpdate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Pagination\LengthAwarePaginator;

class UserService
{
    /**
     * Return all users for an administrator filtered and paginated.
     *
     * @param  User  $actor  The currently authenticated user
     * @return LengthAwarePaginator
     * @throws AuthorizationException  If the actor is not an admin
     */
    public function listAllUsers(User $actor, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        if (! $actor->role->isAdmin()) {
            throw new AuthorizationException();
        }

        $query = User::select([
            'id','firstname','lastname','email',
            'created_at','updated_at','role',
            'twofa_enabled','email_verified_at','is_active',
        ]);

        if (! empty($filters['firstname'])) {
            $query->where('firstname', 'like', '%'.$filters['firstname'].'%');
        }
        if (! empty($filters['lastname'])) {
            $query->where('lastname', 'like', '%'.$filters['lastname'].'%');
        }
        if (! empty($filters['email'])) {
            $query->where('email', $filters['email']);
        }
        if (! empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        return $query->paginate($perPage)
                    ->appends(request()->only(['page','per_page','firstname','lastname','email','role']));
    }

    /**
     * Return the basic info of a target user, if the actor is admin or employee.
     *
     * @param  User  $actor   The currently authenticated user
     * @param  User  $target  The target user
     * @return array{firstname:string,lastname:string,email:string}
     *
     * @throws AuthorizationException  If the actor is not an admin or employee
     */
    public function getUserInfo(User $actor, User $target): array
    {
        if (! ($actor->role->isAdmin() || $actor->role->isEmployee())) {
            throw new AuthorizationException();
        }

        return [
            'firstname' => $target->firstname,
            'lastname'  => $target->lastname,
            'email'     => $target->email,
        ];
    }

    /**
     * Update the given user’s firstname and lastname.
     *
     * @param  User   $user
     * @param  array  $data  ['firstname','lastname']
     * @return array{firstname:string,lastname:string}
     *
     * @throws AuthenticationException If somehow the user is not authenticated
     */
    public function updateName(User $user, array $data): array
    {
        $user->update([
            'firstname' => $data['firstname'],
            'lastname'  => $data['lastname'],
        ]);

        return [
            'firstname' => $user->firstname,
            'lastname'  => $user->lastname,
        ];
    }

    /**
     * Update a target user’s attributes (admin only).
     *
     * @param  User   $actor   the currently authenticated user
     * @param  User   $target  the target user
     * @param  array  $data    Validated data from the request
     * @return array           Updated datas
     *
     * @throws AuthorizationException If the actor is not an admin
     */
    public function updateUserByAdmin(User $actor, User $target, array $data): array
    {
        if (! $actor->role->isAdmin()) {
            throw new AuthorizationException();
        }

        // Appliquer les changements
        if (array_key_exists('is_active', $data)) {
            $target->is_active = $data['is_active'];
        }

        if (array_key_exists('twofa_enabled', $data)) {
            // Si on désactive 2FA, on réinitialise le secret
            $target->twofa_enabled = $data['twofa_enabled'];
            if (! $data['twofa_enabled']) {
                $target->twofa_secret = null;
            }
        }

        if (array_key_exists('verify_email', $data) && $data['verify_email']) {
            $target->markEmailAsVerified();
        }

        foreach (['firstname','lastname','email','role'] as $field) {
            if (array_key_exists($field, $data)) {
                $target->$field = $data[$field];
            }
        }

        $target->save();

        return [
            'firstname'     => $target->firstname,
            'lastname'      => $target->lastname,
            'email'         => $target->email,
            'is_active'     => $target->is_active,
            'twofa_enabled' => $target->twofa_enabled,
            'role'          => $target->role,
            'email_verified_at' => $target->email_verified_at,
        ];
    }

    /**
     * Return the update email request for a user, or null if none.
     *
     * @param  User  $actor   The currently authenticated user
     * @param  User  $target  The target user
     * @return array<string,mixed>|null
     *
     * @throws AuthorizationException  If the actor is not an admin
     */
    public function checkEmailUpdate(User $actor, User $target): ?array
    {
        if (!$actor->role->isAdmin()) {
            throw new AuthorizationException();
        }

        $emailUpdate = EmailUpdate::where('user_id', $target->id)->first();
        if (! $emailUpdate) {
            return null;
        }

        return [
            'old_email'   => $emailUpdate->old_email,
            'new_email'   => $emailUpdate->new_email,
            'created_at'  => $emailUpdate->created_at,
            'updated_at'  => $emailUpdate->updated_at,
        ];
    }

    /**
     * Create a new employee user (admin only).
     *
     * @param  User   $actor  the currently authenticated user
     * @param  array  $data   Validated data from the request
     * @return User
     *
     * @throws AuthorizationException  If the actor is not an admin
     */
    public function createEmployee(User $actor, array $data): User
    {
        if (! $actor->role->isAdmin()) {
            throw new AuthorizationException();
        }

        return User::create([
            'firstname'        => $data['firstname'],
            'lastname'         => $data['lastname'],
            'email'            => $data['email'],
            'password_hash'    => Hash::make($data['password']),
            'role'             => 'employee',
            'is_active'        => true,
            'email_verified_at'=> now(),
        ]);
    }

    /**
     * Return the authenticated user's basic profile.
     *
     * @param  User  $user
     * @return array{firstname:string,lastname:string,email:string,twofa_enabled:bool}
     */
    public function getSelfInfo(User $user): array
    {
        return [
            'firstname'     => $user->firstname,
            'lastname'      => $user->lastname,
            'email'         => $user->email,
            'twofa_enabled' => $user->twofa_enabled,
        ];
    }
}
