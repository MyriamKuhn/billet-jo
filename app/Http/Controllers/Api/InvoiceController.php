<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InvoiceIndexRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Payment;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Http\JsonResponse;


/**
 * Controller for listing and downloading invoice PDFs.
 *
 * Provides endpoints for:
 * - Listing the authenticated user’s invoices with filtering, sorting, and pagination.
 * - Downloading an invoice file for the authenticated user.
 * - (Admin-only) Downloading any invoice by filename.
 */
class InvoiceController extends Controller
{
    /**
     * List all invoices for the authenticated user.
     *
     * Supports optional filters (status, date range), sorting, and pagination.
     *
     * @OA\Get(
     *     path="/api/invoices",
     *     summary="List user invoices",
     *     description="Returns a paginated list of the authenticated user’s invoices, with optional filters (status, date range), sorting and pagination.",
     *     operationId="listInvoices",
     *     tags={"Invoices"},
     *     security={{"bearerAuth":{}}},
     *
     *    @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         description="Filter by invoice status",
     *         @OA\Schema(type="string", enum={"pending","paid","failed","refunded"})
     *     ),
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         required=false,
     *         description="Filter invoices created on or after this date (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         required=false,
     *         description="Filter invoices created on or before this date (YYYY-MM-DD)",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         required=false,
     *         description="Field to sort by",
     *         @OA\Schema(type="string", enum={"uuid","amount","created_at"}, default="created_at")
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         required=false,
     *         description="Sort direction",
     *         @OA\Schema(type="string", enum={"asc","desc"}, default="desc")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of items per page",
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number to retrieve",
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Paginated list of invoices",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="uuid",         type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000"),
     *                     @OA\Property(property="amount",       type="number", format="float", example=100.00),
     *                     @OA\Property(property="status",       type="string", example="paid"),
     *                     @OA\Property(property="created_at",   type="string", format="date-time", example="2025-05-10T14:30:00+02:00"),
     *                     @OA\Property(property="invoice_link", type="string", example="invoice_5566.pdf"),
     *                     @OA\Property(property="download_url", type="string", example="https://api.example.com/api/invoices/invoice_5566.pdf")
     *                 )
     *             ),
     *             @OA\Property(property="links", type="object"),
     *             @OA\Property(property="meta",  type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=403, ref="#/components/responses/Forbidden"),
     *     @OA\Response(response=500, ref="#/components/responses/InternalError"),
     * )
     *
     * @param  InvoiceIndexRequest  $request  Validated filters, sort, and pagination inputs.
     * @return JsonResponse                   Paginated invoice data including download URLs.
     */
    public function index(InvoiceIndexRequest $request)
    {
        $filters    = $request->validatedFilters();
        $options = $request->paginationAndSort();
        $sortBy    = $options['sort_by'];
        $sortOrder = $options['sort_order'];
        $perPage   = $options['per_page'];

        $query = Payment::where('user_id', auth()->id());

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $paginator = $query
            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage);

        $transformed = $paginator->getCollection()->map(function (Payment $p) {
            return [
                'uuid'         => $p->uuid,
                'amount'       => (float) $p->amount,
                'status'       => $p->status->value,
                'created_at'   => $p->created_at->toIso8601String(),
                'invoice_link' => $p->invoice_link,
                'download_url' => url("/api/invoices/{$p->invoice_link}"),
            ];
        });

        $paginator->setCollection($transformed);

        return response()->json([
            'data'  => $paginator->items(),
            'links' => [
                'first' => $paginator->url(1),
                'last'  => $paginator->url($paginator->lastPage()),
                'prev'  => $paginator->previousPageUrl(),
                'next'  => $paginator->nextPageUrl(),
            ],
            'meta'  => [
                'current_page' => $paginator->currentPage(),
                'from'         => $paginator->firstItem(),
                'last_page'    => $paginator->lastPage(),
                'path'         => $paginator->path(),
                'per_page'     => $paginator->perPage(),
                'to'           => $paginator->lastItem(),
                'total'        => $paginator->total(),
            ],
        ], 200);
    }

    /**
     * Stream the PDF for a specific invoice belonging to the authenticated user.
     *
     * @OA\Get(
     *     path="/api/invoices/{filename}",
     *     summary="Download user invoice",
     *     description="Streams the invoice PDF for the logged-in user.",
     *     operationId="downloadInvoice",
     *     tags={"Invoices"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="filename",
     *         in="path",
     *         required=true,
     *         description="Filename of the invoice PDF",
     *         @OA\Schema(type="string", example="invoice_5566.pdf")
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
     *     @OA\Response(response=403, ref="#/components/responses/Forbidden"),
     *     @OA\Response(response=404, ref="#/components/responses/NotFound"),
     *     @OA\Response(response=500, ref="#/components/responses/InternalError"),
     * )
     *
     * @param  Request  $request    The HTTP request instance (used for auth).
     * @param  string   $filename   The invoice PDF filename.
     * @return StreamedResponse      PDF download response or 404 if not found.
     */
    public function download(Request $request, string $filename): Response
    {
        $payment = Payment::where('invoice_link', $filename)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if (!Storage::disk('invoices')->exists($filename)) {
            abort(404, 'Invoice not found');
        }

        return Storage::disk('invoices')->download(
        $filename,
        $filename,
        ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * (Admin only) Stream any invoice PDF by filename.
     *
     * Checks that the caller has admin privileges before serving.
     *
     * @OA\Get(
     *     path="/api/invoices/admin/{filename}",
     *     summary="Download invoice (admin)",
     *     description="Allows an administrator to download any invoice PDF by filename.",
     *     operationId="adminDownloadInvoice",
     *     tags={"Invoices"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="filename",
     *         in="path",
     *         required=true,
     *         description="Filename of the invoice PDF",
     *         @OA\Schema(type="string", example="invoice_5566.pdf")
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
     * @param  Request  $request    The HTTP request instance (used for auth).
     * @param  string   $filename   The invoice PDF filename.
     * @return StreamedResponse      PDF download response or 403/404 on failure.
     */
    public function adminDownload(Request $request, string $filename): StreamedResponse
    {
        $user = auth()->user();
        if (! $user->role->isAdmin()) {
            abort(403, 'Forbidden');
        }

        if (!Storage::disk('invoices')->exists($filename)) {
            abort(404, 'Invoice not found');
        }

        return Storage::disk('invoices')->download(
        $filename,
        $filename,
        ['Content-Type' => 'application/pdf']
        );
    }
}


