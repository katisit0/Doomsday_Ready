<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Movie extends Model
{
    public const TYPES = ['film', 'series'];

    /** Longest allowed runtime in minutes: the spec's 300 for films, more for a whole season of a show. */
    public const MAX_RUNTIME = ['film' => 300, 'series' => 6000];

    protected $fillable = ['title', 'release_year', 'runtime', 'type', 'watched', 'essential'];

    /** Keep the join-table row out of the JSON. */
    protected $hidden = ['pivot'];

    /** Rule: no other movie may have this title, ignoring upper/lower case and outer spaces. */
    public static function uniqueTitle(?int $ignoreId = null): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($ignoreId) {
            if (!is_string($value)) {
                return; // the 'string' rule reports this
            }
            // Both sides lowered and trimmed by the database, so they are compared the same way
            $taken = static::whereRaw('LOWER(TRIM(title)) = LOWER(TRIM(?))', [$value])
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists();

            if ($taken) {
                $fail('The title has already been taken.');
            }
        };
    }

    protected function casts(): array
    {
        return [
            'watched'   => 'boolean',
            'essential' => 'boolean',
        ];
    }

    /** Movies to watch before this one. */
    public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(Movie::class, 'movie_prerequisites', 'movie_id', 'prerequisite_id');
    }
}
