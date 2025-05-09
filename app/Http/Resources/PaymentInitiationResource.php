<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** @OA\Schema(
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
     * Transform the resource into a minimal array for checkout.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'uuid'            => $this->uuid,
            'status'          => $this->status->value,
            'transaction_id'  => $this->transaction_id,
            'client_secret'   => $this->client_secret,
        ];
    }
}
