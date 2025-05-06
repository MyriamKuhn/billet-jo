<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    public function __construct(private Google2FA $google2fa) {}

    /**
     * Enable 2FA for the given user.
     *
     * @param  User  $user
     * @return array{secret: string, qrCodeUrl: string}
     * @throws HttpResponseException
     */
    public function enable(User $user): array
    {
        // If 2FA is already enabled, throw an exception
        if ($user->twofa_enabled) {
            throw new HttpResponseException(response()->json([
                'message' => 'Two-factor authentication is already enabled',
                'code'    => 'twofa_already_enabled',
            ], 400));
        }

        // Generate a new secret key
        $secret = $this->google2fa->generateSecretKey();

        // Save the secret key to the user model
        $user->twofa_secret  = $secret;
        $user->twofa_enabled = true;
        $user->save();

        //  Generate the QR code URL
        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        return [
            'secret'    => $secret,
            'qrCodeUrl' => $qrCodeUrl,
        ];
    }
}
