<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use PragmaRX\Google2FA\Google2FA;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Service pour gérer l'activation/désactivation de la 2FA (authentification à deux facteurs)
 * avec Google2FA.
 */
class TwoFactorService
{
    public function __construct(private Google2FA $google2fa) {}

    /**
     * Prepare two-factor authentication setup:
     * - Generate a temporary secret
     * - Store it in `twofa_secret_temp` with an expiry timestamp
     * - Return the secret and OTP‑Auth URL for QR code generation
     *
     * @param  User  $user
     * @return array{secret: string, qrCodeUrl: string, expires_at: string}
     * @throws HttpResponseException  If 2FA is already enabled
     */
    public function prepareEnable(User $user): array
    {
        if ($user->twofa_enabled) {
            throw new HttpResponseException(response()->json([
                'message' => 'Two-factor authentication is already enabled',
                'code'    => 'twofa_already_enabled',
            ], 400));
        }

        // Generate a new secret key
        $secret = $this->google2fa->generateSecretKey();

        // Store it temporarily on the user with a 10‑minute TTL
        $user->twofa_secret_temp = $secret;
        $user->twofa_temp_expires_at = Carbon::now()->addMinutes(10);
        $user->save();

        // Build the OTP-Auth URL for QR code scanning
        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        return [
            'secret'    => $secret,
            'qrCodeUrl' => $qrCodeUrl,
            'expires_at'=> $user->twofa_temp_expires_at->toISOString(), // optionnel pour le frontend
        ];
    }

    /**
     * Confirm two-factor authentication setup:
     * - Verify the provided OTP against the temporary secret
     * - Activate 2FA and generate recovery codes
     *
     * @param  User    $user
     * @param  string  $otp
     * @return array{recovery_codes: string[]}
     * @throws HttpResponseException  On invalid or expired setup, or if already enabled
     */
    public function confirmEnable(User $user, string $otp): array
    {
        if ($user->twofa_enabled) {
            throw new HttpResponseException(response()->json([
                'message' => 'Two-factor authentication is already enabled',
                'code'    => 'twofa_already_enabled',
            ], 400));
        }

        // Ensure a temporary secret exists and has not expired
        if (empty($user->twofa_secret_temp) || empty($user->twofa_temp_expires_at)
            || now()->greaterThan($user->twofa_temp_expires_at)) {
            throw new HttpResponseException(response()->json([
                'message' => 'No 2FA setup in progress or expired',
                'code'    => 'twofa_no_setup_in_progress_or_expired',
            ], 400));
        }

        $secret = $user->twofa_secret_temp;

        // Check the OTP code
        if (! $this->google2fa->verifyKey($secret, $otp)) {
            throw new HttpResponseException(response()->json([
                'message' => 'Invalid OTP code',
                'code'    => 'twofa_invalid_otp',
            ], 400));
        }

        // Activate 2FA permanently
        $user->twofa_secret = $secret;
        $user->twofa_enabled = true;
        $user->twofa_secret_temp = null;
        $user->twofa_temp_expires_at = null;

        // Generate and store hashed recovery codes
        $plainCodes = [];
        $hashedCodes = [];
        for ($i = 0; $i < 8; $i++) {
            $code = Str::upper(Str::random(10));
            $plainCodes[] = $code;
            $hashedCodes[] = Hash::make($code);
        }
        $user->twofa_recovery_codes = $hashedCodes;

        $user->save();

        // Return plain recovery codes for the user to save
        return [
            'recovery_codes' => $plainCodes,
        ];
    }

    /**
     * Disable two-factor authentication:
     * - Accept either a current OTP or one of the recovery codes
     * - Consume the recovery code if used
     * - Remove all 2FA data from the user
     *
     * @param  User    $user
     * @param  string  $code  OTP or recovery code
     * @return void
     * @throws HttpResponseException  On invalid code or if 2FA not enabled
     */
    public function disableTwoFactor(User $user, string $code): void
    {
        if (! $user->twofa_enabled) {
            throw new HttpResponseException(response()->json([
                'message' => 'Two-factor authentication is not enabled',
                'code'    => 'twofa_not_enabled',
            ], 400));
        }

        $valid = false;

        // First try verifying against the current secret
        if (! empty($user->twofa_secret) && $this->google2fa->verifyKey($user->twofa_secret, $code)) {
            $valid = true;
        } else {
            // Otherwise, check recovery codes
            $hashes = $user->twofa_recovery_codes ?? [];
            foreach ($hashes as $idx => $hash) {
                if (Hash::check($code, $hash)) {
                    // Consume this recovery code
                    unset($hashes[$idx]);
                    $user->twofa_recovery_codes = array_values($hashes);
                    $user->save();
                    $valid = true;
                    break;
                }
            }
        }

        if (! $valid) {
            throw new HttpResponseException(response()->json([
                'message' => 'Invalid two-factor authentication code or recovery code',
                'code'    => 'twofa_invalid_code',
            ], 400));
        }

        // Clear all 2FA-related fields
        $user->twofa_enabled        = false;
        $user->twofa_secret         = null;
        $user->twofa_recovery_codes = null;

        // Also clear any temp setup if present
        if (isset($user->twofa_secret_temp)) {
            $user->twofa_secret_temp = null;
        }
        if (isset($user->twofa_temp_expires_at)) {
            $user->twofa_temp_expires_at = null;
        }

        $user->save();
    }

    /**
     * Verify a one-time OTP (used during login):
     *
     * @param  User    $user
     * @param  string  $otp
     * @return bool    True if the code is valid
     */
    public function verifyOtp(User $user, string $otp): bool
    {
        if (! $user->twofa_enabled || empty($user->twofa_secret)) {
            return false;
        }
        return $this->google2fa->verifyKey($user->twofa_secret, $otp);
    }
}
