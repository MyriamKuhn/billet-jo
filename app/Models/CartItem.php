<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Model representing a cart item in a shopping cart.
 *
 * @OA\Schema(
 *     schema="CartItem",
 *     type="object",
 *     required={"id", "quantity", "cart_id", "product_id"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="quantity", type="integer", example=2),
 *     @OA\Property(property="cart_id", type="integer", example=1),
 *     @OA\Property(property="product_id", type="integer", example=5),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2023-01-01T00:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2023-01-01T12:00:00Z"),
 *     @OA\Property(
 *         property="product",
 *         ref="#/components/schemas/Product"
 *     )
 * )
 */
class CartItem extends Model
{
    /** @use HasFactory<\Database\Factories\CartItemFactory> */
    use HasFactory;

    /**
     * The attributes that can be mass assigned.
     *
     * @var string[]
     */
    protected $fillable = [
        'quantity',
        'cart_id',
        'product_id',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'quantity' => 'integer',
    ];

    /**
     * Get the cart that this item belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Cart, CartItem>
     */
    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * Get the product associated with this cart item.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Product, CartItem>
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
