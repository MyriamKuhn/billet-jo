<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    /** @use HasFactory<\Database\Factories\CartFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        "user_id",
    ];

    /**
     * Associate the cart with a user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<User, Cart>
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Associate the cart with cart items.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<CartItem, Cart>
     */
    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

}
