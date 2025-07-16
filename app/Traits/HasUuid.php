<?php

namespace App\Traits;

use Illuminate\Support\Str;

/**
 * Trait HasUuid
 *
 * Automatically generates a UUID for the model when it is created.
 */
trait HasUuid
{
    protected static function bootHasUuid(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}
