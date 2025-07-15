<?php

namespace Tests\Unit\Listeners;

use Tests\TestCase;
use App\Listeners\GenerateInvoicePdf;
use App\Events\InvoiceRequested;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfPdf;
use Mockery;
use Illuminate\Support\Str;

class GenerateInvoicePdfTest extends TestCase
{
    use RefreshDatabase;

    public function testHandleGeneratesAndStoresPdfForInvoiceRequested()
    {
        // 1) Fake the storage disk
        Storage::fake('invoices');

        // 2) Create a Payment with a known invoice_link
        $payment = Payment::factory()->create([
            'invoice_link' => 'invoice_' . Str::uuid() . '.pdf',
        ]);

        // 3) Create a mock that _extends_ Barryvdh\DomPDF\PDF
        $fakePdf = Mockery::mock(DomPdfPdf::class);
        $fakePdf->shouldReceive('output')
                ->once()
                ->andReturn('PDF_CONTENT_BYTES');

        // 4) Stub the facade to return our PDF mock
        Pdf::shouldReceive('loadView')
            ->once()
            ->withArgs(function ($view, $data) use ($payment) {
                return $view === 'invoices.template'
                    && isset($data['payment']) && $data['payment']->is($payment)
                    && isset($data['items'])   && is_array($data['items']);
            })
            ->andReturn($fakePdf);

        // 5) Invoke the listener
        $listener = new GenerateInvoicePdf();
        $listener->handle(new InvoiceRequested($payment));

        // 6) Assert the file was stored with the correct contents
        Storage::disk('invoices')
            ->assertExists($payment->invoice_link);

        $this->assertEquals(
            'PDF_CONTENT_BYTES',
            Storage::disk('invoices')->get($payment->invoice_link)
        );
    }
}
