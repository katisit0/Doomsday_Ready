<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CinemaBundle extends Model
{
    protected $fillable = ['cinema_id', 'size'];

    public function cinema(): BelongsTo
    {
        return $this->belongsTo(Cinema::class);
    }
}
