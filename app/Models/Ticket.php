<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Model for tickets.
 *
 * @OA\Schema(
 *   schema="Ticket",
 *   type="object",
 *   required={"id","product_snapshot","token","qr_filename","pdf_filename","status","user_id","payment_id","product_id"},
 *   @OA\Property(property="id", type="integer", example=1),
 *   @OA\Property(
 *     property="product_snapshot",
 *     type="object",
 *     @OA\Property(property="product_name", type="string", example="Concert X"),
 *     @OA\Property(property="ticket_type", type="string", example="VIP"),
 *     @OA\Property(property="unit_price", type="number", format="float", example=49.99),
 *     @OA\Property(property="discount_rate", type="number", format="float", example=0.1),
 *     @OA\Property(property="discounted_price", type="number", format="float", example=44.99)
 *   ),
 *   @OA\Property(property="token", type="string", example="550e8400-e29b-41d4-a716-446655440000"),
 *   @OA\Property(property="qr_filename", type="string", example="qr_123e4567-e89b-12d3-a456-426614174000.png"),
 *   @OA\Property(property="pdf_filename", type="string", example="ticket_123e4567-e89b-12d3-a456-426614174000.pdf"),
 *   @OA\Property(property="status", type="string", enum={"issued","used","refunded","cancelled"}, example="issued"),
 *   @OA\Property(property="used_at", type="string", format="date-time", nullable=true, example="2025-05-10T14:00:00Z"),
 *   @OA\Property(property="refunded_at", type="string", format="date-time", nullable=true, example=null),
 *   @OA\Property(property="cancelled_at", type="string", format="date-time", nullable=true, example=null),
 *   @OA\Property(property="created_at", type="string", format="date-time", example="2025-05-10T12:00:00Z"),
 *   @OA\Property(property="updated_at", type="string", format="date-time", example="2025-05-10T12:30:00Z")
 * )
 */
class Ticket extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'product_snapshot',
        'token',
        'qr_filename',
        'pdf_filename',
        'status',
        'used_at',
        'refunded_at',
        'cancelled_at',
        'user_id',
        'payment_id',
        'product_id',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'product_snapshot' => 'array',
        'status'           => TicketStatus::class,
        'used_at'          => 'datetime',
        'refunded_at'      => 'datetime',
        'cancelled_at'     => 'datetime',
    ];

    /**
     * Automatically generate a UUID token when creating.
     */
    protected static function booted(): void
    {
        static::creating(function (Ticket $ticket) {
            $ticket->token = (string) Str::uuid();
        });
    }

    /**
     * Use the ticket token for route model binding.
     *
     * @return string
     */
    public function getRouteKeyName(): string
    {
        return 'token';
    }

    /**
     * Get the full URL for the QR code image.
     *
     * @return string
     */
    public function getQrCodeUrlAttribute(): string
    {
        return Storage::url("qrcodes/{$this->qr_filename}");
    }

    /**
     * Get the full URL for the ticket PDF.
     *
     * @return string
     */
    public function getPdfUrlAttribute(): string
    {
        return Storage::url("tickets/{$this->pdf_filename}");
    }

    /**
     * Ticket belongs to a user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Ticket belongs to a Payment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Ticket belongs to a Product.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
