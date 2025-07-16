<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Represents a user ticket resource, typically used in ticketing systems.
 *
 * @OA\Schema(
 *   schema="UserTicketResource",
 *   type="object",
 *   title="UserTicketResource",
 *
 *   @OA\Property(property="id",               type="integer", example=1),
 *   @OA\Property(property="token",            type="string",  example="550e8400-e29b-41d4-a716-446655440000"),
 *   @OA\Property(property="payment_uuid",   type="string",  format="uuid", example="550e8400-e29b-41d4-a716-446655440000"),
 *   @OA\Property(
 *     property="product_snapshot",
 *     type="object",
 *     required={"product_id","product_name","ticket_type","ticket_places","quantity","unit_price","discount_rate","discounted_price"},
 *     @OA\Property(property="product_id",       type="integer", example=42),
 *     @OA\Property(property="product_name",     type="string",  example="Billet concert"),
 *     @OA\Property(property="ticket_type",      type="string",  example="adult"),
 *     @OA\Property(property="ticket_places",    type="integer", example=2),
 *     @OA\Property(property="quantity",         type="integer", example=2),
 *     @OA\Property(property="unit_price",       type="number",  format="float", example=50.00),
 *     @OA\Property(property="discount_rate",    type="number",  format="float", example=0.10),
 *     @OA\Property(property="discounted_price", type="number",  format="float", example=45.00)
 *   ),
 *   @OA\Property(property="status", type="string", enum={"issued","used","refunded","cancelled"}, example="issued"),
 *   @OA\Property(property="used_at",      type="string", format="date-time", nullable=true, example="2025-05-10T14:00:00Z"),
 *   @OA\Property(property="refunded_at",  type="string", format="date-time", nullable=true),
 *   @OA\Property(property="cancelled_at", type="string", format="date-time", nullable=true),
 *   @OA\Property(property="qr_filename",  type="string", example="qr_…png"),
 *   @OA\Property(property="pdf_filename", type="string", example="ticket_…pdf")
 * )
 */
class UserTicketResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'token'            => $this->token,
            'payment_uuid'     => $this->whenLoaded('payment', fn() => $this->payment->uuid),
            'product_snapshot' => $this->product_snapshot,
            'status'           => $this->status->value,
            'used_at'          => optional($this->used_at)?->toIso8601String(),
            'refunded_at'      => optional($this->refunded_at)?->toIso8601String(),
            'cancelled_at'     => optional($this->cancelled_at)?->toIso8601String(),
            'qr_filename'      => $this->qr_code_url,
            'pdf_filename'     => $this->pdf_url,
        ];
    }
}
