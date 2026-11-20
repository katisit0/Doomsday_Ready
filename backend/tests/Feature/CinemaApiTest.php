<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CinemaApiTest extends TestCase
{
    use RefreshDatabase;

    private function addCinema(string $name, array $sizes): int
    {
        return $this->postJson('/api/cinemas', ['name' => $name, 'bundle_sizes' => $sizes])
            ->assertCreated()
            ->json('id');
    }

    private function book(int $cinemaId, int $groupSize): array
    {
        return $this->getJson("/api/cinemas/$cinemaId/booking?groupSize=$groupSize")->assertOk()->json();
    }

    public function test_sizes_1_3_4_group_6_uses_two_bundles_of_3(): void
    {
        $result = $this->book($this->addCinema('Cinema A', [1, 3, 4]), 6);

        $this->assertTrue($result['possible']);
        $this->assertSame([['size' => 3, 'count' => 2]], $result['bundles']);
    }

    public function test_sizes_2_5_group_11(): void
    {
        $result = $this->book($this->addCinema('Cinema B', [2, 5]), 11);

        $this->assertSame([['size' => 5, 'count' => 1], ['size' => 2, 'count' => 3]], $result['bundles']);
    }

    public function test_sizes_6_9_20_group_44(): void
    {
        $result = $this->book($this->addCinema('Cinema C', [6, 9, 20]), 44);

        $this->assertSame(
            [['size' => 20, 'count' => 1], ['size' => 9, 'count' => 2], ['size' => 6, 'count' => 1]],
            $result['bundles'],
        );
    }

    public function test_sizes_4_6_group_7_is_not_possible(): void
    {
        $result = $this->book($this->addCinema('Cinema D', [4, 6]), 7);

        $this->assertFalse($result['possible']);
    }

    public function test_largest_group_size_works(): void
    {
        $result = $this->book($this->addCinema('Cinema C', [6, 9, 20]), 10000);

        $seats = array_sum(array_map(fn ($b) => $b['size'] * $b['count'], $result['bundles']));
        $this->assertSame(10000, $seats);
    }

    public function test_invalid_group_sizes_are_rejected(): void
    {
        $id = $this->addCinema('Cinema A', [1, 3, 4]);

        foreach (['0', '10001', 'abc', '2.5', ''] as $groupSize) {
            $this->getJson("/api/cinemas/$id/booking?groupSize=$groupSize")
                ->assertStatus(422)
                ->assertJsonPath('error.code', 'VALIDATION_ERROR');
        }
    }

    public function test_invalid_cinemas_are_rejected(): void
    {
        $this->addCinema('Cinema A', [1, 3, 4]);

        foreach ([
            ['name' => 'Cinema A', 'bundle_sizes' => [2]],          // duplicate name
            ['name' => 'X', 'bundle_sizes' => []],                  // no sizes
            ['name' => 'X', 'bundle_sizes' => range(1, 11)],        // more than 10 sizes
            ['name' => 'X', 'bundle_sizes' => [0]],                 // size too small
            ['name' => 'X', 'bundle_sizes' => [501]],               // size too big
            ['name' => 'X', 'bundle_sizes' => [2, 2]],              // repeated size
        ] as $body) {
            $this->postJson('/api/cinemas', $body)->assertStatus(422);
        }
    }

    public function test_cinemas_are_listed_with_sorted_bundle_sizes(): void
    {
        $this->addCinema('Cinema A', [4, 1, 3]);

        $this->getJson('/api/cinemas')
            ->assertOk()
            ->assertJsonPath('0.name', 'Cinema A')
            ->assertJsonPath('0.bundles.*.size', [1, 3, 4]);
    }

    public function test_cinema_names_are_unique_ignoring_case(): void
    {
        $this->addCinema('Cinema A', [1, 3, 4]);

        $this->postJson('/api/cinemas', ['name' => ' cinema a', 'bundle_sizes' => [2]])->assertStatus(422);
    }

    public function test_answer_does_not_depend_on_the_order_sizes_were_given(): void
    {
        // 6 seats with [1, 2, 3, 4, 5]: several two-bundle answers exist (5+1, 4+2, 3+3)
        $a = $this->book($this->addCinema('Up', [1, 2, 3, 4, 5]), 6);
        $b = $this->book($this->addCinema('Down', [5, 4, 3, 2, 1]), 6);

        $this->assertSame(2, $a['total_bundles']);
        $this->assertSame($a['bundles'], $b['bundles']);
    }

    public function test_seeders_create_the_example_cinemas(): void
    {
        $this->seed();
        $this->seed(); // running twice changes nothing

        $this->getJson('/api/cinemas')->assertJsonCount(4)->assertJsonPath('2.bundles.*.size', [6, 9, 20]);
        $this->assertCount(8, $this->getJson('/api/movies')->json());
    }

    public function test_unknown_cinema_is_not_found(): void
    {
        $this->getJson('/api/cinemas/999/booking?groupSize=6')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }
}
