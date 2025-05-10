<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *   schema="TicketResource",
 *   type="object",
 *   title="TicketResource",
 *   @OA\Property(property="id", type="integer", example=1),
 *   @OA\Property(property="token", type="string", example="550e8400-e29b-41d4-a716-446655440000"),
 *   @OA\Property(
 *     property="product_snapshot",
 *     type="object",
 *     @OA\Property(property="product_name", type="string", example="Concert X"),
 *     @OA\Property(property="ticket_type", type="string", example="VIP"),
 *     @OA\Property(property="unit_price", type="number", format="float", example=49.99),
 *     @OA\Property(property="discount_rate", type="number", format="float", example=0.1),
 *     @OA\Property(property="discounted_price", type="number", format="float", example=44.99)
 *   ),
 *   @OA\Property(property="status", type="string", enum={"issued","used","refunded","cancelled"}, example="issued"),
 *   @OA\Property(property="used_at", type="string", format="date-time", nullable=true, example="2025-05-10T14:00:00Z"),
 *   @OA\Property(property="refunded_at", type="string", format="date-time", nullable=true, example=null),
 *   @OA\Property(property="cancelled_at", type="string", format="date-time", nullable=true, example=null),
 *   @OA\Property(property="qr_filename", type="string", example="uuid.png"),
 *   @OA\Property(property="pdf_filename", type="string", example="uuid.pdf"),
 *   @OA\Property(property="user", ref="#/components/schemas/UserResource"),
 *   @OA\Property(property="payment", ref="#/components/schemas/PaymentResource"),
 *   @OA\Property(property="product", ref="#/components/schemas/MinimalProduct"),
 *   @OA\Property(property="created_at", type="string", format="date-time", example="2025-05-10T12:00:00Z"),
 *   @OA\Property(property="updated_at", type="string", format="date-time", example="2025-05-10T12:30:00Z")
 * )
 */
class TicketResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'token'            => $this->token,
            'product_snapshot' => $this->product_snapshot,
            'status'           => $this->status->value,
            'used_at'          => $this->used_at,
            'refunded_at'      => $this->refunded_at,
            'cancelled_at'     => $this->cancelled_at,
            'qr_url'           => $this->qr_code_url,
            'pdf_url'          => $this->pdf_url,
            'user'             => new UserResource($this->whenLoaded('user')),
            'payment'          => new PaymentResource($this->whenLoaded('payment')),
            'product'          => new ProductResource($this->whenLoaded('product')),
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
