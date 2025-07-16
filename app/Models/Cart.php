<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model representing a shopping cart.
 *
 * @OA\Schema(
 *     schema="Cart",
 *     type="object",
 *     required={"id", "user_id"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(
 *         property="cart_items",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/CartItem")
 *     ),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2023-01-01T00:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2023-04-01T12:00:00Z")
 * )
 */
class Cart extends Model
{
    /** @use HasFactory<\Database\Factories\CartFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        "user_id",
    ];

    /**
     * Get the user that owns this cart.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<User, Cart>
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the items in this cart.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<CartItem, Cart>
     */
    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

}
