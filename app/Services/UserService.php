<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use App\Models\EmailUpdate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Exceptions\HttpResponseException;

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
            $target->is_active = (bool) $data['is_active'];
        }

        // Gestion de la 2FA
        if (array_key_exists('twofa_enabled', $data)) {
            $wantEnable = (bool) $data['twofa_enabled'];
            if (! $wantEnable && $target->twofa_enabled) {
                // ADMIN FORCE DISABLE 2FA
                // Nettoyer tous les champs 2FA
                $target->twofa_enabled = false;
                $target->twofa_secret  = null;
                $target->twofa_recovery_codes = null;
                // S’il existe des champs temporaires
                if (isset($target->twofa_secret_temp)) {
                    $target->twofa_secret_temp = null;
                }
                if (isset($target->twofa_temp_expires_at)) {
                    $target->twofa_temp_expires_at = null;
                }
                // Vous pouvez journaliser :
                // \Log::info("2FA disabled by admin for user {$target->id}");
            } elseif ($wantEnable) {
                // ADMIN ESSAIE D’ACTIVER 2FA
                // Décider de la politique : ici, on interdit l’activation directe
                // On peut soit ignorer silencieusement, soit lever une exception.
                throw new HttpResponseException(response()->json([
                    'message' => 'Cannot enable two-factor authentication directly; user must enable it via their own account.',
                    'code'    => 'twofa_enable_not_allowed_admin',
                ], 400));
            }
        }

        // Vérification de l’email
        if (array_key_exists('verify_email', $data) && $data['verify_email']) {
            if (! $target->hasVerifiedEmail()) {
                $target->markEmailAsVerified();
            }
        }

        // Autres champs modifiables par admin
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
