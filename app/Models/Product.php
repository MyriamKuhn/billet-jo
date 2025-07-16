<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\ProductTranslation;

/**
 * Model representing a product with localized details.
 *
 * @OA\Schema(
 *     schema="Product",
 *     type="object",
 *     required={"id", "name", "price", "stock_quantity"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Localized product name based on Accept-Language header",
 *         example="Billet concert"
 *     ),
 *     @OA\Property(
 *         property="product_details",
 *         type="object",
 *         description="Localized details object",
 *         example={
 *           "places": 1,
 *           "description": "Assistez à un moment historique ...",
 *           "date": "2024-07-26",
 *           "time": "19h30 (accès recommandé dès 18h00)",
 *           "location": "Stade de France, Saint-Denis",
 *           "category": "Cérémonies",
 *           "image": "https://picsum.photos/seed/1/600/400"
 *         }
 *     ),
 *     @OA\Property(property="price", type="number", format="float", example=59.99),
 *     @OA\Property(property="sale", type="number", format="float", example=49.99),
 *     @OA\Property(property="stock_quantity", type="integer", example=100),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="tickets", type="array", @OA\Items(ref="#/components/schemas/Ticket")),
 *     @OA\Property(property="cart_items", type="array", @OA\Items(ref="#/components/schemas/CartItem"))
 * )
 */
class Product extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'name',           // default/fallback English name
        'product_details',
        'price',
        'sale',
        'stock_quantity',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'product_details' => 'array',
        'price'           => 'decimal:2',
        'sale'            => 'decimal:2',
        'stock_quantity'  => 'integer',
    ];

    /**
     * Get all available translations for this product.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function translations(): HasMany
    {
        return $this->hasMany(ProductTranslation::class);
    }

    /**
     * Retrieve the translation for the current locale (or 'en' as fallback).
     *
     * @param  string|null  $locale
     * @return \App\Models\ProductTranslation|null
     */
    public function translate(string $locale = null): ?ProductTranslation
    {
        $locale = $locale ?? app()->getLocale();

        if (! $this->relationLoaded('translations')) {
            $this->load('translations');
        }

        return $this->translations
                    ->firstWhere('locale', $locale)
            ??     $this->translations->firstWhere('locale', 'en');
    }

    /**
     * Accessor for the 'name' attribute: returns the translated name.
     *
     * @return string
     */
    public function getNameAttribute(): string
    {
        $translation = $this->translate();

        return $translation->name
            ?? $this->attributes['name'];
    }

    /**
     * Accessor for the 'product_details' attribute: returns the translated details.
     *
     * @param  mixed  $value
     * @return array<string,mixed>
     */
    public function getProductDetailsAttribute($value): array
    {
        $translation = $this->translate();

        return $translation->product_details
            ?? (json_decode($value, true) ?? []);
    }

    /**
     * Get the tickets associated with this product.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Get the cart items associated with this product.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
}
