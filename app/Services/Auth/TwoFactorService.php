<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use PragmaRX\Google2FA\Google2FA;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TwoFactorService
{
    public function __construct(private Google2FA $google2fa) {}

    /**
     * Prépare l'activation de la 2FA :
     * génère secret temporaire, stocke dans les colonnes twofa_secret_temp et twofa_temp_expires_at,
     * retourne secret + QR.
     *
     * @param User $user
     * @return array{secret: string, qrCodeUrl: string}
     * @throws HttpResponseException
     */
    public function prepareEnable(User $user): array
    {
        if ($user->twofa_enabled) {
            throw new HttpResponseException(response()->json([
                'message' => 'Two-factor authentication is already enabled',
                'code'    => 'twofa_already_enabled',
            ], 400));
        }

        // Générer un nouveau secret
        $secret = $this->google2fa->generateSecretKey();

        // Stocker temporairement dans les colonnes du user
        $user->twofa_secret_temp = $secret;
        // TTL par ex. 10 minutes
        $user->twofa_temp_expires_at = Carbon::now()->addMinutes(10);
        $user->save();

        // Générer l’URL OTP Auth pour QR code (ex. otpauth://totp/...)
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
     * Confirm 2FA for the given user.
     *
     * @param  User  $user
     * @param  string  $otp
     * @return array{recovery_codes: array<string>}
     * @throws HttpResponseException
     */
    public function confirmEnable(User $user, string $otp): array
    {
        if ($user->twofa_enabled) {
            throw new HttpResponseException(response()->json([
                'message' => 'Two-factor authentication is already enabled',
                'code'    => 'twofa_already_enabled',
            ], 400));
        }

        // Vérifier qu’il y a un secret temporaire en base et qu’il n’est pas expiré
        if (empty($user->twofa_secret_temp) || empty($user->twofa_temp_expires_at)
            || now()->greaterThan($user->twofa_temp_expires_at)) {
            throw new HttpResponseException(response()->json([
                'message' => 'No 2FA setup in progress or expired',
                'code'    => 'twofa_no_setup_in_progress_or_expired',
            ], 400));
        }

        $secret = $user->twofa_secret_temp;

        // Vérifier l’OTP
        if (! $this->google2fa->verifyKey($secret, $otp)) {
            throw new HttpResponseException(response()->json([
                'message' => 'Invalid OTP code',
                'code'    => 'twofa_invalid_otp',
            ], 400));
        }

        // OTP valide → activer définitivement
        $user->twofa_secret = $secret;
        $user->twofa_enabled = true;
        // Supprimer les infos temp
        $user->twofa_secret_temp = null;
        $user->twofa_temp_expires_at = null;

        // Générer recovery codes (par ex. 8 codes de 10 caractères)
        $plainCodes = [];
        $hashedCodes = [];
        for ($i = 0; $i < 8; $i++) {
            $code = Str::upper(Str::random(10));
            $plainCodes[] = $code;
            $hashedCodes[] = Hash::make($code);
        }
        $user->twofa_recovery_codes = $hashedCodes;

        $user->save();

        // Retourner les codes en clair pour que le frontend les affiche/stocke
        return [
            'recovery_codes' => $plainCodes,
        ];
    }

    /**
     * Disable 2FA for the given user after verifying the code or a recovery code.
     *
     * @param User   $user
     * @param string $code  Le code OTP ou recovery code fourni par l'utilisateur
     * @return void
     *
     * @throws HttpResponseException On invalid code or if 2FA not enabled
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

        // 1. Vérifier OTP normal contre le secret
        if (! empty($user->twofa_secret) && $this->google2fa->verifyKey($user->twofa_secret, $code)) {
            $valid = true;
        } else {
            // 2. Vérifier recovery codes stockés
            $hashes = $user->twofa_recovery_codes ?? [];
            foreach ($hashes as $idx => $hash) {
                if (Hash::check($code, $hash)) {
                    // Consommer ce recovery code : le supprimer de la liste
                    unset($hashes[$idx]);
                    $user->twofa_recovery_codes = array_values($hashes);
                    // On sauvegarde maintenant pour enregistrer la consommation du code
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

        // 3. Désactivation définitive : nettoyer tous les champs 2FA
        $user->twofa_enabled        = false;
        $user->twofa_secret         = null;
        $user->twofa_recovery_codes = null;

        // Si vous utilisez les colonnes temporaires, on les nettoie aussi
        if (isset($user->twofa_secret_temp)) {
            $user->twofa_secret_temp = null;
        }
        if (isset($user->twofa_temp_expires_at)) {
            $user->twofa_temp_expires_at = null;
        }

        $user->save();
    }

    /**
     * Validate a recovery code for the given user.
     *
     * @param User $user
     * @param string $code
     * @return bool
     */
    public function verifyOtp(User $user, string $otp): bool
    {
        if (! $user->twofa_enabled || empty($user->twofa_secret)) {
            return false;
        }
        return $this->google2fa->verifyKey($user->twofa_secret, $otp);
    }
}
