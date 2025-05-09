<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Payment;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RefundRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this refund request.
     */
    public function authorize(): bool
    {
        // Only admins may refund
        return auth()->check() && auth()->user()->role->isAdmin();
    }

    /**
     * Prepare the data for validation.
     *
     * Here we fetch the payment by UUID and compute the refundable max.
     */
    protected function prepareForValidation(): void
    {
        $uuid = $this->route('uuid');

        $payment = Payment::where('uuid', $uuid)->first();

        if (! $payment) {
            // Let it bubble up as a 404 before validation
            throw new NotFoundHttpException("Payment not found.");
        }

        // Calculate remaining refundable amount
        $remaining = $payment->amount - ($payment->refunded_amount ?? 0.0);

        // Merge into the validator data so we can reference it
        $this->merge([
            'max_refund' => $remaining,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'amount'     => [
                'required',
                'numeric',
                'min:0.01',
                // Cannot refund more than remaining
                'max:' . $this->input('max_refund'),
            ],
        ];
    }

    /**
     * Custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'amount.max' => 'You can only refund up to ' . $this->input('max_refund') . '.',
        ];
    }
}
