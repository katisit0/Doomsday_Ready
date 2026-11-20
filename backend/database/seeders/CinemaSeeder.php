<?php

namespace Database\Seeders;

use App\Models\Cinema;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The four example cinemas from the assessment's booking table.
 * Safe to run more than once: cinemas are matched by name and their sizes replaced.
 */
class CinemaSeeder extends Seeder
{
    public function run(): void
    {
        $cinemas = [
            'Cinema A' => [1, 3, 4],
            'Cinema B' => [2, 5],
            'Cinema C' => [6, 9, 20],
            'Cinema D' => [4, 6],
        ];

        DB::transaction(function () use ($cinemas) {
            foreach ($cinemas as $name => $sizes) {
                $cinema = Cinema::firstOrCreate(['name' => $name]);
                $cinema->bundles()->delete();
                foreach ($sizes as $size) {
                    $cinema->bundles()->create(['size' => $size]);
                }
            }
        });
    }
}
