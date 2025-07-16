<?php

namespace App\Listeners;

use App\Events\InvoiceRequested;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Class GenerateInvoicePdf
 *
 * This listener handles the generation of PDF invoices when an invoice is requested.
 */
class GenerateInvoicePdf
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param InvoiceRequested $event
     * @return void
     */
    public function handle(InvoiceRequested $event): void
    {
        $payment = $event->payment;

        // Set locale for PDF rendering
        app()->setLocale($event->locale);

        // Retrieve cart snapshot items
        $items = $payment->cart_snapshot['items'] ?? [];

        // Render the invoice view to PDF
        $pdf = Pdf::loadView('invoices.template', [
            'payment' => $payment,
            'items'   => $items,
        ]);

        // Store the generated PDF on the 'invoices' disk
        $filename = $payment->invoice_link;
        Storage::disk('invoices')->put($filename, $pdf->output());
    }
}
