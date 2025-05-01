<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\CartService;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use App\Models\EmailUpdate;
use App\Helpers\EmailHelper;

class VerificationController extends Controller
{
    /**
     * Verify the user's email address
     *
     * @OA\Get(
     *     path="/api/auth/email/verify/{id}/{hash}",
     *     summary="Verify the user's email address",
     *     description="This route verifies a user's email address via a link sent to them. Based on the result, it returns a redirection URL. This operation is intended to be used exclusively through links sent by email.",
     *     operationId="verifyEmail",
     *     tags={"Authentication"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="User ID to verify",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="hash",
     *         in="path",
     *         description="Hash of the user's email address",
     *         required=true,
     *         @OA\Schema(type="string", example="abc123hashsecure")
     *     ),
     *     @OA\Parameter(
     *         name="expires",
     *         in="query",
     *         description="Expiration timestamp of the link",
     *         required=true,
     *         @OA\Schema(type="integer", example=1714687561)
     *     ),
     *     @OA\Parameter(
     *         name="signature",
     *         in="query",
     *         description="Signed URL signature",
     *         required=true,
     *         @OA\Schema(type="string", example="a94a8fe5ccb19ba61c4c0873d391e987982fbbd3")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Email verified successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Your email address has been successfully verified."),
     *             @OA\Property(property="redirect_url", type="string", example="https://jo2024.mkcodecreation.dev/verification-result/success", description="Used for redirection only in production. In development, a JSON response will be returned.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid verification link",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="error", type="string", example="The verification link is invalid."),
     *             @OA\Property(property="redirect_url", type="string", example="https://jo2024.mkcodecreation.dev/verification-result/invalid", description="Used for redirection only in production. In development, a JSON response will be returned.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="error", type="string", example="User not found."),
     *             @OA\Property(property="redirect_url", type="string", example="https://jo2024.mkcodecreation.dev/verification-result/invalid", description="Used for redirection only in production. In development, a JSON response will be returned.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Email already verified",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="error", type="string", example="Your email address has already been verified."),
     *             @OA\Property(property="redirect_url", type="string", example="https://jo2024.mkcodecreation.dev/verification-result/already-verified", description="Used for redirection only in production. In development, a JSON response will be returned.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="error", type="string", example="An unknown error occurred. Please try again later"),
     *             @OA\Property(property="redirect_url", type="string", example="https://jo2024.mkcodecreation.dev/verification-result/error", description="Used for redirection only in production. In development, a JSON response will be returned.")
     *         )
     *     )
     * )
     */
    public function verifyEMail(Request $request, CartService $cartService): JsonResponse|RedirectResponse
    {
        try {
            /** @var \App\Models\User|\Illuminate\Contracts\Auth\MustVerifyEmail|null $user */
            $user = User::find($request->route('id'));

            if (!$user) {
                if (app()->environment('production')) {
                    return redirect()->away('https://jo2024.mkcodecreation.dev/verification-result/invalid');
                } else {
                    return response()->json([
                        'status' => 'error',
                        'error' => __('validation.error_user_not_found'),
                        'redirect_url' => 'https://jo2024.mkcodecreation.dev/verification-result/invalid'
                    ], 404);
                }
            }

            if (!hash_equals(sha1($user->getEmailForVerification()), $request->route('hash'))) {
                if (app()->environment('production')) {
                    return redirect()->away('https://jo2024.mkcodecreation.dev/verification-result/invalid');
                } else {
                    return response()->json([
                        'status' => 'error',
                        'error' => __('validation.error_email_verification_invalid'),
                        'redirect_url' => 'https://jo2024.mkcodecreation.dev/verification-result/invalid'
                    ], 400);
                }
            }

            if ($user->hasVerifiedEmail()) {
                if (app()->environment('production')) {
                    return redirect()->away('https://jo2024.mkcodecreation.dev/verification-result/already-verified');
                } else {
                    return response()->json([
                        'status' => 'error',
                        'error' => __('validation.error_email_already_verified'),
                        'redirect_url' => 'https://jo2024.mkcodecreation.dev/verification-result/already-verified'
                    ], 409);
                }
            }

            $user->markEmailAsVerified();
            event(new Verified($user));

            // Création du panier
            $cartService->createCartForUser($user);

            if (app()->environment('production')) {
                return redirect()->away('https://jo2024.mkcodecreation.dev/verification-result/success');
            } else {
                return response()->json([
                    'status' => 'success',
                    'message' => __('validation.email_verification_success'),
                    'redirect_url' => 'https://jo2024.mkcodecreation.dev/verification-result/success'
                ], 200);
            }

        } catch (\Exception $e) {
            Log::error('Error during email verification', [
                'error' => $e->getMessage(),
                'user_id' => $request->route('id')
            ]);

            if (app()->environment('production')) {
                return redirect()->away('https://jo2024.mkcodecreation.dev/verification-result/error');
            } else {
                return response()->json([
                    'status' => 'error',
                    'error' => __('validation.error_unknown'),
                    'redirect_url' => 'https://jo2024.mkcodecreation.dev/verification-result/error'
                ], 500);
            }
        }
    }

