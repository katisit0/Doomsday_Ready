<?php

namespace App\Http\Controllers;

use App\Models\Cinema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CinemaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Cinema::with(['bundles' => fn ($q) => $q->orderBy('size')])->orderBy('id')->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:255|unique:cinemas,name',
            'bundle_sizes'   => 'required|array|min:1|max:10',
            'bundle_sizes.*' => 'integer|distinct|between:1,500',
        ]);

        $cinema = DB::transaction(function () use ($data) {
            $cinema = Cinema::create(['name' => $data['name']]);

            foreach ($data['bundle_sizes'] as $size) {
                $cinema->bundles()->create(['size' => $size]);
            }

            return $cinema;
        });

        return response()->json($cinema->load(['bundles' => fn ($q) => $q->orderBy('size')]), 201);
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
