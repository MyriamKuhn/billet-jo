<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="Product",
 *     type="object",
 *     required={"id", "name", "price", "stock_quantity"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Billet concert"),
 *     @OA\Property(
 *         property="product_details",
 *         type="object",
 *         example={
 *           "places": 1,
 *           "description": "Assistez à un moment historique avec la cérémonie d’ouverture des Jeux Olympiques de Paris 2024. Vivez une soirée exceptionnelle...",
 *           "date": "2024-07-26",
 *           "time": "19h30 (accès recommandé dès 18h00)",
 *           "location": "Stade de France, Saint-Denis",
 *           "category": "Cérémonies",
 *           "image": "https://picsum.photos/seed/1/600/400"
 *           },
 *     ),
 *     @OA\Property(property="price", type="number", format="float", example=59.99),
 *     @OA\Property(property="sale", type="number", format="float", example=49.99),
 *     @OA\Property(property="stock_quantity", type="integer", example=100),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2023-01-01T00:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2023-04-01T12:00:00Z"),

 *     @OA\Property(
 *         property="tickets",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/Ticket")
 *     ),
 *     @OA\Property(
 *         property="cart_items",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/CartItem")
 *     )
 * )
 */
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
