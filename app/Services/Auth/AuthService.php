<?php
namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Auth\PasswordBroker;
use PragmaRX\Google2FA\Google2FA;
use App\Notifications\VerifyNewEmailNotification;
use App\Notifications\EmailUpdatedNotification;
use Illuminate\Support\Facades\Notification;
use App\Models\EmailUpdate;
use App\Helpers\EmailHelper;
use App\Services\CartService;
use Psr\Log\LoggerInterface;
use App\Services\Auth\TwoFactorService;

/**
 * AuthService handles user authentication, including login, logout,
 * password reset, and email change requests.
 */
class AuthService
{
    public function __construct(
        private Google2FA $google2fa,
        private PasswordBroker $passwordBroker,
        private CartService $cartService,
        private LoggerInterface $logger,
        private TwoFactorService $twoFactorService
    ) {}

    /**
     * Authenticate a user and return token + profile.
     *
     * Steps:
     * 1. Validate credentials
     * 2. Check account is active
     * 3. Ensure email is verified
     * 4. Verify 2FA code if enabled
     * 5. Generate API token (with optional “remember me”)
     * 6. Merge any guest cart into user cart
     * 7. Return success payload
     *
     * @param  array  $data
     * @return array
     * @throws HttpResponseException on failure
     */
    public function login(array $data): array
    {
        // 1. Credentials
        $user = User::where('email', $data['email'])->first();
        if (!$user || !Hash::check($data['password'], $user->password_hash)) {
            throw new HttpResponseException(response()->json([
                'message' => 'Invalid credentials',
                'code'    => 'invalid_credentials',
            ], 401));
        }

        // 2. Account status
        if (! $user->is_active) {
            throw new HttpResponseException(response()->json([
                'message' => 'Account disabled',
                'code'    => 'account_disabled',
            ], 403));
        }

        // 3. Email verification
        if (! $user->hasVerifiedEmail()) {
            // New email sending if necessary
            $user->sendEmailVerificationNotification();

            throw new HttpResponseException(response()->json([
                'message' => 'Email address not verified',
                'code'    => 'email_not_verified',
                'resend_verification_url'    => url('/api/auth/email/resend'),
            ], 400));
        }

        // 4. Two-factor
        if ($user->twofa_enabled) {
            if (empty($data['twofa_code'])) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Two-factor authentication code is required',
                    'code'    => 'twofa_required',
                ], 400));
            }

            if (! $this->twoFactorService->verifyOtp($user, $data['twofa_code'])) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Invalid two-factor authentication code',
                    'code'    => 'twofa_invalid',
                ], 400));
            }
        }

        // 5. Token Generation (with optional remember me)
        $token = $user->createToken(
            'auth_token',
            ['*'],
            $data['remember'] ? now()->addWeeks(1) : null
        )->plainTextToken;

        // 6. Merge guest cart into user cart
        try {
            $this->cartService->mergeGuestIntoUser($user->id);
        } catch (\Throwable $e) {
            // Log without throwing an exception
            $this->logger->warning('Failed to merge guest cart on login', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }

        // 7. Success response
        return [
            'message' => 'Logged in successfully',
            'token'   => $token,
            'user'     => [
                'id'           => $user->id,
                'firstname'    => $user->firstname,
                'lastname'     => $user->lastname,
                'email'        => $user->email,
                'role'         => $user->role,
                'twofa_enabled'=> $user->twofa_enabled,
            ],
        ];
    }

    /**
     * Logout the given user by revoking their current token.
     *
     * @param  User  $user
     * @return array{message: string}
     * @throws HttpResponseException if no active token
     */
    public function logout(User $user): array
    {
        $token = $user->currentAccessToken();

        if (! $token || $token instanceof TransientToken) {
            throw new HttpResponseException(response()->json([
                'message' => 'No active token found',
                'code'    => 'no_active_token',
            ], 400));
        }

        $token->delete();

        return ['message' => 'Logged out successfully'];
    }

    /**
     * Send a password reset link to the given email.
     *
     * @param  string  $email
     * @return array{message: string}
     * @throws HttpResponseException on failure
     */
    public function sendResetLink(string $email): array
    {
        $user = User::where('email', $email)->first();

        $status = $this->passwordBroker->sendResetLink(['email' => $email]);

        if ($status !== PasswordBroker::RESET_LINK_SENT) {
            throw new HttpResponseException(response()->json([
                'message' => 'Unable to send reset link',
                'code'    => 'reset_link_failed',
            ], 500));
        }

        return ['message' => 'Password reset link sent'];
    }

    /**
     * Reset the user’s password using broker.
     *
     * @param  array{token:string,email:string,password:string,password_confirmation:string}  $data
     * @return array{message:string}
     * @throws HttpResponseException on invalid token/user or other error
     */
    public function resetPassword(array $data): array
    {
        $response = $this->passwordBroker->reset(
            $data,
            function ($user, $password) {
                $user->password_hash = bcrypt($password);
                $user->save();
            }
        );

        return match ($response) {
            PasswordBroker::PASSWORD_RESET => ['message' => 'Password has been reset successfully'],
            PasswordBroker::INVALID_TOKEN => throw new HttpResponseException(response()->json([
                'message' => 'Invalid password reset token',
                'code'    => 'invalid_token',
            ], 400)),
            PasswordBroker::INVALID_USER  => throw new HttpResponseException(response()->json([
                'message' => 'No user found with this email',
                'code'    => 'user_not_found',
            ], 404)),
            default => throw new HttpResponseException(response()->json([
                'message' => 'Unexpected error during password reset',
                'code'    => 'internal_error',
            ], 500)),
        };
    }

    /**
     * Change the authenticated user’s password.
     *
     * 1. Verify current password
     * 2. Save new password
     *
     * @param  User  $user
     * @param  array  $data  ['current_password','password','password_confirmation']
     * @return array{message:string}
     * @throws HttpResponseException if current password is wrong
     */
    public function updatePassword(User $user, array $data): array
    {
        // 1. Check the current password
        if (! Hash::check($data['current_password'], $user->password_hash)) {
            throw new HttpResponseException(response()->json([
                'message' => 'Current password is incorrect',
                'code'    => 'invalid_current_password',
            ], 400));
        }

        // 2. Update the password
        $user->password_hash = Hash::make($data['password']);
        $user->save();

        return ['message' => 'Password changed successfully'];
    }

    /**
     * Initiate an email change for the given user.
     *
     * Steps:
     * 1. Generate a raw/hashed token pair
     * 2. Store the hashed token along with old & new email
     * 3. Send verification email to the new address
     * 4. Notify the old address with cancellation link
     *
     * @param  User    $user
     * @param  string  $newEmail
     * @return array{message:string}
     */
    public function updateEmail(User $user, string $newEmail): array
    {
        $oldEmail = $user->email;

        // 1. Generate a token pair
        [$rawToken, $hashedToken] = EmailHelper::makeTokenPair();

        // 2. Store the hash in the database
        EmailUpdate::updateOrCreate(
            ['user_id' => $user->id],
            [
                'old_email' => $oldEmail,
                'new_email' => $newEmail,
                'token'     => $hashedToken,
            ]
        );

        // 3. Send the verification email with raw token
        Notification::route('mail', $newEmail)
            ->notify(new VerifyNewEmailNotification($user, $rawToken));

        // 4. Send the notification to the old email
        $user->notify(new EmailUpdatedNotification(
            $newEmail,
            $oldEmail,
            $rawToken
        ));

        return ['message' => 'Email change request sent'];
    }
}
