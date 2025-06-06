<?php

namespace App\Listeners;

use App\Events\InvoiceRequested;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

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
     */
    public function handle(InvoiceRequested $event): void
    {
        $payment = $event->payment;

        app()->setLocale($event->locale);
        // Generate the PDF
        $pdf = Pdf::loadView('invoices.template', [
            'payment' => $payment,
        ]);

        // Store the PDF in the 'invoices' disk
        $filename = $payment->invoice_link;
        Storage::disk('invoices')->put($filename, $pdf->output());
    }
}
