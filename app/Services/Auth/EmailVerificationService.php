<?php
namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use App\Exceptions\Auth\UserNotFoundException;
use App\Exceptions\Auth\InvalidVerificationLinkException;
use App\Exceptions\Auth\AlreadyVerifiedException;
use Illuminate\Http\Exceptions\HttpResponseException;

class EmailVerificationService
{
    /**
     * Verify and mark the user's email as verified.
     *
     * @param  int    $userId
     * @param  string $hash
     * @return User
     *
     * @throws UserNotFoundException
     * @throws InvalidVerificationLinkException
     * @throws AlreadyVerifiedException
     */
    public function verify(int $userId, string $hash): User
    {
        /** @var \App\Models\User $user */
        $user = User::find($userId);
        if (! $user) {
            throw new UserNotFoundException();
        }

        $expected = sha1($user->getEmailForVerification());
        if (! hash_equals($expected, $hash)) {
            throw new InvalidVerificationLinkException();
        }

        if ($user->hasVerifiedEmail()) {
            throw new AlreadyVerifiedException();
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        return $user;
    }

    /**
     * Resend the email verification link.
     *
     * @param  string  $email
     * @return array{message:string}
     * @throws HttpResponseException  If the email is already verified
     */
    public function resend(string $email): array
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            throw new UserNotFoundException();
        }

        if ($user->hasVerifiedEmail()) {
            throw new AlreadyVerifiedException();
        }

        $user->sendEmailVerificationNotification();

        return ['message' => 'Verification email resent'];
    }
}