    /**
     * Rensend verification email
     *
     * @OA\Post(
     *     path="/api/auth/email/resend-verification",
     *     summary="Resend email verification link",
     *     description="This endpoint allows a logged-in user to request a resend of the email verification link if their email address has not been verified yet.",
     *     operationId="resendVerificationEmail",
     *     tags={"Authentication"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Email verification link resent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="A new verification email has been sent.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Email already verified",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="error", type="string", example="Your email address has already been verified.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Not authenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="error", type="string", example="You must be logged in to perform this action.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="error", type="string", example="An unknown error occurred. Please try again later"),
     *         )
     *     )
     * )
     */
    public function resendVerificationEmail(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'error' => __('validation.error_unauthorized'),
                ], 401);
            }

            if ($user->hasVerifiedEmail()) {
                return response()->json([
                    'status' => 'error',
                    'error' => __('validation.error_email_already_verified'),
                ], 409);
            }

            $user->sendEmailVerificationNotification();

            return response()->json([
                'status' => 'success',
                'message' => __('validation.email_verification_resend'),
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error resending verification email', [
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? null
            ]);

            return response()->json([
                'status' => 'error',
                'error' => __('validation.error_unknown'),
            ], 500);
        }
    }

    /**
     * Verify a new email address
     *
     * @OA\Get(
     *     path="/api/auth/email/verify-new-mail",
     *     summary="Verify a user's new email address",
     *     description="Verifies a new email using a token sent to the user's new email address. The token must be passed as a query parameter.",
     *     operationId="verifyNewEmail",
     *     tags={"Authentication"},
     *     @OA\Parameter(
     *         name="token",
     *         in="query",
     *         description="The token used to verify the email update",
     *         required=true,
     *         @OA\Schema(type="string", example="abc123hashsecure")
     *     ),
     *     @OA\Parameter(
     *         name="signature",
     *         in="query",
     *         description="The signature to validate the token",
     *         required=true,
     *         @OA\Schema(type="string", example="xyz456signature")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Email updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Your email has been updated successfully."),
     *             @OA\Property(property="redirect_url", type="string", example="https://jo2024.mkcodecreation.dev/verification-result/success", description="Used for redirection only in production. In development, a JSON response will be returned.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Missing or invalid token",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="error", type="string", example="Invalid or missing verification token."),
     *             @OA\Property(property="redirect_url", type="string", example="https://jo2024.mkcodecreation.dev/verification-result/invalid", description="Used for redirection only in production. In development, a JSON response will be returned.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Token used or user not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="error", type="string", example="This verification token is no longer valid."),
     *             @OA\Property(property="redirect_url", type="string", example="https://jo2024.mkcodecreation.dev/verification-result/invalid", description="Used for redirection only in production. In development, a JSON response will be returned.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="error", type="string", example="An unknown error occurred."),
     *             @OA\Property(property="redirect_url", type="string", example="https://jo2024.mkcodecreation.dev/verification-result/error", description="Used for redirection only in production. In development, a JSON response will be returned.")
     *         )
     *     )
     * )
     */
    public function verifyNewEMail(Request $request): JsonResponse
    {
        try {
            $token = $request->query('token');

            if (!$token) {
                if (app()->environment('production')) {
                    return redirect()->away('https://jo2024.mkcodecreation.dev/verification-result/invalid');
                } else {
                    return response()->json([
                        'status'=> 'error',
                        'error' => __('validation.error_email_verification_invalid'),
                        'redirect_url' => 'https://jo2024.mkcodecreation.dev/verification-result/invalid',
                    ], 400);
                }
            }

            $hashedToken = EmailHelper::hashToken($token);
            $emailUpdate = EmailUpdate::where('token', $hashedToken)->first();

            if (!$emailUpdate) {
                if (app()->environment('production')) {
                    return redirect()->away('https://jo2024.mkcodecreation.dev/verification-result/invalid');
                } else {
                    return response()->json([
                        'status'=> 'error',
                        'error' => __('validation.error_email_verification_used'),
                        'redirect_url' => 'https://jo2024.mkcodecreation.dev/verification-result/invalid',
                    ], 404);
                }
            }

            $user = User::find($emailUpdate->user_id);

            if (!$user) {
                if (app()->environment('production')) {
                    return redirect()->away('https://jo2024.mkcodecreation.dev/verification-result/invalid');
                } else {
                    return response()->json([
                        'status' => 'error',
                        'error' => __('validation.error_user_not_found'),
                        'redirect_url' => 'https://jo2024.mkcodecreation.dev/verification-result/invalid'
                    ], 404);
                }
            }

            // Update the user's email address
            $user->email = $emailUpdate->new_email;
            $user->email_verified_at = now(); // Set the email as verified
            $user->save();

            // Update the email update record
            $emailUpdate->touch();

            if (app()->environment('production')) {
                return redirect()->away('https://jo2024.mkcodecreation.dev/verification-result/success');
            } else {
                return response()->json([
                    'status' => 'success',
                    'message' => __('validation.email_updated_success'),
                    'redirect_url' => 'https://jo2024.mkcodecreation.dev/verification-result/success'
                ], 200);
            }

        } catch (\Exception $e) {
            Log::error('Error verifying new email', [
                'error' => $e->getMessage(),
                'token' => $request->query('token')
            ]);

            if (app()->environment('production')) {
                return redirect()->away('https://jo2024.mkcodecreation.dev/verification-result/error');
            } else {
                return response()->json([
                    'status' => 'error',
                    'error' => __('validation.error_unknown'),
                    'redirect_url' => 'https://jo2024.mkcodecreation.dev/verification-result/error'
                ], 500);
            }
        }
    }

    /**
     * Cancel a pending email update request
     *
     * @OA\Get(
     *     path="/api/auth/email/cancel-update/{token}/{old_email}",
     *     operationId="cancelEmailUpdate",
     *     tags={"Authentication"},
     *     summary="Cancel a pending email update request",
     *     description="Cancels a pending email update request using a signed URL. The link is valid for 48 hours.",
     *     @OA\Parameter(
     *         name="token",
     *         in="path",
     *         description="Verification token (plain, not hashed)",
     *         required=true,
     *         @OA\Schema(type="string", example="d7f9b27dcbf04431c9c45a271c...")
     *     ),
     *     @OA\Parameter(
     *         name="old_email",
     *         in="path",
     *         description="The user's current (old) email address",
     *         required=true,
     *         @OA\Schema(type="string", format="email", example="old@example.com")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="The email update request was successfully canceled",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Your email update request has been cancelled."),
     *             @OA\Property(property="redirect_url", type="string", example="https://jo2024.mkcodecreation.dev/verification-result/success", description="Used for redirection only in production. In development, a JSON response will be returned.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No matching email update request found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="error", type="string", example="Email update request not found."),
     *             @OA\Property(property="redirect_url", type="string", example="https://jo2024.mkcodecreation.dev/verification-result/invalid", description="Used for redirection only in production. In development, a JSON response will be returned.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="error", type="string", example="An unknown error occurred. Please try again later"),
     *             @OA\Property(property="redirect_url", type="string", example="https://jo2024.mkcodecreation.dev/verification-result/error", description="Used for redirection only in production. In development, a JSON response will be returned.")
     *         )
     *     )
     * )
     */
    public function cancelEmailUpdate(Request $request, $token, $oldEmail): JsonResponse
    {
        try {
            $hashedToken = EmailHelper::hashToken($token);

            $emailUpdate = EmailUpdate::where('token', $hashedToken)
                ->where('old_email', $oldEmail)
                ->first();

            // Check if the email update request exists
            if (!$emailUpdate) {
                if (app()->environment('production')) {
                    return redirect()->away('https://jo2024.mkcodecreation.dev/verification-result/invalid');
                } else {
                    return response()->json([
                        'status' => 'error',
                        'error' => __('validation.error_email_request_not_found'),
                        'redirect_url' => 'https://jo2024.mkcodecreation.dev/verification-result/invalid'
                    ], 404);
                }
            }

            // Revoke the email update request and restore the old email
            $user = $emailUpdate->user;
            $user->email = $emailUpdate->old_email;
            $user->email_verified_at = now();
            $user->save();

            // Detelete the email update record
            $emailUpdate->delete();

            if (app()->environment('production')) {
                return redirect()->away('https://jo2024.mkcodecreation.dev/verification-result/success');
            } else {
                return response()->json([
                    'status' => 'success',
                    'message' => __('validation.email_update_canceled'),
                    'redirect_url' => 'https://jo2024.mkcodecreation.dev/verification-result/success'
                ], 200);
            }

        } catch (\Exception $e) {
            Log::error('Error canceling email update', [
                'error' => $e->getMessage(),
                'token' => $token,
                'old_email' => $oldEmail
            ]);

            if (app()->environment('production')) {
                return redirect()->away('https://jo2024.mkcodecreation.dev/verification-result/error');
            } else {
                return response()->json([
                    'status' => 'error',
                    'error' => __('validation.error_unknown'),
                    'redirect_url' => 'https://jo2024.mkcodecreation.dev/verification-result/error'
                ], 500);
            }
        }
    }
}
