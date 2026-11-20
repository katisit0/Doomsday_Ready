<?php

namespace App\Http\Controllers;

use App\Models\Movie;
use Database\Seeders\McuMovieSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
        $type = in_array($request->input('type'), Movie::TYPES, true) ? $request->input('type') : 'film';

        $data = $request->validate([
            'title'              => ['required', 'string', 'max:255', Movie::uniqueTitle()],
            'release_year'       => 'required|integer|between:1900,2100',
            'type'               => ['sometimes', Rule::in(Movie::TYPES)],
            'runtime'            => 'required|integer|between:1,' . Movie::MAX_RUNTIME[$type],
            'watched'            => 'sometimes|boolean',
            'essential'          => 'sometimes|boolean',
            'prerequisite_ids'   => 'sometimes|array',
            'prerequisite_ids.*' => 'integer|distinct|exists:movies,id',
        ]);

        $movie = DB::transaction(function () use ($data, $type) {
            $movie = Movie::create([
                'title'        => $data['title'],
                'release_year' => $data['release_year'],
                'runtime'      => $data['runtime'],
                'type'         => $type,
                'watched'      => $data['watched'] ?? false,
                'essential'    => $data['essential'] ?? false,
            ]);

            $movie->prerequisites()->attach($data['prerequisite_ids'] ?? []);

            return $movie;
        });

        $movie->refresh();
        return response()->json($movie->load('prerequisites'), 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Movie $movie)
    {
        $type = in_array($request->input('type'), Movie::TYPES, true) ? $request->input('type') : $movie->type;

        $data = $request->validate([
            'title'              => ['sometimes', 'string', 'max:255', Movie::uniqueTitle($movie->id)],
            'release_year'       => 'sometimes|integer|between:1900,2100',
            'type'               => ['sometimes', Rule::in(Movie::TYPES)],
            'runtime'            => 'sometimes|integer|between:1,' . Movie::MAX_RUNTIME[$type],
            'watched'            => 'sometimes|boolean',
            'essential'          => 'sometimes|boolean',
            'prerequisite_ids'   => 'sometimes|array',
            // The movie itself in its own list is left to the cycle check below: 409 CIRCULAR_DEPENDENCY
            'prerequisite_ids.*' => ['integer', 'distinct', 'exists:movies,id'],
        ]);

        // Changing the type without sending a runtime: the current runtime must still fit the new type
        if (!array_key_exists('runtime', $data) && $movie->runtime > Movie::MAX_RUNTIME[$type]) {
            throw ValidationException::withMessages([
                'runtime' => "A {$type} can run at most " . Movie::MAX_RUNTIME[$type] . ' minutes; send a new runtime as well.',
            ]);
        }

        // One graph change at a time: the cycle check and the write happen under the same lock,
        // so two requests cannot each pass the check and together create a loop.
        return Cache::lock('movie-graph', 10)->block(5, function () use ($movie, $data) {
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
        });
    }

    /**
     * Loads the optional full MCU data set (extra endpoint, behind the "Load full MCU data" button).
     * Safe to call again; watched ticks and movies added by hand are kept.
     */
    public function loadMcu()
    {
        Cache::lock('movie-graph', 30)->block(10, fn () => app(McuMovieSeeder::class)->run());

        return response()->json([
            'loaded'       => true,
            'movies_count' => Movie::count(),
        ]);
    }

    /**
     * Every movie to watch before $movie: each once, each after its own prerequisites.
     * With ?essentials=1 only essential titles are kept (a subset of a valid order is still valid).
     */
    public function rewatchOrder(Request $request, Movie $movie)
    {
        $request->validate(['essentials' => 'sometimes|boolean']);

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

        // Essentials view: same order, non-essential titles left out
        if ($request->boolean('essentials')) {
            $order = array_values(array_filter($order, fn ($m) => $m->essential));
        }

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
}
