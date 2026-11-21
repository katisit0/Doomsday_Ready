<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovieApiTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, int> title => id */
    private array $ids = [];

    private function addMovie(string $title, int $year, int $runtime, array $prerequisites = []): int
    {
        $response = $this->postJson('/api/movies', [
            'title'            => $title,
            'release_year'     => $year,
            'runtime'          => $runtime,
            'prerequisite_ids' => array_map(fn ($t) => $this->ids[$t], $prerequisites),
        ])->assertCreated();

        return $this->ids[$title] = $response->json('id');
    }

    /** The 8-movie graph used for the rewatch examples. */
    private function seedGraph(): void
    {
        $this->addMovie('Iron Man', 2008, 126);
        $this->addMovie('Captain America: The First Avenger', 2011, 124);
        $this->addMovie('The Avengers', 2012, 143, ['Iron Man', 'Captain America: The First Avenger']);
        $this->addMovie('Captain America: Civil War', 2016, 147, ['The Avengers']);
        $this->addMovie('Thor: Ragnarok', 2017, 130);
        $this->addMovie('Avengers: Infinity War', 2018, 149, ['Captain America: Civil War', 'Thor: Ragnarok']);
        $this->addMovie('Avengers: Endgame', 2019, 181, ['Avengers: Infinity War']);
        $this->addMovie('Avengers: Doomsday', 2026, 150, ['Avengers: Endgame']);
    }

    /** Every movie in the order appears once and after all of its own prerequisites. */
    private function assertValidOrder(array $orderIds): void
    {
        $this->assertSame($orderIds, array_values(array_unique($orderIds)), 'a movie appears twice');
        $position = array_flip($orderIds);

        foreach ($orderIds as $id) {
            $prerequisites = $this->getJson('/api/movies')->collect()->firstWhere('id', $id)['prerequisites'];
            foreach ($prerequisites as $prerequisite) {
                $this->assertArrayHasKey($prerequisite['id'], $position, 'a prerequisite is missing');
                $this->assertLessThan($position[$id], $position[$prerequisite['id']], 'a prerequisite comes too late');
            }
        }
    }

    public function test_doomsday_needs_the_seven_other_movies_in_a_valid_order(): void
    {
        $this->seedGraph();

        $response = $this->getJson("/api/movies/{$this->ids['Avengers: Doomsday']}/rewatch-order")->assertOk();
        $orderIds = array_column($response->json('order'), 'id');

        $this->assertCount(7, $orderIds);
        $this->assertNotContains($this->ids['Avengers: Doomsday'], $orderIds);
        $this->assertValidOrder($orderIds);
    }

    public function test_civil_war_needs_iron_man_and_first_avenger_then_the_avengers(): void
    {
        $this->seedGraph();

        $response = $this->getJson("/api/movies/{$this->ids['Captain America: Civil War']}/rewatch-order")->assertOk();
        $titles = array_column($response->json('order'), 'title');

        $this->assertCount(3, $titles);
        $this->assertEqualsCanonicalizing(['Iron Man', 'Captain America: The First Avenger'], array_slice($titles, 0, 2));
        $this->assertSame('The Avengers', $titles[2]);
        $response->assertJson(['watched_count' => 0, 'total_count' => 3, 'remaining_runtime' => 126 + 124 + 143]);
    }

    public function test_ragnarok_needs_nothing(): void
    {
        $this->seedGraph();

        $this->getJson("/api/movies/{$this->ids['Thor: Ragnarok']}/rewatch-order")
            ->assertOk()
            ->assertJson(['order' => [], 'watched_count' => 0, 'total_count' => 0, 'remaining_runtime' => 0]);
    }

    public function test_endgame_as_prerequisite_of_iron_man_is_a_circular_dependency(): void
    {
        $this->seedGraph();

        $this->patchJson("/api/movies/{$this->ids['Iron Man']}", ['prerequisite_ids' => [$this->ids['Avengers: Endgame']]])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'CIRCULAR_DEPENDENCY');

        $this->assertDatabaseMissing('movie_prerequisites', ['movie_id' => $this->ids['Iron Man']]);
    }

    public function test_watched_movies_count_and_are_left_out_of_remaining_runtime(): void
    {
        $this->seedGraph();
        $this->patchJson("/api/movies/{$this->ids['Iron Man']}", ['watched' => true])->assertOk()->assertJsonPath('watched', true);

        $this->getJson("/api/movies/{$this->ids['Captain America: Civil War']}/rewatch-order")
            ->assertJson(['watched_count' => 1, 'total_count' => 3, 'remaining_runtime' => 124 + 143]);
    }

    public function test_invalid_movies_are_rejected(): void
    {
        $this->addMovie('Iron Man', 2008, 126);

        foreach ([
            ['title' => 'Iron Man', 'release_year' => 2008, 'runtime' => 126],   // duplicate title
            ['title' => 'A', 'release_year' => 2008, 'runtime' => 0],            // runtime too short
            ['title' => 'B', 'release_year' => 2008, 'runtime' => 301],          // runtime too long
            ['title' => 'C', 'release_year' => 2008, 'runtime' => 100, 'prerequisite_ids' => [999]], // unknown prerequisite
            [],                                                                  // nothing sent
        ] as $body) {
            $this->postJson('/api/movies', $body)
                ->assertStatus(422)
                ->assertJsonPath('error.code', 'VALIDATION_ERROR');
        }
    }

    public function test_a_movie_cannot_be_its_own_prerequisite(): void
    {
        $id = $this->addMovie('Iron Man', 2008, 126);

        // "never depend on itself, either directly or through other movies" -> 409
        $this->patchJson("/api/movies/$id", ['prerequisite_ids' => [$id]])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'CIRCULAR_DEPENDENCY');
    }

    public function test_watched_can_be_set_when_creating(): void
    {
        $this->postJson('/api/movies', ['title' => 'Iron Man', 'release_year' => 2008, 'runtime' => 126])
            ->assertJsonPath('watched', false);
        $this->postJson('/api/movies', ['title' => 'Thor', 'release_year' => 2011, 'runtime' => 114, 'watched' => true])
            ->assertJsonPath('watched', true);
    }

    public function test_default_seed_is_the_pdf_sample_data(): void
    {
        $this->seed();
        $id = fn ($title) => $this->getJson('/api/movies')->collect()->firstWhere('title', $title)['id'];

        $this->assertCount(8, $this->getJson('/api/movies')->json());
        $this->getJson('/api/movies/' . $id('Avengers: Doomsday') . '/rewatch-order')->assertJsonPath('total_count', 7);
        $this->getJson('/api/movies/' . $id('Thor: Ragnarok') . '/rewatch-order')->assertJsonPath('order', []);
        $this->assertSame(
            'The Avengers',
            $this->getJson('/api/movies/' . $id('Captain America: Civil War') . '/rewatch-order')->json('order.2.title'),
        );
    }

    public function test_loading_full_mcu_data_adds_both_views_and_keeps_watched_ticks(): void
    {
        $this->seed();
        $ironMan = $this->getJson('/api/movies')->collect()->firstWhere('title', 'Iron Man')['id'];
        $this->patchJson("/api/movies/$ironMan", ['watched' => true])->assertOk();

        $this->postJson('/api/movies/load-mcu')->assertOk()->assertJson(['loaded' => true, 'movies_count' => 83]);
        $this->postJson('/api/movies/load-mcu')->assertOk()->assertJson(['movies_count' => 83]); // again: no duplicates

        $movies = $this->getJson('/api/movies')->collect();
        $this->assertTrue($movies->firstWhere('title', 'Iron Man')['watched']);

        $doomsday = $movies->firstWhere('title', 'Avengers: Doomsday')['id'];
        $this->getJson("/api/movies/$doomsday/rewatch-order")->assertJsonPath('total_count', 82);
        $this->getJson("/api/movies/$doomsday/rewatch-order?essentials=1")->assertJsonPath('total_count', 16);
    }

    public function test_a_series_season_may_run_longer_than_a_film(): void
    {
        $this->postJson('/api/movies', ['title' => 'Long film', 'release_year' => 2020, 'runtime' => 301])
            ->assertStatus(422);

        $this->postJson('/api/movies', ['title' => 'Loki (Season 1)', 'release_year' => 2021, 'runtime' => 301, 'type' => 'series'])
            ->assertCreated()
            ->assertJson(['type' => 'series', 'essential' => false]);

        $this->postJson('/api/movies', ['title' => 'Too long', 'release_year' => 2021, 'runtime' => 6001, 'type' => 'series'])
            ->assertStatus(422);

        $this->postJson('/api/movies', ['title' => 'Bad type', 'release_year' => 2021, 'runtime' => 100, 'type' => 'podcast'])
            ->assertStatus(422);
    }

    public function test_essentials_view_keeps_only_essential_titles_in_the_same_order(): void
    {
        $this->seedGraph();
        foreach (['Iron Man', 'The Avengers', 'Avengers: Endgame'] as $title) {
            $this->patchJson("/api/movies/{$this->ids[$title]}", ['essential' => true])->assertOk();
        }

        $doomsday = $this->ids['Avengers: Doomsday'];
        $full = array_column($this->getJson("/api/movies/$doomsday/rewatch-order")->json('order'), 'title');
        $essentials = $this->getJson("/api/movies/$doomsday/rewatch-order?essentials=1")
            ->assertOk()
            ->assertJsonPath('total_count', 3)
            ->json('order');

        $this->assertSame(
            array_values(array_intersect($full, ['Iron Man', 'The Avengers', 'Avengers: Endgame'])),
            array_column($essentials, 'title'),
        );
    }

    public function test_titles_are_unique_ignoring_case_and_spaces(): void
    {
        $this->addMovie('Iron Man', 2008, 126);
        $other = $this->addMovie('Thor', 2011, 114);

        $this->postJson('/api/movies', ['title' => '  iron MAN ', 'release_year' => 2008, 'runtime' => 126])->assertStatus(422);
        $this->patchJson("/api/movies/$other", ['title' => 'IRON MAN'])->assertStatus(422);
        $this->patchJson("/api/movies/$other", ['title' => 'THOR'])->assertOk(); // its own title in new case is fine
    }

    public function test_changing_series_to_film_checks_the_runtime(): void
    {
        $id = $this->postJson('/api/movies', ['title' => 'Loki (Season 2)', 'release_year' => 2023, 'runtime' => 310, 'type' => 'series'])
            ->assertCreated()->json('id');

        $this->patchJson("/api/movies/$id", ['type' => 'film'])->assertStatus(422)->assertJsonValidationErrors('runtime', 'error.fields');
        $this->patchJson("/api/movies/$id", ['type' => 'film', 'runtime' => 120])->assertOk()->assertJson(['type' => 'film', 'runtime' => 120]);
    }

    public function test_prerequisites_have_no_pivot_field(): void
    {
        $this->seedGraph();

        $avengers = $this->getJson('/api/movies')->collect()->firstWhere('title', 'The Avengers');
        $this->assertArrayNotHasKey('pivot', $avengers['prerequisites'][0]);
    }

    public function test_unknown_movie_is_not_found(): void
    {
        $this->getJson('/api/movies/999/rewatch-order')->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
        $this->patchJson('/api/movies/999', ['watched' => true])->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_unsupported_method_is_method_not_allowed(): void
    {
        $this->deleteJson('/api/movies/1')->assertStatus(405)->assertJsonPath('error.code', 'METHOD_NOT_ALLOWED');
    }

    public function test_duplicate_non_ascii_title_is_a_validation_error(): void
    {
        $this->addMovie('Élan', 2020, 100);

        $this->postJson('/api/movies', ['title' => 'Élan', 'release_year' => 2020, 'runtime' => 100])
            ->assertStatus(422)
            ->assertJsonPath('error.fields.title.0', 'The title has already been taken.');
    }

    public function test_title_that_is_not_text_is_a_validation_error(): void
    {
        $this->postJson('/api/movies', ['title' => ['a'], 'release_year' => 2020, 'runtime' => 100])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }
}
