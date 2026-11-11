<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CulturalEntry;
use App\Models\Media;
use App\Models\Region;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CulturalEntryController extends Controller
{
    private function publicMediaRelations(): array
    {
        return [
            'media' => fn ($query) => $query
                ->where('permission_status', Media::PERMISSION_GRANTED)
                ->where(function ($query): void {
                    $query->where(function ($query): void {
                        $query->whereNotNull('file_path')
                            ->where('file_path', '<>', '');
                    })->orWhere(function ($query): void {
                        $query->whereNotNull('external_url')
                            ->where('external_url', '<>', '');
                    });
                })
                ->orderByDesc('is_cover')
                ->orderBy('sort_order'),
        ];
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'region_id' => [
                'nullable',
                'integer',
                Rule::exists('regions', 'id')->where(fn ($query) => $query
                    ->where('region_type', 'province')
                    ->where('is_active', true)),
            ],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('is_active', true),
            ],
        ]);

        $regions = Region::query()
            ->where('region_type', 'province')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $query = CulturalEntry::query()
            ->approvedForPublication()
            ->with([
                'region',
                'categories',
                ...$this->publicMediaRelations(),
                'sources',
            ]);

        $search = trim($validated['search'] ?? '');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('short_description', 'like', "%{$search}%");
            });
        }

        if (! empty($validated['region_id'])) {
            $query->where('origin_region_id', $validated['region_id']);
        }

        if (! empty($validated['category_id'])) {
            $query->whereHas('categories', fn ($categoryQuery) => $categoryQuery
                ->whereKey($validated['category_id']));
        }

        $entries = $query
            ->orderBy('title')
            ->paginate(12)
            ->withQueryString();

        return view('cultural-entries.index', compact('entries', 'regions', 'categories'));
    }

    public function show(string $slug)
    {
        $entry = CulturalEntry::query()
            ->approvedForPublication()
            ->where('slug', $slug)
            ->with([
                'region',
                'categories',
                'sources',
                ...$this->publicMediaRelations(),
            ])
            ->firstOrFail();

        $categoryIds = $entry->categories->modelKeys();
        $regionId = $entry->origin_region_id;

        $relatedEntries = CulturalEntry::query()
            ->approvedForPublication()
            ->where('id', '!=', $entry->id)
            ->where(function ($query) use ($categoryIds, $regionId) {
                if ($categoryIds !== []) {
                    $query->whereHas('categories', fn ($categoryQuery) => $categoryQuery
                        ->whereIn('categories.id', $categoryIds));
                }

                if ($regionId) {
                    if ($categoryIds === []) {
                        $query->where('origin_region_id', $regionId);
                    } else {
                        $query->orWhere('origin_region_id', $regionId);
                    }
                } elseif ($categoryIds === []) {
                    $query->whereRaw('1 = 0');
                }
            })
            ->with([
                'region',
                'categories',
                ...$this->publicMediaRelations(),
            ])
            ->orderBy('title')
            ->limit(3)
            ->get();

        return view('cultural-entries.show', compact('entry', 'relatedEntries'));
    }
}
