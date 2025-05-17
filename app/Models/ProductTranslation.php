<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @OA\Schema(
 *     schema="ProductTranslation",
 *     type="object",
 *     required={"id","product_id","locale","name"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="product_id", type="integer", example=42, description="ID du produit parent"),
 *     @OA\Property(property="locale", type="string", example="fr", description="Code de la langue de la traduction"),
 *     @OA\Property(property="name", type="string", example="Cérémonie d’ouverture officielle des JO"),
 *     @OA\Property(
 *         property="product_details",
 *         type="object",
 *         description="Détails localisés du produit",
 *         @OA\Property(property="places", type="integer", example=1),
 *         @OA\Property(
 *             property="description",
 *             type="array",
 *             @OA\Items(type="string"),
 *             example={
 *               "Assistez à un moment historique avec la cérémonie d’ouverture des Jeux Olympiques de Paris 2024.",
 *               "Vivez une soirée exceptionnelle où le sport, la culture et l’émotion se rencontrent..."
 *             }
 *         ),
 *         @OA\Property(property="date", type="string", format="date", example="2024-07-26"),
 *         @OA\Property(property="time", type="string", example="19h30 (accès recommandé dès 18h00)"),
 *         @OA\Property(property="location", type="string", example="Stade de France, Saint-Denis"),
 *         @OA\Property(property="category", type="string", example="Cérémonies"),
 *         @OA\Property(property="image", type="string", format="uri", example="https://picsum.photos/seed/1/600/400")
 *     ),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-04-22T20:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-04-22T20:00:00Z")
 * )
 */
class ProductTranslation extends Model
{
    use HasFactory;

    // Champs remplissables dans product_translations
    protected $fillable = [
        'product_id',
        'locale',
        'name',
        'product_details',
    ];

    // Casting JSON → array
    protected $casts = [
        'product_details' => 'array',
    ];

    /**
     * Relation inverse vers Product.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
