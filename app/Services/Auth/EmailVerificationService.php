<?php
namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use App\Exceptions\Auth\UserNotFoundException;
use App\Exceptions\Auth\InvalidVerificationLinkException;
use App\Exceptions\Auth\AlreadyVerifiedException;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Class EmailVerificationService
 *
 * Handles email verification logic for users.
 */
class EmailVerificationService
{
    /**
     * Verify and mark the user's email as verified.
     *
     * @param  int    $userId  The ID of the user to verify
     * @param  string $hash    The SHA1 hash from the verification link
     * @return User            The verified user model
     *
     * @throws UserNotFoundException               If no user with given ID exists
     * @throws InvalidVerificationLinkException    If the hash does not match
     * @throws AlreadyVerifiedException            If the user is already verified
     */
    public function verify(int $userId, string $hash): User
    {
        // Attempt to retrieve the user
        /** @var \App\Models\User $user */
        $user = User::find($userId);
        if (! $user) {
            throw new UserNotFoundException();
        }

        // Check that the hash matches the expected SHA1 of their email
        $expected = sha1($user->getEmailForVerification());
        if (! hash_equals($expected, $hash)) {
            throw new InvalidVerificationLinkException();
        }

        // Prevent double-verification
        if ($user->hasVerifiedEmail()) {
            throw new AlreadyVerifiedException();
        }

        // Mark as verified and fire the framework event
        $user->markEmailAsVerified();
        event(new Verified($user));

        return $user;
    }

    /**
     * Resend the email verification link.
     *
     * @param  string  $email The user’s email address
     * @return array{message:string}
     *
     * @throws UserNotFoundException   If no user with that email exists
     * @throws AlreadyVerifiedException If the user has already verified their email
     */
    public function resend(string $email): array
    {
        // Find the user by email
        $user = User::where('email', $email)->first();

        if (! $user) {
            throw new UserNotFoundException();
        }

        // Do not resend if already verified
        if ($user->hasVerifiedEmail()) {
            throw new AlreadyVerifiedException();
        }

        // Send the default Laravel email verification notification
        $user->sendEmailVerificationNotification();

        return ['message' => 'Verification email resent'];
    }
}
