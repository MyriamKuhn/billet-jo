<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Validator;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use App\Models\EmailUpdate;

class UserController extends Controller
{
    /**
     * Retrieve the list of all users.
     *
     * @OA\Get(
     *     path="/api/user/all",
     *     summary="Retrieve the list of all users",
     *     description="This endpoint returns a list of all users, accessible only to admins.",
     *     tags={"Users"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successfully retrieved the users list",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="users", type="array",
     *                     @OA\Items(
     *                           @OA\Property(property="id", type="integer", example=1),
     *                           @OA\Property(property="firstname", type="string", example="John"),
     *                           @OA\Property(property="lastname", type="string", example="Doe"),
     *                           @OA\Property(property="email", type="string", example="john.doe@example.com"),
     *                           @OA\Property(property="created_at", type="string", format="date-time", example="2025-05-01T12:00:00Z"),
     *                           @OA\Property(property="updated_at", type="string", format="date-time", example="2025-05-02T14:30:00Z"),
     *                           @OA\Property(property="role", type="string", enum={"admin", "employee", "user"}, example="admin"),
     *                           @OA\Property(property="twofa_enabled", type="boolean", example=true),
     *                           @OA\Property(property="email_verified_at", type="string", format="date-time", example="2025-05-01T12:00:00Z"),
     *                           @OA\Property(property="is_active", type="boolean", example=true),
     *                       )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden: User is not an admin or unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="error", type="string", example="You are not authorized to perform this action.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="error", type="string", example="An unknown error occurred")
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        try {
            $user = auth()->user();

            if (!$user || !$user->role->isAdmin()) {
                return response()->json([
                    'status' => 'error',
                    'error' => __('user.error_user_unauthorized'),
                ], 403);
            }

            $users = User::select(
                'id',
                'firstname',
                'lastname',
                'email',
                'created_at',
                'updated_at',
                'role',
                'twofa_enabled',
                'email_verified_at',
                'is_active'
            )->get();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'users' => $users,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching users list', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'error' => __('validation.error_unknown'),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/user/{id}",
     *     summary="Retrieve a specific user by ID",
     *     description="Fetch details of a user by ID. Only admins and employees can access this.",
     *     operationId="getUserById",
     *     tags={"Users"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the user to retrieve",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(
     *                 property="user",
     *                 type="object",
     *                 @OA\Property(property="firstname", type="string", example="John"),
     *                 @OA\Property(property="lastname", type="string", example="Doe"),
     *                 @OA\Property(property="email", type="string", example="john.doe@example.com")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized, only admins and employees are allowed",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="error", type="string", example="You are not authorized to perform this action.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="error", type="string", example="User not found")
     *         )
     *     )
     * )
     */
    public function show($id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);

            $authUser = auth()->user();

            if (!($authUser->role->isAdmin() || $authUser->role->isEmployee())) {
                return response()->json([
                    'status' => 'error',
                    'error' => __('user.error_user_unauthorized'),
                ], 403);
            }

