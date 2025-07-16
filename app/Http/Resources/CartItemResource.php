<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource representation for a single cart item.
 *
 * @OA\Schema(
 *   schema="CartItemMinimal",
 *   title="CartItemMinimal",
 *   type="object",
 *   required={"id","quantity","unit_price","total_price","product"},
 *   @OA\Property(property="id",            type="integer", example=7),
 *   @OA\Property(property="product_id",    type="integer", example=42),
 *   @OA\Property(property="quantity",      type="integer", example=3),
 *   @OA\Property(property="in_stock",      type="boolean", example=true),
 *   @OA\Property(property="available_quantity", type="integer", example=5),
 *   @OA\Property(property="unit_price",    type="number",  format="float", example=53.99),
 *   @OA\Property(property="total_price",   type="number",  format="float", example=161.97),
 *   @OA\Property(property="discount_rate", type="number",  format="float", example=0.10, nullable=true),
 *   @OA\Property(property="original_price",type="number",  format="float", example=59.99, nullable=true),
 *   @OA\Property(
 *     property="product",
 *     type="object",
 *     required={"name","image","date","location"},
 *     @OA\Property(property="name",     type="string", example="Billet concert"),
 *     @OA\Property(property="image",    type="string", example="name-of-image.jpg"),
 *     @OA\Property(property="date",     type="string", format="date", example="2024-07-26"),
 *     @OA\Property(property="location", type="string", example="Stade de France")
 *   )
 * )
 */
class CartItemResource extends JsonResource
{
    /**
     * Transform the resource into an array for JSON serialization.
     *
     * Calculates pricing fields and determines stock availability.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        // Original price from the product model
        $originalPrice  = $this->product->price;
        // Discount rate, if any (e.g. 0.10 = 10% off)
        $discountRate   = $this->product->sale ?? 0.0;
        // Calculate the unit price after discount
        $unitPrice      = round($originalPrice * (1 - $discountRate), 2);
        // Calculate the total price for the quantity
        $totalPrice     = round($unitPrice * $this->quantity, 2);
        // Check if there is enough stock to fulfill this quantity
        $available = $this->product->stock_quantity >= $this->quantity;

        return [
            // Unique identifier for the cart item
            'id' => $this->id,
            // The associated product ID
            'product_id' => $this->product_id,
            // Quantity of the product in the cart
            'quantity' => $this->quantity,
            // Whether the requested quantity is currently in stock
            'in_stock' => $available,
            // The maximum available stock for this product
            'available_quantity' => $this->product->stock_quantity,

            // Price per unit after applying any discount
            'unit_price' => $unitPrice,
            // Total price for this line item
            'total_price' => $totalPrice,

            // Include original_price and discount_rate only if a discount applies
            $this->mergeWhen($discountRate > 0, [
                'original_price' => $originalPrice,
                'discount_rate' => $discountRate,
            ]),

            // Product details to assist the frontend (localized fields)
            'product'  => [
                'name'     => $this->product->name,
                'image'    => $this->product->product_details['image']    ?? null,
                'date'     => $this->product->product_details['date']     ?? null,
                'time'     => $this->product->product_details['time']     ?? null,
                'location' => $this->product->product_details['location'] ?? null,
            ],
        ];
    }
}
