<?php

namespace Database\Seeders;

/**
 * Optional full data set: the MCU (films, Disney+ shows and specials) plus the Fox X-Men films and
 * Sony Spider-Man films, up to Avengers: Doomsday. Loaded with POST /api/movies/load-mcu (the
 * "Load full MCU data" button) or `php artisan db:seed --class=McuMovieSeeder`.
 * It replaces the links of the PDF sample movies with these richer ones.
 *
 * - essential = on the official Disney+ "15 to watch before Avengers: Doomsday" list (Aug 2026).
 * - A show season is one entry (type "series") with its total runtime; series runtimes are approximate.
 * - Every title that nothing else depends on is added as a prerequisite of Doomsday, so Doomsday's
 *   full rewatch order contains every title and its essentials view contains the 15 essentials.
 * - Safe to run more than once (see MovieGraphSeeder).
 */
class McuMovieSeeder extends MovieGraphSeeder
{
    public function run(): void
    {
        // title => [release year, runtime (min), type, essential, [prerequisite titles]]
        $titles = [
            // Fox X-Men
            'X-Men'                                    => [2000, 104, 'film', true,  []],
            'X2: X-Men United'                         => [2003, 134, 'film', true,  ['X-Men']],
            'X-Men: The Last Stand'                    => [2006, 104, 'film', false, ['X2: X-Men United']],
            'X-Men Origins: Wolverine'                 => [2009, 107, 'film', false, ['X-Men']],
            'X-Men: First Class'                       => [2011, 132, 'film', false, []],
            'The Wolverine'                            => [2013, 126, 'film', false, ['X-Men: The Last Stand']],
            'X-Men: Days of Future Past'               => [2014, 132, 'film', false, ['The Wolverine', 'X-Men: First Class']],
            'Deadpool'                                 => [2016, 108, 'film', false, []],
            'X-Men: Apocalypse'                        => [2016, 144, 'film', false, ['X-Men: Days of Future Past']],
            'Logan'                                    => [2017, 137, 'film', false, ['X-Men: Days of Future Past']],
            'Deadpool 2'                               => [2018, 119, 'film', false, ['Deadpool']],
            'Dark Phoenix'                             => [2019, 113, 'film', false, ['X-Men: Apocalypse']],
            'The New Mutants'                          => [2020, 94,  'film', false, []],

            // Sony Spider-Man
            'Spider-Man'                               => [2002, 121, 'film', false, []],
            'Spider-Man 2'                             => [2004, 127, 'film', false, ['Spider-Man']],
            'Spider-Man 3'                             => [2007, 139, 'film', false, ['Spider-Man 2']],
            'The Amazing Spider-Man'                   => [2012, 136, 'film', false, []],
            'The Amazing Spider-Man 2'                 => [2014, 142, 'film', false, ['The Amazing Spider-Man']],

            // MCU Phase One
            'Iron Man'                                 => [2008, 126, 'film', false, []],
            'The Incredible Hulk'                      => [2008, 112, 'film', false, []],
            'Iron Man 2'                               => [2010, 124, 'film', false, ['Iron Man']],
            'Thor'                                     => [2011, 114, 'film', false, []],
            'Captain America: The First Avenger'       => [2011, 124, 'film', true,  []],
            'The Avengers'                             => [2012, 143, 'film', true,  ['Iron Man 2', 'The Incredible Hulk', 'Thor', 'Captain America: The First Avenger']],

            // MCU Phase Two
            'Iron Man 3'                               => [2013, 130, 'film', false, ['The Avengers']],
            'Thor: The Dark World'                     => [2013, 112, 'film', false, ['The Avengers']],
            'Captain America: The Winter Soldier'      => [2014, 136, 'film', false, ['The Avengers']],
            'Guardians of the Galaxy'                  => [2014, 121, 'film', false, []],
            'Avengers: Age of Ultron'                  => [2015, 141, 'film', false, ['Iron Man 3', 'Thor: The Dark World', 'Captain America: The Winter Soldier']],
            'Ant-Man'                                  => [2015, 117, 'film', false, ['Avengers: Age of Ultron']],

            // MCU Phase Three
            'Captain America: Civil War'               => [2016, 147, 'film', false, ['Avengers: Age of Ultron', 'Ant-Man']],
            'Doctor Strange'                           => [2016, 115, 'film', false, []],
            'Guardians of the Galaxy Vol. 2'           => [2017, 136, 'film', false, ['Guardians of the Galaxy']],
            'Spider-Man: Homecoming'                   => [2017, 133, 'film', false, ['Captain America: Civil War']],
            'Thor: Ragnarok'                           => [2017, 130, 'film', false, ['Avengers: Age of Ultron', 'Doctor Strange']],
            'Black Panther'                            => [2018, 134, 'film', false, ['Captain America: Civil War']],
            'Avengers: Infinity War'                   => [2018, 149, 'film', true,  ['Thor: Ragnarok', 'Black Panther', 'Guardians of the Galaxy Vol. 2', 'Spider-Man: Homecoming']],
            'Ant-Man and the Wasp'                     => [2018, 118, 'film', false, ['Captain America: Civil War']],
            'Captain Marvel'                           => [2019, 123, 'film', false, []],
            'Avengers: Endgame'                        => [2019, 181, 'film', true,  ['Avengers: Infinity War', 'Ant-Man and the Wasp', 'Captain Marvel']],
            'Spider-Man: Far From Home'                => [2019, 129, 'film', false, ['Avengers: Endgame']],

            // MCU Phase Four
            'WandaVision'                              => [2021, 350, 'series', false, ['Avengers: Endgame']],
            'The Falcon and the Winter Soldier'        => [2021, 300, 'series', false, ['Avengers: Endgame']],
            'Loki (Season 1)'                          => [2021, 285, 'series', true,  ['Avengers: Endgame']],
            'Black Widow'                              => [2021, 134, 'film', false, ['Captain America: Civil War']],
            'What If...? (Season 1)'                   => [2021, 288, 'series', false, ['Loki (Season 1)']],
            'Shang-Chi and the Legend of the Ten Rings' => [2021, 132, 'film', true,  []],
            'Eternals'                                 => [2021, 156, 'film', false, ['Avengers: Endgame']],
            'Hawkeye'                                  => [2021, 300, 'series', false, ['Avengers: Endgame', 'Black Widow']],
            'Spider-Man: No Way Home'                  => [2021, 148, 'film', true,  ['Spider-Man: Far From Home', 'Spider-Man 3', 'The Amazing Spider-Man 2']],
            'Moon Knight'                              => [2022, 282, 'series', false, []],
            'Doctor Strange in the Multiverse of Madness' => [2022, 126, 'film', true, ['WandaVision', 'Spider-Man: No Way Home']],
            'Ms. Marvel'                               => [2022, 270, 'series', false, []],
            'Thor: Love and Thunder'                   => [2022, 119, 'film', false, ['Avengers: Endgame']],
            'I Am Groot (Season 1)'                    => [2022, 20,  'series', false, ['Guardians of the Galaxy Vol. 2']],
            'She-Hulk: Attorney at Law'                => [2022, 297, 'series', false, ['Avengers: Endgame']],
            'Werewolf by Night'                        => [2022, 53,  'film', false, []],
            'Black Panther: Wakanda Forever'           => [2022, 161, 'film', true,  ['Black Panther', 'Avengers: Endgame']],
            'The Guardians of the Galaxy Holiday Special' => [2022, 44, 'film', false, ['Avengers: Endgame']],

            // MCU Phase Five
            'Ant-Man and the Wasp: Quantumania'        => [2023, 125, 'film', false, ['Avengers: Endgame']],
            'Guardians of the Galaxy Vol. 3'           => [2023, 150, 'film', false, ['The Guardians of the Galaxy Holiday Special']],
            'Secret Invasion'                          => [2023, 270, 'series', false, ['Avengers: Endgame']],
            'I Am Groot (Season 2)'                    => [2023, 25,  'series', false, ['I Am Groot (Season 1)']],
            'Loki (Season 2)'                          => [2023, 310, 'series', true,  ['Loki (Season 1)', 'Ant-Man and the Wasp: Quantumania']],
            'The Marvels'                              => [2023, 105, 'film', false, ['Ms. Marvel', 'WandaVision', 'Captain Marvel']],
            'What If...? (Season 2)'                   => [2023, 288, 'series', false, ['What If...? (Season 1)']],
            'Echo'                                     => [2024, 200, 'series', false, ['Hawkeye']],
            'Deadpool & Wolverine'                     => [2024, 128, 'film', true,  ['Loki (Season 2)', 'Logan', 'Deadpool 2']],
            'Agatha All Along'                         => [2024, 333, 'series', false, ['WandaVision']],
            'What If...? (Season 3)'                   => [2024, 240, 'series', false, ['What If...? (Season 2)']],
            'Captain America: Brave New World'         => [2025, 118, 'film', true,  ['The Falcon and the Winter Soldier', 'Eternals']],
            'Daredevil: Born Again (Season 1)'         => [2025, 405, 'series', false, ['She-Hulk: Attorney at Law', 'Echo']],
            'Thunderbolts*'                            => [2025, 127, 'film', true,  ['Black Widow', 'The Falcon and the Winter Soldier', 'Ant-Man and the Wasp']],
            'Ironheart'                                => [2025, 270, 'series', false, ['Black Panther: Wakanda Forever']],
            'The Fantastic Four: First Steps'          => [2025, 114, 'film', true,  []],
            'Eyes of Wakanda'                          => [2025, 120, 'series', false, ['Black Panther: Wakanda Forever']],
            'Marvel Zombies'                           => [2025, 160, 'series', false, ['What If...? (Season 1)']],

            // Phase Six, up to Doomsday
            'Wonder Man'                               => [2026, 264, 'series', false, []],
            'Daredevil: Born Again (Season 2)'         => [2026, 400, 'series', false, ['Daredevil: Born Again (Season 1)']],
            'The Punisher: One Last Kill'              => [2026, 50,  'film', false, ['Daredevil: Born Again (Season 2)']],
            'Spider-Man: Brand New Day'                => [2026, 150, 'film', false, ['Spider-Man: No Way Home']],
            'VisionQuest'                              => [2026, 360, 'series', false, ['WandaVision']],
            // Runtime not announced yet: 150 is a placeholder. Every leaf title is added below as well.
            'Avengers: Doomsday'                       => [2026, 150, 'film', true,  [
                'Thunderbolts*',
                'The Fantastic Four: First Steps',
                'Deadpool & Wolverine',
                'Doctor Strange in the Multiverse of Madness',
                'Black Panther: Wakanda Forever',
                'Shang-Chi and the Legend of the Ten Rings',
                'Captain America: Brave New World',
                'Loki (Season 2)',
                'Spider-Man: Brand New Day',
            ]],
        ];

        // Titles that nothing depends on also become prerequisites of Doomsday (full view = everything)
        $isPrerequisite = [];
        foreach ($titles as [, , , , $prerequisites]) {
            foreach ($prerequisites as $p) {
                $isPrerequisite[$p] = true;
            }
        }
        foreach (array_keys($titles) as $title) {
            if ($title !== 'Avengers: Doomsday' && !isset($isPrerequisite[$title])) {
                $titles['Avengers: Doomsday'][4][] = $title;
            }
        }

        $this->seedGraph($titles);
    }
}