            return response()->json([
                'status' => 'success',
                'user' => [
                    'firstname' => $user->firstname,
                    'lastname' => $user->lastname,
                    'email' => $user->email,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching user details', [
                'error' => $e->getMessage(),
            ]);

            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'status' => 'error',
                    'error' => __('user.error_user_not_found'),
                ], 404);
            }

            return response()->json([
                'status' => 'error',
                'error' => __('validation.error_unknown'),
            ], 500);
        }
    }

    /**
     * Update the authenticated user's name.
     *
     * @OA\Patch(
     *     path="/api/user/update",
     *     summary="Change user's first and last name",
     *     description="Allows the authenticated user to update their firstname and lastname.",
     *     operationId="updateUserName",
     *     tags={"Users"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"firstname", "lastname"},
     *             @OA\Property(property="firstname", type="string", maxLength=255, example="Myriam"),
     *             @OA\Property(property="lastname", type="string", maxLength=255, example="Kühn")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User name updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="User updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="user",
     *                     type="object",
     *                     @OA\Property(property="firstname", type="string", example="Myriam"),
     *                     @OA\Property(property="lastname", type="string", example="Kühn")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="error", type="string", example="Unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="firstname",
     *                     type="array",
     *                     @OA\Items(type="string", example="The firstname field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="lastname",
     *                     type="array",
     *                     @OA\Items(type="string", example="The lastname field is required.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="error", type="string", example="An unknown error occurred")
     *         )
     *     )
     * )
     */
    public function updateName(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'firstname' => 'required|string|max:255',
                'lastname' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'errors' => $validator->errors()], 422);
            }

            $data = $validator->validated();

            $user = auth()->user();

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'errors' => __('validation.error_unauthorized'),
                ], 401);
            }

            // Update the user's name
            $user->update([
                'firstname' => $data['firstname'],
                'lastname' => $data['lastname'],
            ]);

            return response()->json([
                'status' => 'success',
                'message' => __('user.user_updated'),
                'data' => [
                    'user' => [
                        'firstname' => $user->firstname,
                        'lastname' => $user->lastname,
                    ],
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error updating user name', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'error' => __('validation.error_unknown'),
            ], 500);
        }
    }

    /**
     * Update user information by admin.
     *
     * @OA\Patch(
     *     path="/user/{user}",
     *     operationId="updateUserByAdmin",
     *     tags={"Users"},
     *     summary="Change user informations by admin",
     *     description="Allows an admin to update a user's information.",
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="user",
     *         in="path",
     *         required=true,
     *         description="ID of the user to update",
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="is_active", type="boolean", example=true),
     *             @OA\Property(property="twofa_enabled", type="boolean", example=false),
     *             @OA\Property(property="firstname", type="string", maxLength=255, example="Myriam"),
     *             @OA\Property(property="lastname", type="string", maxLength=255, example="Kühn"),
     *             @OA\Property(property="email", type="string", format="email", example="myriam@example.com"),
     *             @OA\Property(property="role", type="string", enum={"admin", "employee", "user"}, example="admin")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="User updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", type="object",
     *                     @OA\Property(property="firstname", type="string", example="Myriam"),
     *                     @OA\Property(property="lastname", type="string", example="Kühn"),
     *                     @OA\Property(property="email", type="string", format="email", example="myriam@example.com"),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="twofa_enabled", type="boolean", example=false),
     *                     @OA\Property(property="role", type="string", enum={"admin", "employee", "user"}, example="admin")
     *                 )
     *             ),
     *             @OA\Property(property="message", type="string", example="The user has been updated successfully.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized access",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="error", type="string", example="You are not authorized to perform this action.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="error", type="string", example="An unknown error occurred")
     *         )
     *     )
     * )
     */
    public function updateUserByAdmin(Request $request, User $user): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'is_active' => 'nullable|boolean',
                'twofa_enabled' => 'nullable|boolean',
                'firstname' => 'nullable|string|max:255',
                'lastname' => 'nullable|string|max:255',
                'email' => 'nullable|email|unique:users,email,' . $user->id,
                'role' => 'nullable|in:admin,employee,user',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $validated = $validator->validated();

            $authUser = auth()->user();

            if (!$authUser || !$authUser->role->isAdmin()) {
                return response()->json([
                    'status' => 'error',
                    'error' => __('user.error_user_unauthorized'),
                ], 403);
            }

            // Update is_active
            if (isset($validated['is_active'])) {
                $user->is_active = $validated['is_active'];
            }

            // Disable 2FA and reset twofa_secret
            if (isset($validated['twofa_enabled']) && !$validated['twofa_enabled']) {
                $user->twofa_enabled = false;
                $user->twofa_secret = null; // reset twofa_secret
            }

            // Update firstname, lastname, and email
            if (isset($validated['firstname'])) {
                $user->firstname = $validated['firstname'];
            }

            if (isset($validated['lastname'])) {
                $user->lastname = $validated['lastname'];
            }

            if (isset($validated['email'])) {
                $user->email = $validated['email'];
            }

            if (isset($validated['role'])) {
                $user->role = $validated['role'];
            }

            $user->save();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'user' => [
                        'firstname' => $user->firstname,
                        'lastname' => $user->lastname,
                        'email' => $user->email,
                        'is_active' => $user->is_active,
                        'twofa_enabled' => $user->twofa_enabled,
                        'role' => $user->role,
                    ],
                ],
                'message' => __('user.user_updated_admin'),
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error updating user by admin', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'error' => __('validation.error_unknown'),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/user/{user}/email-update",
     *     summary="Check if an email update exists for a user",
     *     description="Accessible only by admins. Returns the latest email update request for the given user if it exists.",
     *     tags={"Users"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(
     *         name="user",
     *         in="path",
     *         required=true,
     *         description="ID of the user to check for email update",
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Email update found or not found",
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     type="object",
     *                     @OA\Property(property="status", type="string", example="success"),
     *                     @OA\Property(property="data", type="object",
     *                         @OA\Property(property="old_email", type="string", format="email", example="old@example.com"),
     *                         @OA\Property(property="new_email", type="string", format="email", example="new@example.com"),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-05-01T10:00:00Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-05-01T10:30:00Z")
     *                     ),
     *                     @OA\Property(property="message", type="string", example="Email update found.")
     *                 ),
     *                 @OA\Schema(
     *                     type="object",
     *                     @OA\Property(property="status", type="string", example="success"),
     *                     @OA\Property(property="data", type="string", nullable=true, example=null),
     *                     @OA\Property(property="message", type="string", example="No email update found.")
     *                 )
     *             }
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized access (non-admin user)",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="error", type="string", example="You are not authorized to perform this action.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="error", type="string", example="An unknown error occurred.")
     *         )
     *     )
     * )
     */
    public function checkEmailUpdate(User $user): JsonResponse
    {
        try {
            $authUser = auth()->user();

            if (!$authUser || (!$authUser->role->isAdmin())) {
                return response()->json([
                    'status' => 'error',
                    'error' => __('user.error_user_unauthorized'),
                ], 403);
            }

            $emailUpdate = EmailUpdate::where('user_id', $user->id)->first();

            if (!$emailUpdate) {
                return response()->json([
                    'status' => 'success',
                    'data' => null,
                    'message' => __('user.no_email_update'),
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'old_email' => $emailUpdate->old_email,
                    'new_email' => $emailUpdate->new_email,
                    'created_at' => $emailUpdate->created_at,
                    'updated_at' => $emailUpdate->updated_at,
                ],
                'message' => __('user.email_update_found'),
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error checking email update', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'error' => __('validation.error_unknown'),
            ], 500);
        }
    }
}
