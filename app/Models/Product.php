<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'product_details',
        'price',
        'sale',
        'stock_quantity',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'product_details' => 'array',
        'price' => 'decimal:2',
        'sale' => 'decimal:2',
        'stock_quantity' => 'integer',
    ];

    /**
     * Associate the product with a ticket.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<Ticket, Product>
     */
    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Associate the product with a cart item.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<CartItem, Product>
     */
    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }
}
