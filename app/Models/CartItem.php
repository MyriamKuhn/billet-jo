<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'quantity',
        'cart_id',
        'product_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'quantity' => 'integer',
    ];

    /**
     * Associate the cart item with a cart.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Cart, CartItem>
     */
    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * Associate the cart item with a product.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Product, CartItem>
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
