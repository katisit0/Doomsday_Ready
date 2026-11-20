<?php

namespace App\Http\Controllers;

use App\Models\Movie;
use Illuminate\Http\Request;

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
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
