<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Validator;
use App\Models\User;
use Illuminate\Http\JsonResponse;

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
     * Update the authenticated user's name.
     *
     * @OA\Put(
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
}
