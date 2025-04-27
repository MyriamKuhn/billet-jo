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
     *             @OA\Property(property="message", type="string", example="The verification link is invalid."),
     *             @OA\Property(property="redirect_url", type="string", example="https://jo2024.mkcodecreation.dev/verification-result/invalid", description="Used for redirection only in production. In development, a JSON response will be returned.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="User not found."),
     *             @OA\Property(property="redirect_url", type="string", example="https://jo2024.mkcodecreation.dev/verification-result/invalid", description="Used for redirection only in production. In development, a JSON response will be returned.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Email already verified",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Your email address has already been verified."),
     *             @OA\Property(property="redirect_url", type="string", example="https://jo2024.mkcodecreation.dev/verification-result/already-verified", description="Used for redirection only in production. In development, a JSON response will be returned.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="An unknown error occurred. Please try again later"),
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
                        'message' => __('validation.user_not_found'),
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
                        'message' => __('validation.email_verification_invalid'),
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
                        'message' => __('validation.email_already_verified'),
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
                    'message' => __('validation.unknown_error'),
                    'redirect_url' => 'https://jo2024.mkcodecreation.dev/verification-result/error'
                ], 500);
            }
        }
    }

    /**
     * Rensend verification email
     *
     * @OA\Post(
     *     path="/api/auth/email/verification-notification",
     *     summary="Resend email verification link",
     *     description="This endpoint allows a logged-in user to request a resend of the email verification link if their email address has not been verified yet.",
     *     operationId="resendVerificationEmail",
     *     tags={"Authentication"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Email verification link resent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="A new verification email has been sent.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Email already verified",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Your email address has already been verified.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Not authenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="You must be logged in to perform this action.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="An unknown error occurred. Please try again later"),
     *         )
     *     )
     * )
     */
    public function resendVerificationEmail(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('validation.must_be_connected'),
                ], 401);
            }

            if ($user->hasVerifiedEmail()) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('validation.email_already_verified'),
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
                'message' => __('validation.unknown_error'),
            ], 500);
        }
    }
}
