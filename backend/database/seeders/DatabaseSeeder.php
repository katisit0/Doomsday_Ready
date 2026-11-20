<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with the PDF's example movies and cinemas.
     * The full MCU data set is optional: POST /api/movies/load-mcu or `db:seed --class=McuMovieSeeder`.
     */
    public function run(): void
    {
        $this->call([
            SampleMovieSeeder::class,
            CinemaSeeder::class,
        ]);
    }
}
