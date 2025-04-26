<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Http;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\JsonResponse;

class UserController extends Controller
{
    /**
     * Register a new user.
     *
     * @OA\Post(
     *     path="/api/user/register",
     *     summary="Register a new user",
     *     description="Allows a user to create an account. Requires a secure password (at least 15 characters, with uppercase letters, lowercase letters, numbers, and symbols), validation via Google reCAPTCHA to prevent abuse, and email confirmation after registration. Account creation attempts are protected by throttling to prevent spam. A verification email is sent to validate the user's email address. The API also protects against unauthorized access with a restrictive CORS configuration.",
     *     operationId="registerUser",
     *     tags={"Users"},
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
     *             @OA\Property(property="message", type="string", example="Captcha verification failed."),
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
     *             @OA\Property(property="message", type="string", example="Too many attempts. Please try again in 60 seconds.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal or database error.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="An internal error occurred. Please try again later."),
     *             @OA\Property(property="errors", type="string", example="Database error. Please try again.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Access denied."),
     *             @OA\Property(property="errors", type="string", example="You do not have the necessary permissions.")
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
     *         response=405,
     *         description="Not allowed",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Method not allowed."),
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
                'message' => __('account_created')
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation error', [
                'errors' => $e->validator->errors(),
                'input' => $request->all(),
            ]);

            return response()->json([
                'status'=> 'error',
                'message' => __('validation.validation_failed'),
                'errors' => $e->validator->errors(),
            ], 422);

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Database erreor', [
                'error' => $e->getMessage(),
                'input' => $request->all(),
            ]);

            return response()->json([
                'status'=> 'error',
                'message' => __('validation.database_error'),
                'errors' => $e->getMessage(),
            ], 500);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            Log::error('Access denied', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status'=> 'error',
                'message' => __('validation.access_denied'),
                'errors' => $e->getMessage(),
            ], 403);

        } catch (\Illuminate\Http\Exceptions\ThrottleRequestsException $e) {
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
}
