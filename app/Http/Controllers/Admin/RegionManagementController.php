<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CulturalEntry;
use App\Models\Region;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RegionManagementController extends Controller
{
    public function page(): View
    {
        return view('admin.regions.index');
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $regions = Region::query()
            ->withCount([
                'children',
                'culturalEntries as cultural_entries_count' => fn ($query) => $query->withTrashed(),
            ])
            ->when(trim($filters['search'] ?? '') !== '', function ($query) use ($filters): void {
                $search = trim($filters['search']);
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when(isset($filters['status']), function ($query) use ($filters): void {
                $query->where('is_active', $filters['status'] === 'active');
            })
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return response()->json([
            'data' => $regions->getCollection()
                ->map(fn (Region $region): array => $this->regionData($region))
                ->all(),
            'meta' => [
                'current_page' => $regions->currentPage(),
                'per_page' => $regions->perPage(),
                'last_page' => $regions->lastPage(),
                'total' => $regions->total(),
                'from' => $regions->firstItem(),
                'to' => $regions->lastItem(),
            ],
            'links' => [
                'first' => $regions->url(1),
                'last' => $regions->url($regions->lastPage()),
                'prev' => $regions->previousPageUrl(),
                'next' => $regions->nextPageUrl(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());
        try {
            $region = Region::query()->create($this->attributes($data, true));
        } catch (UniqueConstraintViolationException) {
            return $this->duplicateSlugResponse();
        }

        return response()->json([
            'message' => 'Wilayah berhasil ditambahkan.',
            'data' => $this->regionData($region->loadCount([
                'children',
                'culturalEntries as cultural_entries_count' => fn ($query) => $query->withTrashed(),
            ])),
        ], JsonResponse::HTTP_CREATED);
    }

    public function show(Region $region): JsonResponse
    {
        $region->loadCount([
            'children',
            'culturalEntries as cultural_entries_count' => fn ($query) => $query->withTrashed(),
        ]);

        return response()->json(['data' => $this->regionData($region)]);
    }

    public function update(Request $request, Region $region): JsonResponse
    {
        $data = $request->validate($this->rules($region));

        if (array_key_exists('parent_id', $data)
            && $data['parent_id'] !== null
            && $this->isDescendant((int) $data['parent_id'], $region)) {
            return response()->json([
                'message' => 'Wilayah tidak dapat dipindahkan ke dalam turunannya sendiri.',
                'errors' => ['parent_id' => ['Pilih wilayah induk yang tidak membentuk siklus.']],
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $region->fill($this->attributes($data));
            $region->save();
        } catch (UniqueConstraintViolationException) {
            return $this->duplicateSlugResponse();
        }

        $region->loadCount([
            'children',
            'culturalEntries as cultural_entries_count' => fn ($query) => $query->withTrashed(),
        ]);

        return response()->json([
            'message' => 'Wilayah berhasil diperbarui.',
            'data' => $this->regionData($region),
        ]);
    }

    public function setStatus(Request $request, Region $region): JsonResponse
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $region->is_active = (bool) $data['is_active'];
        $region->save();
        $region->loadCount([
            'children',
            'culturalEntries as cultural_entries_count' => fn ($query) => $query->withTrashed(),
        ]);

        return response()->json([
            'message' => $region->is_active
                ? 'Wilayah berhasil diaktifkan.'
                : 'Wilayah berhasil dinonaktifkan.',
            'data' => $this->regionData($region),
        ]);
    }

    public function destroy(Region $region): JsonResponse
    {
        $result = DB::transaction(function () use ($region): string {
            $lockedRegion = Region::query()
                ->whereKey($region->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRegion->children()->exists()
                || CulturalEntry::withTrashed()
                    ->where('origin_region_id', $lockedRegion->getKey())
                    ->exists()) {
                return 'related';
            }

            $lockedRegion->delete();

            return 'deleted';
        });

        if ($result === 'related') {
            return response()->json([
                'message' => 'Wilayah tidak dapat dihapus karena masih memiliki wilayah turunan atau entri budaya.',
                'errors' => ['region' => ['Pindahkan atau lepaskan relasi terlebih dahulu.']],
            ], JsonResponse::HTTP_CONFLICT);
        }

        return response()->json(['message' => 'Wilayah berhasil dihapus.']);
    }

    private function rules(?Region $region = null): array
    {
        $slugRule = Rule::unique('regions', 'slug');
        $parentRules = ['sometimes', 'nullable', 'integer', 'exists:regions,id'];

        if ($region !== null) {
            $slugRule->ignore($region->id);
            $parentRules[] = Rule::notIn([$region->id]);
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'alpha_dash', 'max:255', $slugRule],
            'region_type' => ['sometimes', 'required', 'string', 'max:30'],
            'parent_id' => $parentRules,
            'code' => ['sometimes', 'nullable', 'string', 'max:30'],
            'description' => ['sometimes', 'nullable', 'string', 'max:16000'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-999.9999999,999.9999999'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-999.9999999,999.9999999'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function attributes(array $data, bool $forCreation = false): array
    {
        if ($forCreation && ! array_key_exists('region_type', $data)) {
            $data['region_type'] = 'province';
        }

        if ($forCreation && ! array_key_exists('is_active', $data)) {
            $data['is_active'] = true;
        }

        return $data;
    }

    private function regionData(Region $region): array
    {
        return [
            'id' => $region->id,
            'parent_id' => $region->parent_id,
            'name' => $region->name,
            'slug' => $region->slug,
            'region_type' => $region->region_type,
            'code' => $region->code,
            'description' => $region->description,
            'latitude' => $region->latitude,
            'longitude' => $region->longitude,
            'is_active' => (bool) $region->is_active,
            'children_count' => $region->children_count ?? 0,
            'cultural_entries_count' => $region->cultural_entries_count ?? 0,
            'created_at' => $region->created_at?->toISOString(),
            'updated_at' => $region->updated_at?->toISOString(),
        ];
    }

    private function isDescendant(int $candidateParentId, Region $region): bool
    {
        $pendingIds = [$region->id];
        $visited = [];

        while ($pendingIds !== []) {
            $parentId = array_pop($pendingIds);
            if (isset($visited[$parentId])) {
                continue;
            }

            $visited[$parentId] = true;
            $childIds = Region::query()
                ->where('parent_id', $parentId)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            if (in_array($candidateParentId, $childIds, true)) {
                return true;
            }

            $pendingIds = array_merge($pendingIds, $childIds);
        }

        return false;
    }

    private function duplicateSlugResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Slug wilayah sudah digunakan.',
            'errors' => ['slug' => ['Slug harus unik.']],
        ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    }
}
