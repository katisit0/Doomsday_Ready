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

    /**
     * Fewest bundles that add up to exactly groupSize seats, or "not possible".
     */
    public function booking(Request $request, Cinema $cinema)
    {
        $data = $request->validate([
            'groupSize' => 'required|integer|between:1,10000',
        ]);
        $groupSize = (int) $data['groupSize'];

        $sizes = $cinema->bundles()->pluck('size')->all();
        $counts = $this->fewestBundles($sizes, $groupSize);

        if ($counts === null) {
            return [
                'cinema'     => $cinema->name,
                'group_size' => $groupSize,
                'possible'   => false,
            ];
        }

        krsort($counts); // largest size first
        $bundles = [];
        foreach ($counts as $size => $count) {
            $bundles[] = ['size' => $size, 'count' => $count];
        }

        return [
            'cinema'        => $cinema->name,
            'group_size'    => $groupSize,
            'possible'      => true,
            'total_bundles' => array_sum($counts),
            'bundles'       => $bundles,
        ];
    }

    /**
     * Returns [size => count, ...] using the fewest bundles, or null when groupSize can't be made exactly.
     */
    private function fewestBundles(array $sizes, int $groupSize): ?array
    {
        // best[n] = fewest bundles that make exactly n seats (null = not possible yet)
        // last[n] = the bundle size used last to reach best[n]
        $best = array_fill(0, $groupSize + 1, null);
        $last = array_fill(0, $groupSize + 1, null);
        $best[0] = 0; // 0 seats need 0 bundles

        // For each n, try every size in $sizes:
        for ($n = 1; $n <= $groupSize; $n++) {
            foreach ($sizes as $size) {
                if ($size > $n) {
                    continue; // bundle bigger than n
                }
                if ($best[$n - $size] === null) {
                    continue; // what's left can't be made
                }
                if ($best[$n] === null || $best[$n] > $best[$n - $size] + 1) {
                    $best[$n] = $best[$n - $size] + 1;
                    $last[$n] = $size;
                }
            }
        }

        if ($best[$groupSize] === null) {
            return null; // not possible
        }

        // Walk back from groupSize using last[] and count each size
        $counts = [];
        $n = $groupSize;
        while ($n > 0) {
            $size = $last[$n];
            $counts[$size] = ($counts[$size] ?? 0) + 1;
            $n -= $size;
        }

        return $counts;
    }
}
