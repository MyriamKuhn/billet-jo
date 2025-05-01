<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="EmailUpdate",
 *     type="object",
 *     required={"id", "user_id", "old_email", "new_email", "token"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(property="old_email", type="string", example="old.email@example.com"),
 *     @OA\Property(property="new_email", type="string", example="new.email@example.com"),
 *     @OA\Property(property="token", type="string", example="1234567890abcdef"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2023-01-01T00:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2023-01-01T00:00:00Z")
 * )
 */
class EmailUpdate extends Model
{
    /** @use HasFactory<\Database\Factories\EmailUpdateFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'old_email',
        'new_email',
        'token',
    ];

    /**
     * Associate the email update with a user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<User, EmailUpdate>
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
