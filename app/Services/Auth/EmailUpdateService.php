<?php

namespace App\Services\Auth;

use App\Models\EmailUpdate;
use App\Models\User;
use App\Helpers\EmailHelper;
use App\Exceptions\Auth\MissingVerificationTokenException;
use App\Exceptions\Auth\EmailUpdateNotFoundException;

/**
 * EmailUpdateService handles the verification and cancellation of email updates.
 * It checks the provided token, updates the user's email, and manages pending email changes.
 */
class EmailUpdateService
{
    /**
     * Verify the token and apply the email update.
     *
     * @param  string|null  $rawToken
     * @return User
     *
     * @throws MissingVerificationTokenException
     * @throws EmailUpdateNotFoundException
     */
    public function verifyNewEmail(?string $rawToken): User
    {
        // Ensure a token was provided
        if (! $rawToken) {
            throw new MissingVerificationTokenException();
        }

        // Hash the raw token for lookup
        $hashed = EmailHelper::hashToken($rawToken);

        // Find the pending email update by hashed token
        $emailUpdate = EmailUpdate::where('token', $hashed)->first();
        if (! $emailUpdate) {
            throw new EmailUpdateNotFoundException();
        }

        /** @var \App\Models\User $user */
        $user = User::findOrFail($emailUpdate->user_id);
        // Apply the new email and mark it as verified
        $user->email = $emailUpdate->new_email;
        $user->email_verified_at = now();
        $user->save();

        // Revoke all existing tokens for security
        $user->tokens()->delete();

        // Update the timestamp on the email update record
        $emailUpdate->touch();

        return $user;
    }

    /**
     * Cancel a pending email change and restore the old email.
     *
     * @param  string  $rawToken
     * @return User
     *
     * @throws EmailUpdateNotFoundException
     */
    public function cancelEmailUpdate(string $rawToken): User
    {
        // Hash the raw token for lookup
        $hashed = EmailHelper::hashToken($rawToken);

        // Find the pending email update by hashed token
        $emailUpdate = EmailUpdate::where('token', $hashed)->first();

        if (! $emailUpdate) {
            throw new EmailUpdateNotFoundException();
        }

        /** @var \App\Models\User $user */
        $user = User::findOrFail($emailUpdate->user_id);
        // Restore the old email
        $user->email = $emailUpdate->old_email;
        $user->save();

        // Revoke all existing tokens for security
        $user->tokens()->delete();

        // Remove the pending email update record
        $emailUpdate->delete();

        return $user;
    }
}
