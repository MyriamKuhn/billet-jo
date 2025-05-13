<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Product;

/**
 * @OA\Schema(
 *     schema="PaymentResource",
 *     type="object",
 *     title="PaymentResource",
 *     @OA\Property(property="uuid",            type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000"),
 *     @OA\Property(property="invoice_link",    type="string", example="https://example.com/invoice/12345"),
 *     @OA\Property(
 *         property="cart_snapshot",
 *         type="array",
 *         description="Localized snapshot of the cart as seen by the admin",
 *         @OA\Items(
 *             type="object",
 *             required={"product_id","product_name","ticket_type","quantity","unit_price"},
 *             @OA\Property(property="product_id",   type="integer", example=42),
 *             @OA\Property(property="product_name", type="string",  example="Billet concert"),
 *             @OA\Property(property="ticket_type",  type="string",  example="adult"),
 *             @OA\Property(property="product_place", type="integer", example=2),
 *             @OA\Property(property="quantity",     type="integer", example=2),
 *             @OA\Property(property="unit_price",   type="number",  format="float", example=50.00)
 *         )
 *     ),
 *     @OA\Property(property="amount",          type="number", format="float", example=130.00),
 *     @OA\Property(property="payment_method",  type="string", enum={"paypal","stripe","free"}, example="paypal"),
 *     @OA\Property(property="status",          type="string", enum={"pending","paid","failed","refunded"}, example="paid"),
 *     @OA\Property(property="transaction_id",  type="string", nullable=true, example="pi_abc123"),
 *     @OA\Property(property="paid_at",         type="string", format="date-time", nullable=true, example="2023-04-01T12:00:00Z"),
 *     @OA\Property(property="refunded_at",     type="string", format="date-time", nullable=true, example="2023-04-02T15:00:00Z"),
 *     @OA\Property(property="refunded_amount", type="number", format="float", nullable=true, example=50.00),
 *     @OA\Property(
 *         property="user",
 *         type="object",
 *         required={"id","email"},
 *         @OA\Property(property="id",    type="integer", example=1),
 *         @OA\Property(property="email", type="string",  format="email", example="user@example.com")
 *     ),
 *     @OA\Property(property="created_at",      type="string", format="date-time", example="2023-01-01T00:00:00Z"),
 *     @OA\Property(property="updated_at",      type="string", format="date-time", example="2023-04-01T12:00:00Z")
 * )
 */
class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array for admin listing.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $products = $this->whenLoaded('snapshot_products');

        $localizedSnapshot = collect($this->cart_snapshot['items'])->map(function($line) use ($products) {
            $product = $products[$line['product_id']] ?? null;

            return [
                'product_id'    => $line['product_id'],
                'product_name'  => $product
                    ? $product->name   // via getNameAttribute()
                    : ($line['product_name'] ?? null),
                'ticket_type'   => $product
                    ? $product->product_details['category']
                    : ($line['ticket_type'] ?? null),
                'quantity'      => $line['quantity'],
                'unit_price'    => $line['unit_price'],
            ];
        })->toArray();

        return [
            'uuid'            => $this->uuid,
            'invoice_link'    => $this->invoice_link,
            'cart_snapshot'   => $localizedSnapshot,
            'amount'          => $this->amount,
            'payment_method'  => $this->payment_method->value,
            'status'          => $this->status->value,
            'transaction_id'  => $this->transaction_id,
            'paid_at'         => optional($this->paid_at)->toIso8601String(),
            'refunded_at'     => optional($this->refunded_at)->toIso8601String(),
            'refunded_amount' => $this->refunded_amount,
            'user'            => [
                'id'    => $this->user->id,
                'email' => $this->user->email,
            ],
            'created_at'      => $this->created_at->toIso8601String(),
            'updated_at'      => $this->updated_at->toIso8601String(),
        ];
    }
}
