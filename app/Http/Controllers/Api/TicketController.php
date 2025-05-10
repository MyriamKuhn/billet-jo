<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TicketUserRequest;
use App\Http\Resources\TicketResource;
use App\Http\Requests\TicketIndexRequest;
use App\Services\TicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use App\Models\Ticket;
use App\Http\Requests\TicketStatusRequest;
use App\Http\Requests\TicketCreateRequest;
use App\Exceptions\TicketAlreadyProcessedException;
use App\Enums\TicketStatus;

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
     * List the authenticated user's tickets, paginated.
     *
     * @OA\Get(
     *   path="/api/tickets/user",
     *   summary="List the authenticated user's tickets, paginated",
     *   description="This endpoint retrieves a paginated list of tickets for the authenticated user. It supports optional filtering by product name.",
     *   tags={"Tickets"},
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
     *
     * @param TicketUserRequest $request
     * @return \Illuminate\Http\JsonResponse
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

    /**
     * Download an ticket PDF for the authenticated user.
     *
     * @OA\Get(
     *     path="/api/tickets/{filename}",
     *     summary="Download user ticket",
     *     description="Streams the ticket PDF for the logged-in user.",
     *     operationId="downloadTicket",
     *     tags={"Tickets"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="filename",
     *         in="path",
     *         required=true,
     *         description="Filename of the ticket PDF",
     *         @OA\Schema(type="string", example="ticket_5566.pdf")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="PDF file download",
     *         @OA\MediaType(
     *             mediaType="application/pdf"
     *         )
     *     ),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=404, ref="#/components/responses/NotFound"),
     *     @OA\Response(response=403, ref="#/components/responses/Forbidden"),
     *     @OA\Response(response=500, ref="#/components/responses/InternalError"),
     * )
     *
     * @param Request $request
     * @param string $filename
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function downloadTicket(Request $request, string $filename)
    {
        $ticket = Ticket::where('pdf_filename', $filename)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if (!Storage::disk('tickets')->exists($filename)) {
            abort(404, 'Ticket not found');
        }

        return Storage::disk('tickets')->download(
        $filename,
        $filename,
        ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * Download any ticket PDF as admin.
     *
     * @OA\Get(
     *     path="/api/tickets/admin/{filename}",
     *     summary="Download ticket (admin)",
     *     description="Allows an administrator to download any ticket PDF by filename.",
     *     operationId="adminDownloadTicket",
     *     tags={"Tickets"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="filename",
     *         in="path",
     *         required=true,
     *         description="Filename of the ticket PDF",
     *         @OA\Schema(type="string", example="ticket_5566.pdf")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="PDF file download",
     *         @OA\MediaType(mediaType="application/pdf")
     *     ),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=403, ref="#/components/responses/Forbidden"),
     *     @OA\Response(response=404, ref="#/components/responses/NotFound"),
     *     @OA\Response(response=500, ref="#/components/responses/InternalError"),
     * )
     *
     * @param Request $request
     * @param string $filename
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function downloadAdminTicket(Request $request, string $filename)
    {
        $user = $request->user();
        if (! $user->role->isAdmin()) {
            abort(403, 'Forbidden');
        }

        if (!Storage::disk('tickets')->exists($filename)) {
            abort(404, 'Ticket not found');
        }

        return Storage::disk('tickets')->download(
        $filename,
        $filename,
        ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * Download a qr code for an authentificated user.
     *
     * @OA\Get(
     *     path="/api/tickets/qr/{filename}",
     *     summary="Download user qr code",
     *     description="Streams the qr code for the logged-in user.",
     *     operationId="downloadQr",
     *     tags={"Tickets"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="filename",
     *         in="path",
     *         required=true,
     *         description="Filename of the Qr code PNG",
     *         @OA\Schema(type="string", example="qr_5566.png")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="PNG file download",
     *         @OA\MediaType(
     *             mediaType="image/png"
     *         )
     *     ),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=404, ref="#/components/responses/NotFound"),
     *     @OA\Response(response=403, ref="#/components/responses/Forbidden"),
     *     @OA\Response(response=500, ref="#/components/responses/InternalError"),
     * )
     *
     * @param Request $request
     * @param string $filename
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function downloadQr(Request $request, string $filename)
    {
        $ticket = Ticket::where('qr_filename', $filename)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if (! Storage::disk('qrcodes')->exists($filename)) {
            abort(404, 'QR code not found');
        }

        return Storage::disk('qrcodes')->download(
            $filename,
            $filename,
            ['Content-Type' => 'image/png']
        );
    }

    /**
     * Download any qr code as an admin.
     *
     * @OA\Get(
     *     path="/api/tickets/admin/qr/{filename}",
     *     summary="Download qr code (admin)",
     *     description="Allows an administrator to download any QR code by filename.",
     *     operationId="adminDownloadQr",
     *     tags={"Tickets"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="filename",
     *         in="path",
     *         required=true,
     *         description="Filename of the Qr code PNG",
     *         @OA\Schema(type="string", example="qr_5566.png")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="PNG file download",
     *         @OA\MediaType(mediaType="image/png")
     *     ),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=403, ref="#/components/responses/Forbidden"),
     *     @OA\Response(response=404, ref="#/components/responses/NotFound"),
     *     @OA\Response(response=500, ref="#/components/responses/InternalError"),
     * )
     *
     * @param Request $request
     * @param string $filename
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function downloadAdminQr(Request $request, string $filename)
    {
        $user = $request->user();
        if (! $user->role->isAdmin()) {
            abort(403, 'Forbidden');
        }

        if (! Storage::disk('qrcodes')->exists($filename)) {
            abort(404, 'QR code not found');
        }

        return Storage::disk('qrcodes')->download(
            $filename,
            $filename,
            ['Content-Type' => 'image/png']
        );
    }

    /**
     * @OA\Put(
     *   path="/api/tickets/admin/{id}/status",
     *   summary="Update ticket status (admin only)",
     *   description="Updates the status of a ticket. Only admin can use this endpoint.",
     *   operationId="updateTicketStatus",
     *   tags={"Tickets"},
     *   security={{"bearerAuth":{}}},
     *
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     description="ID of the ticket to update",
     *     required=true,
     *     @OA\Schema(type="integer", format="int64")
     *   ),
     *
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"status"},
     *       @OA\Property(property="status", type="string", enum={"issued","used","refunded","cancelled"})
     *     )
     *   ),
     *
     *   @OA\Response(response=204, ref="#/components/responses/NoContent"),

     *   @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *   @OA\Response(response=403, ref="#/components/responses/Forbidden"),
     *   @OA\Response(response=404, ref="#/components/responses/NotFound"),
     *   @OA\Response(response=422, ref="#/components/responses/ValidationError"),
     *   @OA\Response(response=500, ref="#/components/responses/InternalError")
     * )
     */
    public function updateStatus(TicketStatusRequest $request, int $id)
    {
        $status = $request->validatedStatus();
        $ticket = $this->ticketService->changeStatus($id, $status);

        return response()->json([null], 204);
    }

    /**
     * @OA\Post(
     *   path="/api/tickets",
     *   summary="Generate free tickets for a user (admin only)",
     *   description="Generates free tickets for a user and product. Only admin can use this endpoint.",
     *   operationId="createFreeTickets",
     *   tags={"Tickets"},
     *   security={{"bearerAuth":{}}},
     *
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"user_id","product_id","quantity"},
     *       @OA\Property(property="user_id",    type="integer", example=1, description="ID of the existing user"),
     *       @OA\Property(property="product_id", type="integer", example=42, description="ID of the product/event"),
     *       @OA\Property(property="quantity",   type="integer", example=2, description="Number of tickets to generate")
     *     )
     *   ),
     *
     *   @OA\Response(response=204, ref="#/components/responses/NoContent"),
     *   @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *   @OA\Response(response=403, ref="#/components/responses/Forbidden"),
     *   @OA\Response(response=422, ref="#/components/responses/ValidationError"),
     *   @OA\Response(response=500, ref="#/components/responses/InternalError")
     * )
     */
    public function createForUser(TicketCreateRequest $request)
    {
        $data = $request->validatedData();
        $this->ticketService->createFreeTickets(
            $data['user_id'],
            $data['product_id'],
            $data['quantity']
        );

        return response()->json(null, 204);
    }

    /**
     * Scan a ticket QR code and mark it as used (employee only).
     *
     * @OA\Post(
     *   path="/api/tickets/scan/{token}",
     *   summary="Scan ticket QR and validate entry",
     *   description="Employee scans a ticket token. If status is `issued`, marks it as `used` and returns user & event info. If already processed, throws 409 with full details.",
     *   operationId="scanTicket",
     *   tags={"Tickets"},
     *   security={{"bearerAuth":{}}},
     *
     *   @OA\Parameter(
     *     name="token",
     *     in="path",
     *     required=true,
     *     description="Unique ticket UUID token from QR code",
     *     @OA\Schema(type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000")
     *   ),
     *
     *   @OA\Response(
     *     response=200,
     *     description="Ticket validated: returns attendee & event info",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(
     *         property="user",
     *         type="object",
     *         @OA\Property(property="firstname", type="string", example="John"),
     *         @OA\Property(property="lastname",  type="string", example="Doe"),
     *         @OA\Property(property="email",     type="string", example="john.doe@example.com")
     *       ),
     *       @OA\Property(
     *         property="event",
     *         type="object",
     *         @OA\Property(property="name",     type="string", example="Concert Live"),
     *         @OA\Property(property="date",     type="string", format="date", example="2024-07-26"),
     *         @OA\Property(property="time",     type="string", format="time", example="20:00"),
     *         @OA\Property(property="location", type="string", example="Stade de France")
     *       )
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=409,
     *     description="Ticket already processed: returns status, timestamp, attendee & event info",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="status",    type="string", example="used"),
     *       @OA\Property(property="timestamp", type="string", format="date-time", example="2025-05-10T15:00:00+02:00"),
     *       @OA\Property(
     *         property="user",
     *         type="object",
     *         @OA\Property(property="firstname", type="string", example="John"),
     *         @OA\Property(property="lastname",  type="string", example="Doe"),
     *         @OA\Property(property="email",     type="string", example="john.doe@example.com")
     *       ),
     *       @OA\Property(
     *         property="event",
     *         type="object",
     *         @OA\Property(property="name",     type="string", example="Concert Live"),
     *         @OA\Property(property="date",     type="string", format="date", example="2024-07-26"),
     *         @OA\Property(property="time",     type="string", format="time", example="20:00"),
     *         @OA\Property(property="location", type="string", example="Stade de France")
     *       ),
     *       @OA\Property(property="code",    type="string", example="ticket_already_processed"),
     *       @OA\Property(property="message", type="string", example="This ticket was already used on 2025-05-10T15:00:00+02:00")
     *     )
     *   ),
     *
     *   @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *   @OA\Response(response=403, ref="#/components/responses/Forbidden"),
     *   @OA\Response(response=404, ref="#/components/responses/NotFound"),
     *   @OA\Response(response=500, ref="#/components/responses/InternalError")
     * )
     *
     * @param Request $request
     * @param string  $token
     * @return \Illuminate\Http\JsonResponse
     */
    public function scanTicket(Request $request, string $token)
    {
        $ticket = Ticket::with('user','product')
                        ->where('token', $token)
                        ->firstOrFail();

        if ($ticket->status !== TicketStatus::Issued->value) {
            throw new TicketAlreadyProcessedException($ticket);
        }

        $ticket->update([
            'status'  => TicketStatus::Used->value,
            'used_at' => now(),
        ]);

        return response()->json([
            'user'  => [
                'firstname' => $ticket->user->firstname,
                'lastname'  => $ticket->user->lastname,
                'email'     => $ticket->user->email,
            ],
            'event' => [
                'name'     => $ticket->product->name,
                'date'     => $ticket->product->product_details['date'] ?? null,
                'time'     => $ticket->product->product_details['time'] ?? null,
                'location' => $ticket->product->product_details['location'] ?? null,
            ],
        ], 200);
    }
}
