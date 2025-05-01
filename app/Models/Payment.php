<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @OA\Schema(
 *     schema="Payment",
 *     type="object",
 *     required={"id", "invoice_link", "amount", "payment_method", "status", "user_id"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="uuid", type="string", example="550e8400-e29b-41d4-a716-446655440000"),
 *     @OA\Property(property="invoice_link", type="string", example="http://example.com/invoice/12345"),
 *     @OA\Property(property="amount", type="number", format="float", example=100.00),
 *     @OA\Property(property="payment_method", type="string", enum={"credit_card", "paypal", "bank_transfer"}, example="credit_card"),
 *     @OA\Property(property="status", type="string", enum={"pending", "completed", "failed"}, example="completed"),
 *     @OA\Property(property="transaction_id", type="string", example="abc123xyz"),
 *     @OA\Property(property="paid_at", type="string", format="date-time", example="2023-04-01T12:00:00Z"),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2023-01-01T00:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2023-04-01T12:00:00Z"),
 *     @OA\Property(property="tickets", type="array", @OA\Items(ref="#/components/schemas/Ticket"))
 * )
 */
class Payment extends Model
{
    /** @use HasFactory<\Database\Factories\PaymentFactory> */
    use HasUuid, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'invoice_link',
        'amount',
        'payment_method',
        'status',
        'transaction_id',
        'paid_at',
        'user_id',
    ];

    /**
     * The attributes that should be prevented from mass assignment.
     *
     * @var array
     */
    protected $guarded = [
        'uuid',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'payment_method' => PaymentMethod::class,
        'status'=> PaymentStatus::class,
        'paid_at' => 'datetime',
    ];

    /**
     * Associate the payment with a user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<User, Payment>
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Associate the payment with a ticket.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<Ticket, Payment>
     */
    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }
}
