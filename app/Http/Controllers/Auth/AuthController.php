<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\Auth\RegistrationService;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\AuthService;
use App\Services\Auth\TwoFactorService;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Http\Requests\Auth\UpdateEmailRequest;
use App\Http\Requests\Auth\DisableTwoFactorRequest;

class AuthController extends Controller
{
    public function __construct(private RegistrationService $registrationService, private AuthService $authService, private TwoFactorService $twoFactorService) {}

    /**
     * Register a new user.
     *
     * @OA\Post(
     *     path="/api/auth/register",
     *     summary="Register a new user",
     *     description="
This endpoint allows a visitor to create a new user account with built-in protections:
- **Strong password enforcement**: minimum 15 characters, mixed case, numbers & symbols
- **Google reCAPTCHA** verification to block bots and automated abuse
- **Throttling** (e.g. 5 requests/minute) to limit spam & brute-force attempts
- **Email confirmation**: a verification link is sent after registration
",
     *     operationId="registerUser",
     *     tags={"Authentication"},
     *
     *      @OA\Parameter(ref="#/components/parameters/AcceptLanguageHeader"),
     *
     *       @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/RegisterUser")
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="User created successfully. Verification Email sent.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Registration successful. Please check your emails.")
     *         )
     *     ),
     *     @OA\Response(response=422, ref="#/components/responses/ValidationError"),
     *     @OA\Response(response=429, ref="#/components/responses/TooManyRequests"),
     *     @OA\Response(response=500, ref="#/components/responses/InternalError")
     * )
     *
     * @param RegisterRequest $request
     * @return JsonResponse
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $this->registrationService->register($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Registration successful. Please check your emails.',
        ], 201);
    }

    /**
     * Login a user.
     *
     * @OA\Post(
     *     path="/api/auth/login",
     *     summary="Authenticate user",
     *     description="
This endpoint allows a user to log in and receive an authentication token:

- **Email & password** credentials are required
- **Remember me** option for extended session duration (1 week)
- **Two-factor authentication (2FA)** code if enabled
- **Throttling** to limit brute-force attempts
    ",
    *     operationId="authLogin",
    *     tags={"Authentication"},
    *
    *     @OA\Parameter(ref="#/components/parameters/AcceptLanguageHeader"),
    *
    *     @OA\RequestBody(
    *         required=true,
    *         description="User login credentials",
    *         @OA\JsonContent(
    *             required={"email","password"},
    *             @OA\Property(property="email",       type="string", format="email",    example="user@example.com"),
    *             @OA\Property(property="password",    type="string", format="password", example="MyGreatPassword@123"),
    *             @OA\Property(property="remember",    type="boolean",               example=true, description="Stay logged in for one week"),
    *             @OA\Property(property="twofa_code",  type="string",                example="123456",      description="2FA code if enabled")
    *         )
    *     ),
    *
    *     @OA\Response(
    *         response=200,
    *         description="Authentication successful. Returns a token and user information.",
    *         @OA\JsonContent(
    *             @OA\Property(property="message", type="string", example="Logged in successfully"),
    *             @OA\Property(property="token",   type="string", example="eyJ0eXAiOiJKV1QiLCJhbGciOiJI…"),
    *             @OA\Property(property="user",    type="object",
    *                 @OA\Property(property="id",            type="integer", example=1),
    *                 @OA\Property(property="firstname",     type="string",  example="John"),
    *                 @OA\Property(property="lastname",      type="string",  example="Doe"),
    *                 @OA\Property(property="email",         type="string",  example="user@example.com"),
    *                 @OA\Property(property="role",          type="string",  example="user"),
    *                 @OA\Property(property="twofa_enabled", type="boolean", example=true)
    *             )
    *         )
    *     ),
    *
    *     @OA\Response(
    *         response=400,
    *         description="Bad request: email not verified or 2FA missing/invalid",
    *   @OA\JsonContent(
    *     oneOf={
    *       @OA\Schema(
    *         required={"message","code"},
    *         @OA\Property(property="message", type="string", example="Email address not verified"),
    *         @OA\Property(property="code",    type="string", example="email_not_verified"),
    *         @OA\Property(property="resend_verification_url",type="string", format="url",
    *             example="https://api.example.com/api/auth/email/resend",
    *             description="URL to call to resend the verification email")
    *       ),
    *       @OA\Schema(
    *         required={"message","code"},
    *         @OA\Property(property="message", type="string", example="Two-factor authentication code is required"),
    *         @OA\Property(property="code",    type="string", example="twofa_required")
    *       ),
    *       @OA\Schema(
    *         required={"message","code"},
    *         @OA\Property(property="message", type="string", example="Invalid two-factor authentication code"),
    *         @OA\Property(property="code",    type="string", example="twofa_invalid")
    *       )
    *     }
    *   )
    * ),
    *
    *     @OA\Response(
    *         response=401,
    *         description="Unauthorized (invalid credentials)",
    *         @OA\JsonContent(
    *             @OA\Property(property="message", type="string", example="Invalid credentials"),
    *             @OA\Property(property="code",    type="string", example="invalid_credentials")
    *         )
    *     ),
    *
    *     @OA\Response(
    *         response=403,
    *         description="Forbidden (account disabled)",
    *         @OA\JsonContent(
    *             @OA\Property(property="message", type="string", example="Account disabled"),
    *             @OA\Property(property="code",    type="string", example="account_disabled")
    *         )
    *     ),
    *     @OA\Response(response=422, ref="#/components/responses/ValidationError"),
    *     @OA\Response(response=429, ref="#/components/responses/TooManyRequests"),
    *     @OA\Response(response=500, ref="#/components/responses/InternalError")
    * )
    */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        return response()->json($result, 200);
    }

    /**
     * Enable two-factor authentication for the user.
     *
     * @OA\Post(
     *     path="/api/auth/2fa/enable",
     *     summary="Enable two-factor authentication",
     *     description="
Generates a new secret key for two-factor authentication and returns an OTP-Auth URL.
Requires the user to be authenticated via Bearer token.
",
     *     operationId="authEnableTwoFactor",
     *     tags={"Authentication"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Two-factor authentication enabled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="secret", type="string", description="Generated secret key for 2FA", example="JBSWY3DPEHPK3PXP"),
     *             @OA\Property(property="qrCodeUrl", type="string", description="URL to generate QR code for 2FA", example="otpauth://totp/Example%3Auser%40example.com?secret=JBSWY3DPEHPK3PXP&issuer=Example")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Two-factor authentication already enabled",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Two-factor authentication is already enabled"),
     *             @OA\Property(property="code",    type="string", example="twofa_already_enabled")
     *         )
     *     ),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=500, ref="#/components/responses/InternalError"),
     *     @OA\Response(response=503, ref="#/components/responses/ServiceUnavailable")
     * )
     *
     * @return JsonResponse
     */
    public function enableTwoFactor(): JsonResponse
    {
        $result = $this->twoFactorService->enable(auth()->user());

        return response()->json($result, 200);
    }

    /**
     * Logout the current user.
     *
     * @OA\Post(
     *     path="/api/auth/logout",
     *     summary="Revoke the current authentication token",
     *     description="Revokes the token used for authentication. Requires a valid Bearer token.",
     *     operationId="authLogout",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Logout successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Logged out successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Bad request (no active token)",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No active token found"),
     *             @OA\Property(property="code",    type="string", example="no_active_token")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=500, ref="#/components/responses/InternalError"),
     *     @OA\Response(response=503, ref="#/components/responses/ServiceUnavailable")
     * )
     *
     * @return JsonResponse
     */
    public function logout(): JsonResponse
    {
        $result = $this->authService->logout(auth()->user());

        return response()->json($result, 200);
    }

    /**
     * Send a password reset link.
     *
     * @OA\Post(
     *     path="/api/auth/password/forgot",
     *     summary="Send password reset link",
     *     description="
Sends an email containing a password reset link to the user.

- Requires a valid email of an existing user.
- Response does not reveal if the email is registered, to prevent enumeration.
    ",
     *     operationId="authForgotPassword",
     *     tags={"Authentication"},
     *
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguageHeader"),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Reset link sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Password reset link sent")
     *         )
     *     ),
     *
     *     @OA\Response(response=422, ref="#/components/responses/ValidationError"),
     *     @OA\Response(response=429, ref="#/components/responses/TooManyRequests"),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error or email send failure",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unable to send reset link"),
     *             @OA\Property(property="code",    type="string", example="reset_link_failed")
     *         )
     *     )
     * )
     *
     * @param ForgotPasswordRequest $request
     * @return JsonResponse
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $result = $this->authService->sendResetLink($request->validated()['email']);

        return response()->json($result, 200);
    }

    /**
     * Reset the user's password using the token sent to their email.
     *
     * @OA\Post(
     *     path="/api/auth/password/reset",
     *     summary="Reset password with token",
     *     description="
Allows a user to reset their password using a valid reset token:

- `token`: the password reset token
- `email`: user’s email (must exist)
- `password` + `password_confirmation`: new secure password (min 15 chars, mixed case, numbers, symbols)
",
     *     operationId="authResetPassword",
     *     tags={"Authentication"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"token", "email", "password", "password_confirmation"},
     *             @OA\Property(property="token", type="string", example="abcdef123456"),
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="StrongP@ssword2025!"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="StrongP@ssword2025!")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Password successfully reset",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Password has been reset successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Invalid or expired token",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invalid password reset token"),
     *             @OA\Property(property="code",    type="string", example="invalid_token")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Email not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No user found with this email"),
     *             @OA\Property(property="code",    type="string", example="user_not_found")
     *         )
     *     ),
     *     @OA\Response(response=422, ref="#/components/responses/ValidationError"),
     *     @OA\Response(response=429, ref="#/components/responses/TooManyRequests"),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unexpected error during password reset"),
     *             @OA\Property(property="code",    type="string", example="internal_error")
     *         )
     *     )
     * )
     *
     * @param ResetPasswordRequest $request
     * @return JsonResponse
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $result = $this->authService->resetPassword($request->validated());

        return response()->json($result, 200);
    }

    /**
     * Update the user's password.
     *
     * @OA\Patch(
     *     path="/api/auth/password",
     *     summary="Change password",
     *     description="
Allows an authenticated user to change their password:

- Provide `current_password` to verify identity
- Set a new secure password (min. 15 chars, mixed case, numbers & symbols)
",
     *     tags={"Authentication"},
     *     operationId="authUpdatePassword",
     *     security={{
     *         "bearerAuth": {}
     *     }},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"current_password", "password", "password_confirmation"},
     *             @OA\Property(
     *                 property="current_password",
     *                 type="string",
     *                 example="StrongP@ssword2025!",
     *                 description="The current password of the user"
     *             ),
     *             @OA\Property(
     *                 property="password",
     *                 type="string",
     *                 example="NewP@ssword2025!",
     *                 description="The new password to be set for the user"
     *             ),
     *             @OA\Property(
     *                 property="password_confirmation",
     *                 type="string",
     *                 example="NewP@ssword2025!",
     *                 description="Confirmation of the new password"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Password changed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Password changed successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Invalid current password",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Current password is incorrect"),
     *             @OA\Property(property="code",    type="string", example="invalid_current_password")
     *         )
     *     ),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=422, ref="#/components/responses/ValidationError"),
     *     @OA\Response(response=500, ref="#/components/responses/InternalError"),
     *     @OA\Response(response=503, ref="#/components/responses/ServiceUnavailable")
     * )
     *
     * @param UpdatePasswordRequest $request
     * @return JsonResponse
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $result = $this->authService->updatePassword(
            auth()->user(),
            $request->validated()
        );

        return response()->json($result, 200);
    }

    /**
     * Request email change for the current user.
     *
     * @OA\Patch(
     *     path="/api/auth/email",
     *     operationId="authUpdateEmail",
     *     summary="Request email update",
 *     description="
Sends a verification link to the new email address and notifies the old address.
Requires authentication via Bearer token.
",
     *     tags={"Authentication"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguageHeader"),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="New unique email address",
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="new.email@example.com")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Verification email sent to the new address",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Verification email sent to the new address")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=422, ref="#/components/responses/ValidationError"),
     *     @OA\Response(response=500, ref="#/components/responses/InternalError"),
     * )
     *
     * @param UpdateEmailRequest $request
     * @return JsonResponse
     */
    public function updateEmail(UpdateEmailRequest $request): JsonResponse
    {
        $result = $this->authService->updateEmail(
            auth()->user(),
            $request->input('email')
        );

        return response()->json($result, 200);
    }

    /**
     * Disable two-factor authentication for the user.
     *
     * @OA\Post(
     *     path="/api/auth/2fa/disable",
     *     summary="Disable two-factor authentication",
     *     description="Disables 2FA for the authenticated user after verifying the provided 2FA code.",
     *     operationId="disableTwoFactor",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"twofa_code"},
     *             @OA\Property(property="twofa_code", type="string", example="123456", description="Current 2FA code from the authenticator app")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=204,
     *         description="Two-factor authentication disabled successfully, no content"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid or missing 2FA code, or 2FA not enabled",
     *         @OA\JsonContent(
     *     oneOf={
     *       @OA\Schema(
     *         required={"message","code"},
     *         @OA\Property(property="message", type="string", example="Two-factor authentication is not enabled"),
     *         @OA\Property(property="code",    type="string", example="twofa_not_enabled")
     *       ),
     *       @OA\Schema(
     *         required={"message","code"},
     *         @OA\Property(property="message", type="string", example="Invalid two-factor authentication code"),
     *         @OA\Property(property="code",    type="string", example="twofa_invalid_code")
     *       )
     *     }
     *   )
     * ),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=422, ref="#/components/responses/ValidationError"),
     *     @OA\Response(response=500, ref="#/components/responses/InternalError")
     * )
     */
    public function disableTwoFactor(DisableTwoFactorRequest $request): JsonResponse
    {
        $this->authService->disableTwoFactor(
            auth()->user(),
            $request->input('twofa_code')
        );

        return response()->json(null, 204);
    }
}
