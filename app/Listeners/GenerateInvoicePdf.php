<?php

namespace App\Listeners;

use App\Events\InvoiceRequested;
use Illuminate\Contracts\Queue\ShouldQueue;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class GenerateInvoicePdf implements ShouldQueue
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
        // Generate the PDF
        $pdf = Pdf::loadView('invoices.template', [
            'payment' => $payment,
        ]);

        // Store the PDF in the 'invoices' disk
        $filename = $payment->invoice_link;
        Storage::disk('invoices')->put($filename, $pdf->output());
    }
}
