<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
