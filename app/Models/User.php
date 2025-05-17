<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Notifications\VerifyEmailNotification;

/**
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     required={"id", "firstname", "lastname", "email", "role", "is_active"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="firstname", type="string", example="John"),
 *     @OA\Property(property="lastname", type="string", example="Doe"),
 *     @OA\Property(property="email", type="string", example="john.doe@example.com"),
 *     @OA\Property(property="role", type="string", enum={"admin", "employee", "user"}, example="admin"),
 *     @OA\Property(property="twofa_enabled", type="boolean", example=true),
 *     @OA\Property(property="twofa_secret", type="string", example="abcd1234"),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="email_verified_at", type="string", format="date-time", example="2023-04-01T12:00:00Z"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2023-01-01T00:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2023-04-01T12:00:00Z"),
 *     @OA\Property(property="password_hash", type="string", example="$2y$10$Sfs4e51z4./7bdZI9shXv1m5gIaDU5hN2mJ4qhp9HXVFuDQZDzKCm"),
 *     @OA\Property(property="remember_token", type="string", example="abcd1234xyz"),
 *     @OA\Property(property="cart", type="object", ref="#/components/schemas/Cart"),
 *     @OA\Property(property="tickets", type="array", @OA\Items(ref="#/components/schemas/Ticket")),
 *     @OA\Property(property="payments", type="array", @OA\Items(ref="#/components/schemas/Payment")),
 *     @OA\Property(property="emailUpdate", type="object", ref="#/components/schemas/EmailUpdate"),
 * )
 */
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
     */
    protected $casts = [
        'role'=> UserRole::class,
        'twofa_enabled' => 'boolean',
        'is_active' => 'boolean',
        'email_verified_at' => 'datetime',
    ];

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

    /**
     * Associate the user with a email update.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne<EmailUpdate, User>
     */
    public function emailUpdate()
    {
        return $this->hasOne(EmailUpdate::class);
    }

    /**
     *  Send the email verification notification.
     *
     * @return void
     */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new VerifyEmailNotification());
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
    }
}
