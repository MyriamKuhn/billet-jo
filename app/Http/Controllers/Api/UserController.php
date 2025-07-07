<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use App\Services\UserService;
use App\Http\Requests\UpdateUserNameRequest;
use App\Http\Requests\AdminUpdateUserRequest;
use App\Http\Requests\StoreEmployeeRequest;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private UserService $userService) {}

    /**
     * Retrieve the list of all users paginated and filtered.
     *
     * @OA\Get(
     *     path="/api/users",
     *     summary="Retrieve all users (admin only)",
     *     description="Returns a paginated list of users, filterable by firstname, lastname, email and role.",
     *     tags={"Users"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(name="page", in="query", description="Page number", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", description="Items per page", @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="firstname", in="query", description="Search by firstname (like)", @OA\Schema(type="string")),
     *     @OA\Parameter(name="lastname",  in="query", description="Search by name (like)",    @OA\Schema(type="string")),
     *     @OA\Parameter(name="email",     in="query", description="Search by email (exact)",  @OA\Schema(type="string")),
     *     @OA\Parameter(name="role",      in="query", description="Filter by roles",            @OA\Schema(type="string", enum={"admin","employee","user"})),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Users retrieved successfully",
     *         @OA\JsonContent(
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
     *                 ),
     *          @OA\Property(property="meta", type="object",
     *              @OA\Property(property="current_page", type="integer", example=1),
     *              @OA\Property(property="last_page",    type="integer", example=10),
     *              @OA\Property(property="per_page",     type="integer", example=15),
     *              @OA\Property(property="total",        type="integer", example=150)
     *          ),
     *          @OA\Property(property="links", type="object",
     *              @OA\Property(property="first", type="string", example="…?page=1&per_page=15"),
     *              @OA\Property(property="last",  type="string", example="…?page=10&per_page=15"),
     *              @OA\Property(property="prev",  type="string|null", example="…?page=1&per_page=15"),
     *              @OA\Property(property="next",  type="string|null", example="…?page=2&per_page=15")
     *           )
     *         )
     *      )
     *     ),
     *
     *     @OA\Response(response=403, ref="#/components/responses/Forbidden"),
     *     @OA\Response(response=500, ref="#/components/responses/InternalError")
     * )
     *
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['firstname','lastname','email','role']);
        $page    = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 15);

        $paginator = $this->userService
                        ->listAllUsers(auth()->user(), $filters, $perPage);

        return response()->json([
            'data'  => ['users' => $paginator->items()],
            'meta'  => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last'  => $paginator->url($paginator->lastPage()),
                'prev'  => $paginator->previousPageUrl(),
                'next'  => $paginator->nextPageUrl(),
            ],
        ], 200);
    }

    /**
     * Get a user’s basic information.
     *
     * @OA\Get(
     *     path="/api/users/{user}",
     *     summary="Get user details by ID (admin or employee only)",
     *     description="Returns firstname, lastname and email of the specified user. Requires admin or employee privileges.",
     *     operationId="getUserById",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="user",
     *         in="path",
     *         description="ID of the user to retrieve",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64", example=42)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="User retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="user",
     *                 type="object",
     *                 @OA\Property(property="firstname", type="string", example="Jane"),
     *                 @OA\Property(property="lastname",  type="string", example="Smith"),
     *                 @OA\Property(property="email",     type="string", format="email", example="jane.smith@example.com")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=403, ref="#/components/responses/Forbidden"),
     *     @OA\Response(response=404, ref="#/components/responses/NotFound"),
     *     @OA\Response(response=500, ref="#/components/responses/InternalError")
     * )
     *
     * @param User $user
     * @return JsonResponse
     */
    public function show(User $user): JsonResponse
    {
        $data = $this->userService->getUserInfo(auth()->user(), $user);

        return response()->json([
            'user'   => $data,
        ], 200);
    }

    /**
     * Update the authenticated user’s firstname and lastname.
     *
     * @OA\Patch(
     *     path="/api/users/me",
     *     summary="Modify current user firstname and lastname",
     *     description="Allows an authenticated user to update their firstname and lastname.",
     *     operationId="updateCurrentUserName",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"firstname","lastname"},
     *             @OA\Property(property="firstname", type="string", example="Alice"),
     *             @OA\Property(property="lastname",  type="string", example="Dupont")
     *         )
     *     ),
     *
     *     @OA\Response(response=204, description="Name updated successfully, no content"),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=422, ref="#/components/responses/ValidationError"),
     *     @OA\Response(response=500, ref="#/components/responses/InternalError")
     * )
     */
    public function updateSelf(UpdateUserNameRequest $request): JsonResponse
    {
        $user = auth()->user();
        $result = $this->userService->updateName($user, $request->validated());

        return response()->json(null, 204);
    }

    /**
     * @OA\Patch(
     *     path="/api/users/{user}",
     *     summary="Modify a user's details (admin only)",
     *     description="Allows an administrator to update a user's status, 2FA setting, name, email, role or verified email.",
     *     operationId="updateUserByAdmin",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="user",
     *         in="path",
     *         description="ID of the user to update",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64", example=123)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="is_active",     type="boolean", example=true),
     *             @OA\Property(property="twofa_enabled", type="boolean", example=false),
     *             @OA\Property(property="firstname",     type="string",  example="Alice"),
     *             @OA\Property(property="lastname",      type="string",  example="Dupont"),
     *             @OA\Property(property="email",         type="string",  format="email", example="alice@example.com"),
     *             @OA\Property(property="role",          type="string",  enum={"admin","employee","user"}, example="employee"),
     *             @OA\Property(property="verify_email",  type="boolean", example=true)
     *         )
     *     ),
     *
     *     @OA\Response(response=204, description="User updated successfully, no content"),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=403, ref="#/components/responses/Forbidden"),
     *     @OA\Response(response=422, ref="#/components/responses/ValidationError"),
     *     @OA\Response(response=500, ref="#/components/responses/InternalError")
     * )
     *
     * @param AdminUpdateUserRequest $request
     * @param User $user
     * @return JsonResponse
     */
    public function update(AdminUpdateUserRequest $request, User $user): JsonResponse
    {
        $result = $this->userService->updateUserByAdmin(
            auth()->user(),
            $user,
            $request->validated()
        );

        return response()->json(null, 204);
    }

    /**
     * Check if a user has a pending email update.
     *
     * @OA\Get(
     *     path="/api/users/email/{user}",
     *     summary="Check pending email change for a user (admin only)",
     *     description="Admin-only. Returns the pending email update data for the specified user, or null if none.",
     *     operationId="checkEmailUpdate",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="user",
     *         in="path",
     *         description="ID of the user to check",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Pending email update retrieved or none",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 nullable=true,
     *                 oneOf={
     *           @OA\Schema(
     *             type="object",
     *             title="EmailUpdate",
     *             @OA\Property(property="old_email",  type="string", format="email", example="old@example.com"),
     *             @OA\Property(property="new_email",  type="string", format="email", example="new@example.com"),
     *             @OA\Property(property="created_at", type="string", format="date-time", example="2025-05-02T12:00:00Z"),
     *             @OA\Property(property="updated_at", type="string", format="date-time", example="2025-05-03T12:00:00Z")
     *           ),
     *           @OA\Schema(type="null")
     *         }
     *       ),
     *       @OA\Property(property="message", type="string", example="Checked pending email update")
     *     )
     *   ),
     *
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=403, ref="#/components/responses/Forbidden"),
     *     @OA\Response(response=500, ref="#/components/responses/InternalError")
     * )
     */
    public function checkEmailUpdate(User $user): JsonResponse
    {
        $actor = auth()->user();
        $data  = $this->userService->checkEmailUpdate($actor, $user);

        return response()->json([
            'data'    => $data,
            'message' => $data ? 'Pending email update retrieved' : 'Pending email update not retrieved',
        ], 200);
    }

    /**
     * Create a new employee user (admin only).
     *
     * @OA\Post(
     *     path="/api/users/employees",
     *     summary="Create a new employee (admin only)",
     *     description="Allows an administrator to create a new employee account.
The new user is immediately active and email‐verified. Password must meet security requirements.",
     *     operationId="createEmployee",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"firstname","lastname","email","password","password_confirmation"},
     *             @OA\Property(property="firstname",             type="string", example="Alice"),
     *             @OA\Property(property="lastname",              type="string", example="Dupont"),
     *             @OA\Property(property="email",                 type="string", format="email", example="alice@example.com"),
     *             @OA\Property(property="password",              type="string", format="password", example="Str0ngP@ssword2025!"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="Str0ngP@ssword2025!")
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Employee created successfully, no content"),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=403, ref="#/components/responses/Forbidden"),
     *     @OA\Response(response=422, ref="#/components/responses/ValidationError"),
     *     @OA\Response(response=500, ref="#/components/responses/InternalError")
     * )
     *
     * @param StoreEmployeeRequest $request
     * @return JsonResponse
     */
    public function storeEmployee(StoreEmployeeRequest $request): JsonResponse
    {
        $user = $this->userService->createEmployee(
            auth()->user(),
            $request->validated()
        );

        return response()->json(null, 201);
    }

    /**
     * Get the authenticated user's profile.
     *
     * @OA\Get(
     *     path="/api/users/me",
     *     summary="Get current user profile",
     *     description="Returns the authenticated user's firstname, lastname, email, and 2FA status.",
     *     operationId="getCurrentUserProfile",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Profile retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="user",
     *                 type="object",
     *                 @OA\Property(property="firstname",     type="string", example="John"),
     *                 @OA\Property(property="lastname",      type="string", example="Doe"),
     *                 @OA\Property(property="email",         type="string", format="email", example="john.doe@example.com"),
     *                 @OA\Property(property="twofa_enabled", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=500, ref="#/components/responses/InternalError")
     * )
     */
    public function showSelf(): JsonResponse
    {
        $user    = auth()->user();
        $profile = $this->userService->getSelfInfo($user);

        return response()->json([
            'user'   => $profile,
        ], 200);
    }
}
