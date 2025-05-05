<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *   schema="ProductDetails",
 *   title="ProductDetails",
 *   type="object",
 *   required={"places","description","date","time","location","category","image"},
 *   @OA\Property(property="places",      type="integer", example=1),
 *   @OA\Property(property="description", type="string",  example="Assistez à un moment historique…"),
 *   @OA\Property(property="date",        type="string",  format="date", example="2024-07-26"),
 *   @OA\Property(property="time",        type="string",                 example="19h30"),
 *   @OA\Property(property="location",    type="string",                 example="Stade de France"),
 *   @OA\Property(property="category",    type="string",                 example="Cérémonies"),
 *   @OA\Property(property="image",       type="string",                 example="image.png"),
 * )
 */
class ProductDetailsResource extends JsonResource
{
    /**
     * Transform the product_details JSON payload into the desired structure.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        // $this->resource est déjà un array thanks to the cast
        return [
            'places'      => $this->resource['places']      ?? null,
            'description' => $this->resource['description'] ?? null,
            'date'        => $this->resource['date']        ?? null,
            'time'        => $this->resource['time']        ?? null,
            'location'    => $this->resource['location']    ?? null,
            'category'    => $this->resource['category']    ?? null,
            'image'       => $this->resource['image']       ?? null,
        ];
    }
}
