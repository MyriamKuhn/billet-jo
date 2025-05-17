<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use App\Models\EmailUpdate;
use App\Helpers\EmailHelper;
use App\Services\Auth\EmailVerificationService;
use App\Exceptions\Auth\UserNotFoundException;
use App\Exceptions\Auth\InvalidVerificationLinkException;
use App\Exceptions\Auth\AlreadyVerifiedException;
use App\Exceptions\Auth\MissingVerificationTokenException;
use App\Exceptions\Auth\EmailUpdateNotFoundException;
use App\Services\Auth\EmailUpdateService;

class VerificationController extends Controller
{
    public function __construct(private EmailVerificationService $verificationService, private CartService $cartService, private EmailUpdateService $emailUpdateService) {}

    /**
     * Verify the user's email address
     *
     * @OA\Get(
     *     path="/api/auth/email/verify/{id}/{hash}",
     *     summary="Verify user email",
 *     description="
Validates the email verification link.
- In **production**, redirects the user to the frontend at `/verification-result/{success|invalid|already-verified|error}`.
- In **non-production**, returns a JSON payload with `message` and `redirect_url`.
",
     *     operationId="authVerifyEmail",
     *     tags={"Authentication"},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the user to verify",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="hash",
     *         in="path",
     *         description="SHA1 hash from the verification link",
     *         required=true,
     *         @OA\Schema(type="string", example="abc123hashsecure")
     *     ),
     *     @OA\Parameter(
     *         name="expires",
     *         in="query",
     *         description="Expiration timestamp of the signed URL",
     *         required=true,
     *         @OA\Schema(type="integer", example=1714687561)
     *     ),
     *     @OA\Parameter(
     *         name="signature",
     *         in="query",
     *         description="Signature of the signed URL",
     *         required=true,
     *         @OA\Schema(type="string", example="a94a8fe5ccb19ba61c4c0873d391e987982fbbd3")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Email verified successfully (JSON response in non-production)",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Email verified successfully"),
     *             @OA\Property(property="redirect_url", type="string", example="https://frontend.example.com/verification-result/success", description="Used for redirection only in production. In development, a JSON response will be returned.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=302,
     *         description="Redirect to frontend verification result page (production)",
     *         @OA\Header(
     *             header="Location",
     *             description="Redirect URL",
     *             @OA\Schema(type="string", example="https://frontend.example.com/verification-result/success")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Invalid verification link",
     *         @OA\JsonContent(
     *             @OA\Property(property="message",      type="string", example="Invalid verification link"),
     *             @OA\Property(property="code",         type="string", example="invalid_verification_link"),
     *             @OA\Property(property="redirect_url", type="string", example="https://frontend.example.com/verification-result/invalid")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="User not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message",      type="string", example="User not found"),
     *             @OA\Property(property="code",         type="string", example="user_not_found"),
     *             @OA\Property(property="redirect_url", type="string", example="https://frontend.example.com/verification-result/invalid")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=409,
     *         description="Email already verified",
     *         @OA\JsonContent(
     *             @OA\Property(property="message",      type="string", example="Email already verified"),
     *             @OA\Property(property="code",         type="string", example="already_verified"),
     *             @OA\Property(property="redirect_url", type="string", example="https://frontend.example.com/verification-result/already-verified")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Unexpected error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message",      type="string", example="Unexpected error"),
     *             @OA\Property(property="code",         type="string", example="internal_error"),
     *             @OA\Property(property="redirect_url", type="string", example="https://frontend.example.com/verification-result/error")
     *         )
     *     )
     * )
     */
    public function verify(Request $request): RedirectResponse|JsonResponse
    {
        $id    = (int) $request->route('id');
        $hash  = $request->route('hash');
        $base  = config('app.frontend_url') . '/verification-result';

        // Web flow redirection for the frontend
        if (app()->environment('production')) {
            try {
                $user = $this->verificationService->verify($id, $hash);
                $this->cartService->getUserCart($user);
                $segment = 'success';
            } catch (UserNotFoundException|InvalidVerificationLinkException $e) {
                $segment = 'invalid';
            } catch (AlreadyVerifiedException $e) {
                $segment = 'already-verified';
            } catch (\Throwable $e) {
                Log::error('Error during email verification', [
                    'exception' => $e,
                    'user_id'   => $id,
                ]);
                $segment = 'error';
            }

            return redirect()->away("{$base}/{$segment}");
        }

        // On dev environment, we return a JSON response
        $user = $this->verificationService->verify($id, $hash);
        $this->cartService->getUserCart($user);

        return response()->json([
            'message'      => 'Email verified successfully',
            'redirect_url' => "{$base}/success",
        ], 200);
    }

