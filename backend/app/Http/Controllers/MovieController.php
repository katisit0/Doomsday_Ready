<?php

namespace App\Http\Controllers;

use App\Models\Movie;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use PhpOption\None;

class MovieController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Movie::with('prerequisites')->orderBy('id')->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title'              => 'required|string|max:255|unique:movies,title',
            'release_year'       => 'required|integer|between:1900,2100',
            'runtime'            => 'required|integer|between:1,300',
            'prerequisite_ids'   => 'sometimes|array',
            'prerequisite_ids.*' => 'integer|distinct|exists:movies,id',
        ]);

        $movie = Movie::create([
            'title'        => $data['title'],
            'release_year' => $data['release_year'],
            'runtime'      => $data['runtime'],
        ]);

        $movie->prerequisites()->attach($data['prerequisite_ids'] ?? []);
        $movie->refresh();
        return response()->json($movie->load('prerequisites'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Movie $movie)
    {
        $data = $request->validate([
            'title'              => ['sometimes', 'string', 'max:255', Rule::unique('movies', 'title')->ignore($movie->id)],
            'release_year'       => 'sometimes|integer|between:1900,2100',
            'runtime'            => 'sometimes|integer|between:1,300',
            'watched'            => 'sometimes|boolean',
            'prerequisite_ids'   => 'sometimes|array',
            'prerequisite_ids.*' => ['integer', 'distinct', 'exists:movies,id', Rule::notIn([$movie->id])],
        ]);

        // Cycle check
        if (array_key_exists('prerequisite_ids', $data)) {
            $toVisit = $data['prerequisite_ids'];
            $visited = [];

            while (!empty($toVisit)) {
                $id = array_pop($toVisit);

                if ($id == $movie->id) {
                    return response()->json([
                        'error' => [
                            'code'    => 'CIRCULAR_DEPENDENCY',
                            'message' => 'This change would make the movie depend on itself.',
                        ],
                    ], 409);
                }

                if (isset($visited[$id])) {
                    continue;
                }
                $visited[$id] = true;

                $prerequisiteMovie = Movie::find($id);
                array_push($toVisit, ...$prerequisiteMovie->prerequisites()->pluck('movies.id')->all());
            }
        }

        DB::transaction(function () use ($movie, $data) {
            $movie->update(Arr::except($data, ['prerequisite_ids']));

            if (array_key_exists('prerequisite_ids', $data)) {
                $movie->prerequisites()->sync($data['prerequisite_ids']);
            }
        });

        return $movie->refresh()->load('prerequisites');
    }

    /**
     * Every movie to watch before $movie: each once, each after its own prerequisites.
     */
    public function rewatchOrder(Movie $movie)
    {
        // Load all prerequisite links once: [movie_id => [prerequisite_id, ...]]
        $graph = [];
        foreach (DB::table('movie_prerequisites')->orderBy('prerequisite_id')->get() as $link) {
            $graph[$link->movie_id][] = $link->prerequisite_id;
        }

        $visited = [];
        $orderIds = [];
        foreach ($graph[$movie->id] ?? [] as $prerequisiteId) {
            $this->visit($prerequisiteId, $graph, $visited, $orderIds);
        }

        $movies = Movie::whereIn('id', $orderIds)->get()->keyBy('id');
        $order = array_map(fn ($id) => $movies[$id], $orderIds);

        $watchedCount = 0;
        $remainingRuntime = 0;
        foreach ($order as $m) {
            if ($m->watched) {
                $watchedCount++;
            } else {
                $remainingRuntime += $m->runtime;
            }
        }

        return [
            'movie'             => $movie,
            'order'             => $order,
            'watched_count'     => $watchedCount,
            'total_count'       => count($order),
            'remaining_runtime' => $remainingRuntime,
        ];
    }

    /**
     * Depth-first, post-order: add a movie only after all its prerequisites are added.
     */
    private function visit(int $id, array $graph, array &$visited, array &$orderIds): void
    {
        if (isset($visited[$id])) {
            return;
        }
        $visited[$id] = true;

        foreach ($graph[$id] ?? [] as $prerequisiteId) {
            $this->visit($prerequisiteId, $graph, $visited, $orderIds);
        }

        $orderIds[] = $id;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
