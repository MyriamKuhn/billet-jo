<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\ProductTranslation;

/**
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

    protected $fillable = [
        'name',           // default/fallback English name
        'product_details',
        'price',
        'sale',
        'stock_quantity',
    ];

    protected $casts = [
        'product_details' => 'array',
        'price'           => 'decimal:2',
        'sale'            => 'decimal:2',
        'stock_quantity'  => 'integer',
    ];

    /**
     * Relation vers toutes les traductions disponibles.
     */
    public function translations(): HasMany
    {
        return $this->hasMany(ProductTranslation::class);
    }

    /**
     * Récupère la traduction pour la locale courante (ou 'en' en fallback).
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
     * Accessor pour ->name : renvoie le nom traduit.
     */
    public function getNameAttribute(): string
    {
        $translation = $this->translate();

        return $translation->name
            ?? $this->attributes['name'];
    }

    /**
     * Accessor pour ->product_details : renvoie les détails traduits.
     */
    public function getProductDetailsAttribute($value): array
    {
        $translation = $this->translate();

        return $translation->product_details
            ?? (json_decode($value, true) ?? []);
    }

    /**
     * Tickets liés à ce produit.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Éléments du panier liés à ce produit.
     */
    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
}
