<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Represents a user resource, typically used in user management systems.
 *
 * @OA\Schema(
 *   schema="UserResource",
 *   type="object",
 *   title="UserResource",
 *   @OA\Property(property="id", type="integer", example=1),
 *   @OA\Property(property="firstname", type="string", example="John"),
 *   @OA\Property(property="lastname", type="string", example="Doe"),
 *   @OA\Property(property="email", type="string", example="john.doe@example.com"),
 *   @OA\Property(property="role", type="string", enum={"admin","employee","user"}, example="admin"),
 *   @OA\Property(property="twofa_enabled", type="boolean", example=true),
 *   @OA\Property(property="is_active", type="boolean", example=true),
 *   @OA\Property(property="email_verified_at", type="string", format="date-time", nullable=true, example="2025-05-10T12:00:00Z"),
 *   @OA\Property(property="created_at", type="string", format="date-time", example="2025-05-10T12:00:00Z"),
 *   @OA\Property(property="updated_at", type="string", format="date-time", example="2025-05-10T12:00:00Z")
 * )
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id'                => $this->id,
            'firstname'         => $this->firstname,
            'lastname'          => $this->lastname,
            'email'             => $this->email,
            'role'              => $this->role->value,
            'twofa_enabled'     => $this->twofa_enabled,
            'is_active'         => $this->is_active,
            'email_verified_at' => $this->email_verified_at,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}
