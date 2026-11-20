<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cinema extends Model
{
    protected $fillable = ['name'];

    /** Seat bundle sizes this cinema sells. */
    public function bundles(): HasMany
    {
        return $this->hasMany(CinemaBundle::class);
    }
}
