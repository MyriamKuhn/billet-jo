<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @OA\Schema(
 *     schema="Ticket",
 *     type="object",
 *     required={"id", "qr_code_link", "pdf_link", "is_used", "is_refunded", "user_id", "payment_id", "product_id"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="qr_code_link", type="string", example="https://example.com/qr-code/12345"),
 *     @OA\Property(property="pdf_link", type="string", example="https://example.com/ticket-pdf/12345"),
 *     @OA\Property(property="is_used", type="boolean", example=false),
 *     @OA\Property(property="is_refunded", type="boolean", example=false),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(property="payment_id", type="integer", example=1),
 *     @OA\Property(property="product_id", type="integer", example=1),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2023-01-01T00:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2023-04-01T12:00:00Z")
 * )
 */
class Ticket extends Model
{
    /** @use HasFactory<\Database\Factories\TicketFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'qr_code_link',
        'pdf_link',
        'is_used',
        'is_refunded',
        'user_id',
        'payment_id',
        'product_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'is_used' => 'boolean',
        'is_refunded' => 'boolean',
    ];

    /**
     * Associate the ticket with a user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<User, Ticket>
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Associate the ticket with a payment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Payment, Ticket>
     */
    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Associate the ticket with a product.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Product, Ticket>
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
