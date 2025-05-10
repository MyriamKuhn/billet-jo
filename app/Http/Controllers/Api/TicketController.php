<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TicketUserRequest;
use App\Http\Resources\TicketResource;
use App\Http\Requests\TicketIndexRequest;
use App\Services\TicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class TicketController extends Controller
{
    protected TicketService $ticketService;

    public function __construct(TicketService $ticketService)
    {
        $this->ticketService = $ticketService;
    }

    /**
     * Get a filtered, paginated list of all tickets (admin only).
     *
     * @OA\Get(
     *   path="/api/tickets",
     *   summary="Retrieve a filtered, paginated list of all tickets (admin only)",
     *   description="This endpoint retrieves a list of all tickets with optional filters and pagination. It is intended for admin users only.",
     *   tags={"Tickets"},
     *   security={{"bearerAuth":{}}},
     *
     *   @OA\Parameter(name="status",      in="query", description="Filter by ticket status",          required=false, @OA\Schema(type="string", enum={"issued","used","refunded","cancelled"})),
     *   @OA\Parameter(name="user_id",     in="query", description="Filter by user ID",                  required=false, @OA\Schema(type="integer", format="int64")),
     *   @OA\Parameter(name="user_email",  in="query", description="Filter by user email",               required=false, @OA\Schema(type="string", format="email")),
     *   @OA\Parameter(name="created_from",in="query", description="Created on or after (YYYY-MM-DD)",  required=false, @OA\Schema(type="string", format="date")),
     *   @OA\Parameter(name="created_to",  in="query", description="Created on or before (YYYY-MM-DD)", required=false, @OA\Schema(type="string", format="date")),
     *   @OA\Parameter(name="updated_from",in="query", description="Updated on or after (YYYY-MM-DD)",  required=false, @OA\Schema(type="string", format="date")),
     *   @OA\Parameter(name="updated_to",  in="query", description="Updated on or before (YYYY-MM-DD)", required=false, @OA\Schema(type="string", format="date")),
     *   @OA\Parameter(name="used_from",   in="query", description="Used on or after (YYYY-MM-DD)",     required=false, @OA\Schema(type="string", format="date")),
     *   @OA\Parameter(name="used_to",     in="query", description="Used on or before (YYYY-MM-DD)",    required=false, @OA\Schema(type="string", format="date")),
     *   @OA\Parameter(name="refunded_from",in="query",description="Refunded on or after (YYYY-MM-DD)", required=false,@OA\Schema(type="string", format="date")),
     *   @OA\Parameter(name="refunded_to", in="query", description="Refunded on or before (YYYY-MM-DD)",required=false,@OA\Schema(type="string", format="date")),
     *   @OA\Parameter(name="cancelled_from",in="query",description="Cancelled on or after (YYYY-MM-DD)",required=false,@OA\Schema(type="string", format="date")),
     *   @OA\Parameter(name="cancelled_to", in="query", description="Cancelled on or before (YYYY-MM-DD)",required=false,@OA\Schema(type="string", format="date")),
     *   @OA\Parameter(name="per_page",    in="query", description="Items per page (1–100)",             required=false, @OA\Schema(type="integer", default=25, minimum=1, maximum=100)),
     *
     *   @OA\Response(
     *     response=200,
     *     description="Successful response",
     *     @OA\JsonContent(
     *       @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/TicketResource")),
     *       @OA\Property(property="meta", type="object",
     *         @OA\Property(property="current_page", type="integer", example=1),
     *         @OA\Property(property="last_page",    type="integer", example=10),
     *         @OA\Property(property="per_page",     type="integer", example=25),
     *         @OA\Property(property="total",        type="integer", example=250)
     *       )
     *     )
     *   ),
     *   @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *   @OA\Response(response=403, ref="#/components/responses/Forbidden"),
     *   @OA\Response(response=404, ref="#/components/responses/NotFound"),
     *   @OA\Response(response=422, ref="#/components/responses/ValidationError"),
     *   @OA\Response(response=500, ref="#/components/responses/InternalError")
     * )
     *
     * @param TicketIndexRequest $request
     */
    public function index(TicketIndexRequest $request)
    {
        $filters   = $request->validatedFilters();

        $paginated = $this->ticketService->getFilteredTickets($filters);

        return response()->json([
            'data' => TicketResource::collection($paginated),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ], 200);
    }

    /**
     * @OA\Get(
     *   path="/api/tickets/user",
     *   summary="List the authenticated user's tickets, paginated",
     *   description="This endpoint retrieves a paginated list of tickets for the authenticated user. It supports optional filtering by product name.",
     *   tags={"User","Tickets"},
     *   security={{"bearerAuth":{}}},
     *
     *   @OA\Parameter(
     *     name="q",
     *     in="query",
     *     description="Search by product name",
     *     @OA\Schema(type="string")
     *   ),
     *   @OA\Parameter(
     *     name="per_page",
     *     in="query",
     *     description="Items per page (1–100)",
     *     @OA\Schema(type="integer", default=25, minimum=1, maximum=100)
     *   ),
     *
     *   @OA\Response(
     *     response=200,
     *     description="Successful response",
     *     @OA\JsonContent(
     *       @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/TicketResource")),
     *       @OA\Property(property="meta", type="object",
     *         @OA\Property(property="current_page", type="integer"),
     *         @OA\Property(property="last_page",    type="integer"),
     *         @OA\Property(property="per_page",     type="integer"),
     *         @OA\Property(property="total",        type="integer")
     *       )
     *     )
     *   ),
     *   @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *   @OA\Response(response=403, ref="#/components/responses/Forbidden"),
     *   @OA\Response(response=404, ref="#/components/responses/NotFound"),
     *   @OA\Response(response=422, ref="#/components/responses/ValidationError"),
     *   @OA\Response(response=500, ref="#/components/responses/InternalError"),
     * )
     */
    public function userTickets(TicketUserRequest $request)
    {
        $filters   = $request->validatedFilters();
        $userId    = $request->user()->id;
        $paginated = $this->ticketService->getUserTickets($userId, $filters);

        return Response::json([
            'data' => TicketResource::collection($paginated),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ],200);
    }
}
