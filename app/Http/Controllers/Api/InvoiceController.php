<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Payment;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    /**
     * List all invoices for the authenticated user.
     *
     * @OA\Get(
     *     path="/api/invoices",
     *     summary="List user invoices",
     *     description="Returns a list of invoices for the logged-in user, including download URLs.",
     *     operationId="listInvoices",
     *     tags={"Invoices"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of invoices",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="uuid",         type="string", format="uuid", example="..."),
     *                     @OA\Property(property="amount",       type="number", format="float", example=100.00),
     *                     @OA\Property(property="status",       type="string", example="paid"),
     *                     @OA\Property(property="created_at",   type="string", format="date-time", example="2025-05-10T14:30:00+02:00"),
     *                     @OA\Property(property="invoice_link", type="string", example="invoice_5566.pdf"),
     *                     @OA\Property(property="download_url", type="string", example="https://api.example.com/api/invoices/invoice_5566.pdf")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=403, ref="#/components/responses/Forbidden")
     * )
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $invoices = Payment::query()
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($p) => [
                'uuid'         => $p->uuid,
                'amount'       => (float) $p->amount,
                'status'       => $p->status->value,
                'created_at'   => $p->created_at->toIso8601String(),
                'invoice_link' => $p->invoice_link,
                'download_url' => url("/api/invoices/{$p->invoice_link}"),
            ]);

        return response()->json(['data' => $invoices]);
    }

    /**
     * Download an invoice PDF for the authenticated user.
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
     *     @OA\Response(response=404, description="Invoice not found"),
     *     @OA\Response(response=403, ref="#/components/responses/Forbidden")
     * )
     */
    public function download(Request $request, string $filename): Response
    {
        $payment = Payment::where('invoice_link', $filename)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return Storage::disk('invoices')
            ->download("private/invoices/{$filename}");
    }

    /**
     * Download any invoice as admin.
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
     *     @OA\Response(response=404, description="Invoice not found")
     * )
     */
    public function adminDownload(Request $request, string $filename): StreamedResponse
    {
        $user = $request->user();
        if (! $user->role->isAdmin()) {
            abort(403, 'Forbidden');
        }

        $path = "private/invoices/{$filename}";

        if (!Storage::disk('invoices')->exists($path)) {
            abort(404, 'Invoice not found');
        }

        return Storage::disk('invoices')->download(
            $path,
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }
}


