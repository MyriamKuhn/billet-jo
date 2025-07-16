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
 * User model representing a user in the system.
 *
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
 * ),
 *
 * @property \App\Enums\UserRole        $role
 * @property bool                        $twofa_enabled
 * @property bool                        $is_active
 * @property \Illuminate\Support\Carbon|null   $email_verified_at
 * @property \Illuminate\Support\Carbon|null   $created_at
 * @property \Illuminate\Support\Carbon|null   $updated_at
 * @property string|null                 $password_hash
 * @property string|null                 $remember_token
 * @property \App\Models\Cart|null        $cart
 * @property \Illuminate\Database\Eloquent\Collection<int, \App\Models\Ticket> $tickets
 * @property \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payment> $payments
 * @property \App\Models\EmailUpdate|null $emailUpdate
 * @property string|null                 $twofa_secret
 * @property string|null                 $twofa_recovery_codes
 * @property string|null                 $twofa_secret_temp
 * @property \Illuminate\Support\Carbon|null $twofa_secret_temp_updated_at
 * @property \Illuminate\Support\Carbon|null $twofa_recovery_codes_updated_at
 * @property \Illuminate\Support\Carbon|null $twofa_temp_expires_at
 * @property string|null                 $firstname
 * @property string|null                 $lastname
 * @property string|null                 $email
 * @property int  $id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Ticket> $tickets
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'email',
        'password_hash',
        'firstname',
        'lastname',
        'role',
        'is_active',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var string[]
     */
    protected $hidden = [
        'password_hash',
        'remember_token',
        'twofa_secret',
        'twofa_recovery_codes',
        'twofa_secret_temp',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'role'=> UserRole::class,
        'twofa_enabled' => 'boolean',
        'is_active' => 'boolean',
        'email_verified_at' => 'datetime',
        'twofa_recovery_codes' => 'array',
        'twofa_temp_expires_at'=> 'datetime',
    ];

    /**
     * Get the tickets belonging to this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Get the payments belonging to this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the cart associated with this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function cart()
    {
        return $this->hasOne(Cart::class);
    }

    /**
     * Get the pending email update for this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function emailUpdate()
    {
        return $this->hasOne(EmailUpdate::class);
    }

    /**
     * Send the email verification notification.
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
