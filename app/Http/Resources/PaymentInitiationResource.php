<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for initiating a payment.
 *
 * @OA\Schema(
 *     schema="PaymentInitiationPaid",
 *     type="object",
 *     @OA\Property(property="uuid",           type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000"),
 *     @OA\Property(property="status",         type="string", example="paid", enum={"pending","paid","failed","refunded"}),
 *     @OA\Property(property="transaction_id", type="string", example="pi_abc123"),
 *     @OA\Property(property="client_secret",  type="string", example="cs_test_456")
 * ),
 * @OA\Schema(
 *     schema="PaymentInitiationPending",
 *     type="object",
 *     @OA\Property(property="uuid",           type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000"),
 *     @OA\Property(property="status",         type="string", example="pending", enum={"pending","paid","failed","refunded"}),
 *     @OA\Property(property="transaction_id", type="string", example="pi_abc123"),
 *     @OA\Property(property="client_secret",  type="string", example="cs_test_456")
 * )
 */
class PaymentInitiationResource extends JsonResource
{
    /**
     * Transform the resource into an array suitable for the frontend checkout.
     *
     * This will include:
     * - uuid: the unique identifier for the payment
     * - status: the current payment status (pending/paid/failed/refunded)
     * - transaction_id: the identifier from the payment gateway (if available)
     * - client_secret: the Stripe client secret for completing the payment
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            // Unique payment UUID
            'uuid'            => $this->uuid,
            // Current status of the payment, as a string value
            'status'          => $this->status->value,
            // Gateway-specific transaction identifier (nullable)
            'transaction_id'  => $this->transaction_id,
            // Client secret used by Stripe's frontend SDK to complete the payment
            'client_secret'   => $this->client_secret,
        ];
    }
}
