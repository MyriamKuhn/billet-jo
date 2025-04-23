<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;

class Payment extends Model
{
    use HasUuid;

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
}
