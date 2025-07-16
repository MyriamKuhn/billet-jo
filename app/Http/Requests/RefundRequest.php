<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Payment;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Form request to validate refund requests for payments.
 *
 * Ensures only administrators may issue refunds, fetches the target payment by UUID,
 * calculates the maximum refundable amount, and validates the requested refund amount.
 */
class RefundRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this refund request.
     *
     * @return bool  True if the authenticated user has an admin role.
     */
    public function authorize(): bool
    {
        // Only admins may refund
        return auth()->check() && auth()->user()->role->isAdmin();
    }

    /**
     * Prepare the data for validation.
     *
     * Fetches the payment by its UUID from the route, throws 404 if not found,
     * and computes the remaining refundable amount to enforce max limits.
     *
     * @return void
     * @throws NotFoundHttpException
     */
    protected function prepareForValidation(): void
    {
        // Retrieve the payment UUID from the route parameters
        $uuid = $this->route('uuid');

        // Attempt to load the payment record
        $payment = Payment::where('uuid', $uuid)->first();

        if (! $payment) {
            // Throw 404 before proceeding to validation
            throw new NotFoundHttpException("Payment not found.");
        }

        // Compute the remaining refundable amount
        $remaining = $payment->amount - ($payment->refunded_amount ?? 0.0);

        // Merge into request data for use in validation rules
        $this->merge([
            'max_refund' => $remaining,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Ensures 'amount' is present, numeric, above 0.01, and does not exceed the remaining refundable.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount'     => [
                'required',
                'numeric',
                'min:0.01',
                // Enforce maximum refund amount calculated earlier
                'max:' . $this->input('max_refund'),
            ],
        ];
    }

    /**
     * Custom error messages for validation failures.
     *
     * Provides a clear message when the requested refund exceeds the allowed maximum.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.max' => 'You can only refund up to ' . $this->input('max_refund') . '.',
        ];
    }
}
