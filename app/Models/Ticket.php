<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'qr_code_link',
        'pdf_link',
        'is_used',
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
