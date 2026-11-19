<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Movie extends Model
{
    protected $fillable = ['title', 'release_year', 'runtime', 'watched'];

    protected function casts(): array
    {
        return [
            'watched' => 'boolean',
        ];
    }

    /** Movies to watch before this one. */
    public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(Movie::class, 'movie_prerequisites', 'movie_id', 'prerequisite_id');
    }
}
