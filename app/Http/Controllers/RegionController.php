<?php

namespace App\Http\Controllers;

use App\Models\CulturalEntry;
use App\Models\Media;
use App\Models\Region;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegionController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $provinceQuery = Region::query()
            ->where('region_type', 'province')
            ->where('is_active', true);

        $provinceCount = (clone $provinceQuery)->count();
        $search = trim($validated['search'] ?? '');

        $regions = $provinceQuery
            ->withCount([
                'culturalEntries' => fn ($query) => $query->publiclyVisible(),
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->get()
            ->each(function (Region $region): void {
                $region->setAttribute('artwork', config('region_artworks.'.$region->slug));
            });

        return view('regions.index', compact('regions', 'provinceCount'));
    }

    public function show(string $slug): View
    {
        $region = Region::query()
            ->where('region_type', 'province')
            ->where('is_active', true)
            ->where('slug', $slug)
            ->withCount([
                'culturalEntries' => fn ($query) => $query->publiclyVisible(),
            ])
            ->firstOrFail();

        $entries = CulturalEntry::query()
            ->publiclyVisible()
            ->where('origin_region_id', $region->id)
            ->with([
                'categories',
                'media' => fn ($query) => $query
                    ->where('permission_status', Media::PERMISSION_GRANTED)
                    ->orderByDesc('is_cover')
                    ->orderBy('sort_order'),
            ])
            ->orderBy('title')
            ->paginate(12)
            ->withQueryString();

        return view('regions.show', compact('region', 'entries'));
    }
}
