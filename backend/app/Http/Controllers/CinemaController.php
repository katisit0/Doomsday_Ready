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
            'name'           => ['required', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail) {
                // unique ignoring upper/lower case and outer spaces (both sides lowered and trimmed by the database)
                if (is_string($value) && Cinema::whereRaw('LOWER(TRIM(name)) = LOWER(TRIM(?))', [$value])->exists()) {
                    $fail('The name has already been taken.');
                }
            }],
            'bundle_sizes'   => 'required|array|min:1|max:10',
            'bundle_sizes.*' => 'integer|distinct|between:1,500',
        ], [], [
            // readable names in messages ("bundle size" instead of "bundle_sizes.0")
            'bundle_sizes'   => 'bundle sizes',
            'bundle_sizes.*' => 'bundle size',
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
     * Fewest bundles that add up to exactly groupSize seats, or "not possible".
     */
    public function booking(Request $request, Cinema $cinema)
    {
        $data = $request->validate([
            'groupSize' => 'required|integer|between:1,10000',
        ]);
        $groupSize = (int) $data['groupSize'];

        // Largest first: when two answers use the same number of bundles, the one found first
        // (preferring larger bundles as the last pick) wins, so the answer never depends on insert order.
        $sizes = $cinema->bundles()->orderByDesc('size')->pluck('size')->all();
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
