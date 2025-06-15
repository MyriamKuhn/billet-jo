<?php

namespace App\Services\Auth;

use App\Models\EmailUpdate;
use App\Models\User;
use App\Helpers\EmailHelper;
use App\Exceptions\Auth\MissingVerificationTokenException;
use App\Exceptions\Auth\EmailUpdateNotFoundException;

class EmailUpdateService
{
    /**
     * Vérifie le token et applique la mise à jour de l'email.
     *
     * @param  string|null  $rawToken
     * @return User
     *
     * @throws MissingVerificationTokenException
     * @throws EmailUpdateNotFoundException
     */
    public function verifyNewEmail(?string $rawToken): User
    {
        if (! $rawToken) {
            throw new MissingVerificationTokenException();
        }

        $hashed = EmailHelper::hashToken($rawToken);

        $emailUpdate = EmailUpdate::where('token', $hashed)->first();
        if (! $emailUpdate) {
            throw new EmailUpdateNotFoundException();
        }

        /** @var \App\Models\User $user */
        $user = User::findOrFail($emailUpdate->user_id);
        $user->email = $emailUpdate->new_email;
        $user->email_verified_at = now();
        $user->save();

        $user->tokens()->delete();

        // Record validated email update
        $emailUpdate->touch();

        return $user;
    }

    /**
     * Cancel a pending email change, restoring the old email.
     *
     * @param  string  $rawToken
     * @param  string  $oldEmail
     * @return User
     *
     * @throws EmailUpdateNotFoundException
     */
    public function cancelEmailUpdate(string $rawToken): User
    {
        $hashed = EmailHelper::hashToken($rawToken);

        $emailUpdate = EmailUpdate::where('token', $hashed)->first();

        if (! $emailUpdate) {
            throw new EmailUpdateNotFoundException();
        }

        /** @var \App\Models\User $user */
        $user = User::findOrFail($emailUpdate->user_id);
        // Restore old email and mark as verified
        $user->email = $emailUpdate->old_email;
        $user->save();

        $user->tokens()->delete();

        // Remove the pending update
        $emailUpdate->delete();

        return $user;
    }
}
