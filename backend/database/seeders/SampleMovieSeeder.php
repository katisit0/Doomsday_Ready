<?php

namespace Database\Seeders;

/**
 * The example data from the assessment PDF (default seed), so its expected results can be checked as is.
 * Runtimes are not given in the PDF; the real theatrical runtimes are used (Doomsday: placeholder).
 */
class SampleMovieSeeder extends MovieGraphSeeder
{
    public function run(): void
    {
        $this->seedGraph([
            'Iron Man'                           => [2008, 126, 'film', false, []],
            'Captain America: The First Avenger' => [2011, 124, 'film', false, []],
            'The Avengers'                       => [2012, 143, 'film', false, ['Iron Man', 'Captain America: The First Avenger']],
            'Captain America: Civil War'         => [2016, 147, 'film', false, ['The Avengers']],
            'Thor: Ragnarok'                     => [2017, 130, 'film', false, []],
            'Avengers: Infinity War'             => [2018, 149, 'film', false, ['Captain America: Civil War', 'Thor: Ragnarok']],
            'Avengers: Endgame'                  => [2019, 181, 'film', false, ['Avengers: Infinity War']],
            'Avengers: Doomsday'                 => [2026, 150, 'film', false, ['Avengers: Endgame']],
        ]);
    }
}