    /**
     * Resend email verification link
     *
     * @OA\Post(
     *     path="/api/auth/email/resend",
     *     summary="Resend verification email",
     *     description="Sends a new email verification link to the authenticated user. Requires a valid Bearer token.",
     *     operationId="authResendVerificationEmail",
     *     tags={"Authentication"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguageHeader"),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Verification email resent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Verification email resent")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(
     *         response=409,
     *         description="Email already verified",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Email already verified"),
     *             @OA\Property(property="code",    type="string", example="already_verified")
     *         )
     *     ),
     *     @OA\Response(response=429, ref="#/components/responses/TooManyRequests"),
     *     @OA\Response(response=500, ref="#/components/responses/InternalError"),
     * )
     */
    public function resend(Request $request): JsonResponse
    {
        $result = $this->verificationService->resend(auth()->user());

        return response()->json($result, 200);
    }

    /**
     * Verify and apply a pending email change.
     *
     * @OA\Get(
     *     path="/api/auth/email/change/verify",
     *     summary="Verify new email address",
     *     description="
Validates the token for a pending email change.
- In **production**, redirects to the front at `/verification-result/{success|invalid|error}`.
- In **non-production**, returns JSON with `message` and `redirect_url`.
",
     *     operationId="authVerifyNewEmail",
     *     tags={"Authentication"},
     *
     *     @OA\Parameter(
     *         name="token",
     *         in="query",
     *         description="Raw verification token from the email link",
     *         required=true,
     *         @OA\Schema(type="string", example="03AGdBq26…")
     *     ),
     *     @OA\Parameter(
     *         name="expires",
     *         in="query",
     *         description="Expiration timestamp of the signed URL",
     *         required=true,
     *         @OA\Schema(type="integer", example=1714687561)
     *     ),
     *     @OA\Parameter(
     *         name="signature",
     *         in="query",
     *         description="Signature of the signed URL",
     *          required=true,
     *         @OA\Schema(type="string", example="a94a8fe5ccb19ba61c4c0873d391e987982fbbd3")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Email updated successfully (JSON in non-production)",
     *         @OA\JsonContent(
     *             @OA\Property(property="message",      type="string", example="Email updated successfully"),
     *             @OA\Property(property="redirect_url", type="string", example="https://frontend.example.com/verification-result/success")
     *         )
     *     ),
     *     @OA\Response(
     *         response=302,
     *         description="Redirect to frontend result page (production)",
     *         @OA\Header(
     *             header="Location",
     *             description="Redirect URL",
     *             @OA\Schema(type="string", example="https://frontend.example.com/verification-result/success")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Missing or invalid token",
     *         @OA\JsonContent(
     *             @OA\Property(property="message",      type="string", example="Invalid or expired verification token"),
     *             @OA\Property(property="code",         type="string", example="verification_token_missing"),
     *             @OA\Property(property="redirect_url", type="string", example="https://frontend.example.com/verification-result/invalid")
     *           )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Email request not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message",      type="string", example="Email request not found"),
     *             @OA\Property(property="code",         type="string", example="email_not_found"),
     *             @OA\Property(property="redirect_url", type="string", example="https://frontend.example.com/verification-result/invalid")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Unexpected error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message",      type="string", example="Unexpected error"),
     *             @OA\Property(property="code",         type="string", example="internal_error"),
     *             @OA\Property(property="redirect_url", type="string", example="https://frontend.example.com/verification-result/error")
     *         )
     *     )
     * )
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function verifyNew(Request $request): RedirectResponse|JsonResponse
    {
        $token = $request->query('token');
        $base  = config('app.frontend_url') . '/verification-result';

        // Web flow redirection in prod
        if (app()->environment('production')) {
            try {
                $user = $this->emailUpdateService->verifyNewEmail($token);
                // recreate the cart for the user
                $this->cartService->getUserCart($user);
                $segment = 'success';
            } catch (MissingVerificationTokenException|EmailUpdateNotFoundException $e) {
                $segment = 'invalid';
            } catch (\Throwable $e) {
                Log::error('Error verifying new email', [
                    'exception' => $e,
                    'token'     => $token,
                ]);
                $segment = 'error';
            }

            return redirect()->away("{$base}/{$segment}");
        }

        // Dev/API → JSON
        $this->emailUpdateService->verifyNewEmail($token);
        return response()->json([
            'message'      => 'Email updated successfully',
            'redirect_url' => "{$base}/success",
        ], 200);
    }

    /**
     * Cancel a pending email change and restore the old address.
     *
     * @OA\Get(
     *     path="/api/auth/email/change/cancel/{token}/{old_email}",
     *     summary="Cancel email change request",
     *     description="
Validates the token for a pending email change and restores the old email during 48 hours.
- In **production**, redirects the user to `/verification-result/{success|invalid|error}`.
- In **non-production**, returns JSON with `message` and `redirect_url`.
",
    *     operationId="authCancelEmailUpdate",
    *     tags={"Authentication"},
    *
    *     @OA\Parameter(
    *         name="token",
    *         in="path",
    *         description="Raw token from the email update link",
    *         required=true,
    *         @OA\Schema(type="string", example="03AGdBq26…")
    *     ),
    *     @OA\Parameter(
    *         name="old_email",
    *         in="path",
    *         description="Previous email address before the update",
    *         required=true,
    *         @OA\Schema(type="string", format="email", example="old.user@example.com")
    *     ),
    *     @OA\Parameter(
    *         name="expires",
    *         in="query",
    *         description="Expiration timestamp of the signed URL",
    *         required=true,
    *         @OA\Schema(type="integer", example=1714687561)
    *     ),
    *     @OA\Parameter(
    *         name="signature",
    *         in="query",
    *         description="Signature of the signed URL",
    *         required=true,
    *         @OA\Schema(type="string", example="a94a8fe5ccb19ba61c4c0873d391e987982fbbd3")
    *     ),
    *
    *     @OA\Response(
    *         response=200,
    *         description="Email update canceled successfully (JSON in non-production)",
    *         @OA\JsonContent(
    *             @OA\Property(property="message",      type="string", example="Email update canceled"),
    *             @OA\Property(property="redirect_url", type="string", example="https://frontend.example.com/verification-result/success")
    *         )
    *     ),
    *     @OA\Response(
    *         response=302,
    *         description="Redirect to frontend result page (production)",
    *         @OA\Header(
    *             header="Location",
    *             description="Redirect URL",
    *             @OA\Schema(type="string", example="https://frontend.example.com/verification-result/success")
    *         )
    *     ),
    *     @OA\Response(
    *         response=404,
    *         description="Email update request not found or already processed",
    *         @OA\JsonContent(
    *             @OA\Property(property="message",      type="string", example="Email update request not found"),
    *             @OA\Property(property="code",         type="string", example="email_update_not_found"),
    *             @OA\Property(property="redirect_url", type="string", example="https://frontend.example.com/verification-result/invalid")
    *         )
    *     ),
    *     @OA\Response(
    *         response=500,
    *         description="Unexpected error",
    *         @OA\JsonContent(
    *             @OA\Property(property="message",      type="string", example="Unexpected error"),
    *             @OA\Property(property="code",         type="string", example="internal_error"),
    *             @OA\Property(property="redirect_url", type="string", example="https://frontend.example.com/verification-result/error")
    *         )
    *     )
    * )
    */
    public function cancelChange(Request $request, string $token, string $oldEmail): RedirectResponse|JsonResponse
    {
        $base = config('app.frontend_url') . '/verification-result';

        if (app()->environment('production')) {
            try {
                $user = $this->emailUpdateService->cancelEmailUpdate($token, $oldEmail);
                // recreate the cart for the user
                $this->cartService->getUserCart($user);
                $segment = 'success';
            } catch (EmailUpdateNotFoundException $e) {
                $segment = 'invalid';
            } catch (\Throwable $e) {
                Log::error('Error canceling email update', [
                    'exception' => $e,
                    'token'     => $token,
                    'old_email' => $oldEmail,
                ]);
                $segment = 'error';
            }

            return redirect()->away("{$base}/{$segment}");
        }

        // Dev/API → JSON
        $this->emailUpdateService->cancelEmailUpdate($token, $oldEmail);

        return response()->json([
            'message'      => 'Email update canceled',
            'redirect_url' => "{$base}/success",
        ], 200);
    }
}
