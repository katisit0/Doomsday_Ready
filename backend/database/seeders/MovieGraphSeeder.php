<?php

namespace Database\Seeders;

use App\Models\Movie;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Writes a list of titles and their prerequisites to the database.
 * Safe to run more than once: titles are matched ignoring case, their details updated and their
 * prerequisites replaced; watched ticks and movies not in the list are left alone.
 */
abstract class MovieGraphSeeder extends Seeder
{
    /**
     * @param array<string, array{0:int, 1:int, 2:string, 3:bool, 4:string[]}> $titles
     *        title => [release year, runtime, type, essential, [prerequisite titles]]
     */
    protected function seedGraph(array $titles): void
    {
        DB::transaction(function () use ($titles) {
            $ids = [];
            foreach ($titles as $title => [$year, $runtime, $type, $essential]) {
                $movie = Movie::whereRaw('LOWER(TRIM(title)) = ?', [mb_strtolower($title)])->first() ?? new Movie();
                $movie->fill([
                    'title'        => $title,
                    'release_year' => $year,
                    'runtime'      => $runtime,
                    'type'         => $type,
                    'essential'    => $essential,
                ])->save();
                $ids[$title] = $movie->id;
            }

            foreach ($titles as $title => [, , , , $prerequisites]) {
                Movie::find($ids[$title])->prerequisites()->sync(
                    array_map(fn ($p) => $ids[$p], array_values(array_unique($prerequisites)))
                );
            }
        });
    }
}
