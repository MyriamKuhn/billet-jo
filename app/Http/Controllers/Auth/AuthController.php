<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Http;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\JsonResponse;
use PragmaRX\Google2FA\Google2FA;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Auth\Access\AuthorizationException;

class AuthController extends Controller
{
        /**
     * Register a new user.
     *
     * @OA\Post(
     *     path="/api/auth/register",
     *     summary="Register a new user",
     *     description="Allows a user to create an account. Requires a secure password (at least 15 characters, with uppercase letters, lowercase letters, numbers, and symbols), validation via Google reCAPTCHA to prevent abuse, and email confirmation after registration. Account creation attempts are protected by throttling to prevent spam. A verification email is sent to validate the user's email address. The API also protects against unauthorized access with a restrictive CORS configuration.",
     *     operationId="registerUser",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"firstname", "lastname", "email", "password", "password_confirmation", "captcha_token"},
     *             @OA\Property(property="firstname", type="string", maxLength=100, example="John"),
     *             @OA\Property(property="lastname", type="string", maxLength=100, example="Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="MegaGreatPassword@2025"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="MegaGreatPassword@2025"),
     *             @OA\Property(property="captcha_token", type="string", example="03AGdBq27...")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User created successfull. Verification Email sended.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Registration successful. Please check your emails.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or captcha verification failed.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Validation error. Please check your data."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 additionalProperties=@OA\Property(type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Too many attempts. Please try again later.",
     *         @OA\Header(
     *             header="Retry-After",
     *             description="Time to wait before retrying (in seconds)",
     *             @OA\Schema(type="integer", example=60)
     *         ),
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Too many attempts. Please try again later.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal or database error.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="An unknown error occurred. Please try again."),
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Access denied. You do not have the necessary permissions."),
     *             @OA\Property(property="errors", type="string", example="You do not have the necessary permissions.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Not authenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="You must be logged in to perform this action.")
     *         )
     *     )
     * )
     */
    public function register(Request $request): JsonResponse
    {
        try {
            // Validation of the datas from the request
            $validated = $request->validate([
                'firstname' => 'required|string|max:100',
                'lastname' => 'required|string|max:100',
                'email' => 'required|email|max:100|unique:users,email',
                'password' => [
                    'required',
                    'confirmed', // Doit correspondre à password_confirmation
                    Password::min(15)
                        ->mixedCase()
                        ->letters()
                        ->numbers()
                        ->symbols(),
                ],
                'captcha_token' => 'required|string',
            ]);

            // Verification of the captcha only on the production environment
            // In local, we don't need to verify the captcha
            if (app()->environment('production')) {
                if (!$this->verifyCaptcha($validated['captcha_token'])) {
                    return response()->json([
                        'status' => 'error',
                        'message' => __('validation.captcha_failed')
                    ], 422);
                }
            }

            // Creation of the user
            $user = User::create([
                'firstname' => $validated['firstname'],
                'lastname' => $validated['lastname'],
                'email' => $validated['email'],
                'password_hash' => Hash::make($validated['password']),
                'role' => 'user', // All users are created with the role 'user'
            ]);

            // Send the verification email
            event(new Registered($user));

            return response()->json([
                'status'=> 'success',
                'message' => __('validation.account_created')
            ], 201);

        } catch (ValidationException $e) {
            Log::error('Validation error', [
                'errors' => $e->validator->errors(),
                'input' => $request->all(),
            ]);

            return response()->json([
                'status'=> 'error',
                'message' => __('validation.validation_failed'),
                'errors' => $e->validator->errors(),
            ], 422);

        } catch (QueryException $e) {
            Log::error('Database error', [
                'error' => $e->getMessage(),
                'input' => $request->all(),
            ]);

            return response()->json([
                'status'=> 'error',
                'message' => __('validation.unknown_error'),
            ], 500);

        } catch (AuthorizationException $e) {
            Log::error('Access denied', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status'=> 'error',
                'message' => __('validation.access_denied'),
                'errors' => $e->getMessage(),
            ], 403);

        } catch (ThrottleRequestsException $e) {
            Log::warning('Too many attempts', [
                'retry_after' => $e->getHeaders()['Retry-After'],
            ]);

            return response()->json([
                'status'=> 'error',
                'message' => __('validation.throttling_error'),
            ], 429, ['Retry-After' => $e->getHeaders()['Retry-After']]);

        } catch (\Exception $e) {
            Log::error('Unknown error', [
                'error' => $e->getMessage(),
                'input' => $request->all(),
            ]);

            return response()->json([
                'status'=> 'error',
                'message' => __('validation.unknown_error'),
            ], 500);
        }
    }

    /**
     * Verify the captcha token with Google reCAPTCHA.
     *
     * @param string $token
     * @return bool
     */
    private function verifyCaptcha(string $token): bool
    {
        try {
            $response = Http::withOptions([
                'verify' => app()->environment('production'),  // Deactivate SSL verification in local environment
            ])->asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => env('RECAPTCHA_SECRET_KEY'),
                'response' => $token,
            ]);

            $data = $response->json();

            // Check if the response is valid and contains success
            if ($data['success'] ?? false) {
                return true;
            } else {
                Log::warning('Captcha failed', ['data' => $data]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Error during captcha verification', [
                'error' => $e->getMessage(),
                'token' => $token
            ]);
            return false; // In case of an error, we assume the captcha is invalid
        }
    }

    /**
     * Login a user.
     *
     * @OA\Post(
     *     path="/api/auth/login",
     *     summary="Login user",
     *     description="Allows a user to log in with their email and password, and receive an authentication token. Admins can disable accounts, and users can choose to remember their login or activate two-factor authentication (2FA).",
     *     operationId="login",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Login credentials (email and password are mandatory)",
     *         @OA\JsonContent(
     *             required={"email", "password", "remember"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="MyGreatPassword@123"),
     *             @OA\Property(property="remember", type="boolean", example=true, description="Optional: Whether the user wants to stay logged in"),
     *             @OA\Property(property="twofa_code", type="string", example="123456", description="Optional: Two-factor authentication code")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Connection successful. Returns the authentication token and user information.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Login successful."),
     *             @OA\Property(property="token", type="string", example="token_example"),
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="firstname", type="string", example="John"),
     *                 @OA\Property(property="lastname", type="string", example="Doe"),
     *                 @OA\Property(property="email", type="string", example="user@example.com"),
     *                 @OA\Property(property="role", type="string", example="user"),
     *                 @OA\Property(property="twofa_enabled", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error (email not verified or 2FA code not valid)",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Validation error. Please check your data."),
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid credentials.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Invalid credentials. Please check your email address and password.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Account disabled or access denied.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Your account has been disabled. Please contact support.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Too many attempts",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Too many attempts. Please try again later.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error or database error.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="An unknown error occurred. Please try again.")
     *         )
     *     )
     * )
     */
    public function login(Request $request): JsonResponse
    {
        try {
            // Validate the request data
            $credentials = $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
                'remember' => 'boolean',
                'twofa_code' => 'nullable|string',
            ]);

            // Check if the user exists and the password is correct
            $user = User::where('email', $credentials['email'])->first();
            if (!$user || !Hash::check($credentials['password'], $user->password_hash)) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('validation.invalid_credentials'),
                ], 401);
            }

            // Check if the user is active
            if (!$user->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('validation.account_disabled'),
                ], 403);
            }

            // Check if the user has validated their email
            if (!$user->hasVerifiedEmail()) {
                $user->sendEmailVerificationNotification();

                return response()->json([
                    'status' => 'error',
                    'message' => __('validation.email_not_verified'),
                ], 400);
            }

            // If the user has 2FA enabled, check if a 2FA code is provided
            if ($user->twofa_enabled && !$credentials['twofa_code']) {
                return response()->json([
                    'status' => 'error',
                    'message' => __('validation.twofa_required'),
                ], 400);
            }

            // If the 2FA code is provided, verify it
            if ($user->twofa_enabled && $credentials['twofa_code']) {
                $google2fa = app('pragmarx.google2fa');

                // Verify the 2FA code
                if (!$google2fa->verifyKey($user->twofa_secret, $credentials['twofa_code'])) {
                    return response()->json([
                        'status' => 'error',
                        'message' => __('validation.twofa_invalid'),
                    ], 400);
                }
            }

            // If the user has Remember Me checked, create a token that lasts for 1 week
            $token = $credentials['remember']
                ? $user->createToken('auth_token', ['*'], now()->addWeeks(1))->plainTextToken
                : $user->createToken('auth_token')->plainTextToken;

            // Return the token and user information
            return response()->json([
                'status' => 'success',
                'message' => __('validation.login_success'),
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'firstname' => $user->firstname,
                    'lastname' => $user->lastname,
                    'email' => $user->email,
                    'role' => $user->role,
                    'twofa_enabled' => $user->twofa_enabled,
                ],
            ], 200);

        } catch (ValidationException $e) {
            Log::error('Validation error', [
                'errors' => $e->validator->errors(),
                'input' => $request->all(),
            ]);

            return response()->json([
                'status'=> 'error',
                'message' => __('validation.validation_failed'),
                'errors' => $e->validator->errors(),
            ], 422);

        } catch (QueryException $e) {
            Log::error('Database error', [
                'error' => $e->getMessage(),
                'input' => $request->all(),
            ]);

            return response()->json([
                'status'=> 'error',
                'message' => __('validation.unknown_error'),
            ], 500);

        } catch (AuthorizationException $e) {
            Log::error('Access denied', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status'=> 'error',
                'message' => __('validation.access_denied'),
                'errors' => $e->getMessage(),
            ], 403);

        } catch (ThrottleRequestsException $e) {
            Log::warning('Too many attempts', [
                'retry_after' => $e->getHeaders()['Retry-After'],
            ]);

            return response()->json([
                'status'=> 'error',
                'message' => __('validation.throttling_error'),
            ], 429, ['Retry-After' => $e->getHeaders()['Retry-After']]);

        } catch (\Exception $e) {
            Log::error('Unknown error', [
                'error' => $e->getMessage(),
                'input' => $request->all(),
            ]);

            return response()->json([
                'status'=> 'error',
                'message' => __('validation.unknown_error'),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/auth/enable2FA",
     *     summary="Enable two-factor authentication for the user",
     *     description="Generates a new secret key for two-factor authentication and returns the QR code URL.",
     *     operationId="enableTwoFactor",
     *     tags={"Authentication"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Two-factor authentication enabled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="secret", type="string", description="Generated secret key for 2FA", example="JBSWY3DPEHPK3PXP"),
     *             @OA\Property(property="qrCodeUrl", type="string", description="URL to generate QR code for 2FA", example="otpauth://totp/Example%3Auser%40example.com?secret=JBSWY3DPEHPK3PXP&issuer=Example")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Authentication error: User must be logged in",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="You must be logged in to perform this action.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error: Input validation failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Validation error. Please check your data."),
     *             @OA\Property(property="errors", type="object", additionalProperties={@OA\Property(type="array", items=@OA\Property(type="string"))})
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied: User does not have the necessary permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Access denied. You do not have the necessary permissions.")
     *         )
     *    ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="An unknown error occurred. Please try again.")
     *         )
     *     )
     * )
     */
    public function enableTwoFactor(Request $request): JsonResponse
    {
        try {
            $google2fa = new Google2FA();

            // Generate a new secret key
            $secret = $google2fa->generateSecretKey();

            // Store the secret key in the database
            $user = $request->user();
            $user->twofa_secret = $secret;
            $user->twofa_enabled = true;  // Activer la 2FA
            $user->save();

            // Generate the QR code URL
            $qrCodeUrl = $google2fa->getQRCodeUrl(
                config('app.name'),
                $user->email,
                $secret
            );

            return response()->json([
                'secret' => $secret,
                'qrCodeUrl' => $qrCodeUrl,
            ]);

        } catch (AuthenticationException $e) {
            Log::error('Authentication error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => __('validation.must_be_connected'),
            ], 401);

        } catch (ValidationException $e) {
            Log::error('Validation error', [
                'errors' => $e->validator->errors(),
                'input' => $request->all(),
            ]);

            return response()->json([
                'status'=> 'error',
                'message' => __('validation.validation_failed'),
                'errors' => $e->validator->errors(),
            ], 422);

        } catch (QueryException $e) {
            Log::error('Database error', [
                'error' => $e->getMessage(),
                'input' => $request->all(),
            ]);

            return response()->json([
                'status'=> 'error',
                'message' => __('validation.unknown_error'),
            ], 500);

        } catch (AuthorizationException $e) {
            Log::error('Access denied', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status'=> 'error',
                'message' => __('validation.access_denied'),
                'errors' => $e->getMessage(),
            ], 403);

        } catch (\Exception $e) {
            Log::error('Error enabling 2FA', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => __('validation.unknown_error'),
            ], 500);
        }
    }

    /**
     * Logout a user.
     *
     * @OA\Post(
     *     path="/api/auth/logout",
     *     summary="Logout a user and invalidate their current access token",
     *     description="Logs out the user by deleting their current active token.",
     *     operationId="logoutUser",
     *     tags={"Authentication"},
     *     security={{
     *         "bearerAuth": {}
     *     }},
     *     @OA\Response(
     *         response=200,
     *         description="Successfully logged out.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Successfully logged out.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="No active token found.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="You are not authenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Unknown error or Database error occurred while logging out.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Unknown error occurred.")
     *         )
     *     ),
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Check if the user is authenticated
            if (!$user->currentAccessToken()) {
            return response()->json([
                'status' => 'error',
                'message' => __('validation.no_active_token'),
            ], 400);
            }

            // Delete the current access token
            $user->currentAccessToken()->delete();

            return response()->json([
                'status' => 'success',
                'message' => __('validation.logout_success'),
            ], 200);

        } catch (QueryException $e) {
            Log::error('Database error', [
                'error' => $e->getMessage(),
                'input' => $request->all(),
            ]);

            return response()->json([
                'status'=> 'error',
                'message' => __('validation.unknown_error'),
            ], 500);

        } catch (\Exception $e) {
            Log::error('Logout error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => __('validation.unknown_error'),
            ], 500);
        }
    }
}
