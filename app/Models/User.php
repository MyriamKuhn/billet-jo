<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Notifications\VerifyEmailNotification;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens,HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'password_hash',
        'firstname',
        'lastname',
        'role',
        'twofa_secret',
        'twofa_enabled',
        'is_active',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password_hash',
        'remember_token',
        'twofa_secret',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role'=> UserRole::class,
            'twofa_enabled' => 'boolean',
            'is_active' => 'boolean',
            'email_verified_at' => 'datetime',
        ];
    }

    /**
     * Associate the user with a ticket.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<Ticket, User>
     */
    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Associate the user with a payment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<Payment, User>
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Associate the user with a cart.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne<Cart, User>
     */
    public function cart()
    {
        return $this->hasOne(Cart::class);
    }

    public function sendEmailVerificationNotification()
    {
        $this->notify(new VerifyEmailNotification());
    }
}
