<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryManagementController extends Controller
{
    public function page(): View
    {
        return view('admin.categories.index');
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $categories = Category::query()
            ->withCount([
                'children',
                'culturalEntries as cultural_entries_count' => function ($query): void {
                    $query->withTrashed();
                },
            ])
            ->when(trim($filters['search'] ?? '') !== '', function ($query) use ($filters): void {
                $search = trim($filters['search']);
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when(isset($filters['status']), function ($query) use ($filters): void {
                $query->where('is_active', $filters['status'] === 'active');
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return response()->json([
            'data' => $categories->getCollection()
                ->map(fn (Category $category): array => $this->categoryData($category))
                ->all(),
            'meta' => [
                'current_page' => $categories->currentPage(),
                'per_page' => $categories->perPage(),
                'last_page' => $categories->lastPage(),
                'total' => $categories->total(),
                'from' => $categories->firstItem(),
                'to' => $categories->lastItem(),
            ],
            'links' => [
                'first' => $categories->url(1),
                'last' => $categories->url($categories->lastPage()),
                'prev' => $categories->previousPageUrl(),
                'next' => $categories->nextPageUrl(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        try {
            $category = Category::query()->create($this->attributes($data, true));
        } catch (UniqueConstraintViolationException) {
            return $this->duplicateSlugResponse();
        }

        return response()->json([
            'message' => 'Kategori berhasil ditambahkan.',
            'data' => $this->categoryData($category->loadCount([
                'children',
                'culturalEntries as cultural_entries_count' => fn ($query) => $query->withTrashed(),
            ])),
        ], JsonResponse::HTTP_CREATED);
    }

    public function show(Category $category): JsonResponse
    {
        $category->loadCount([
            'children',
            'culturalEntries as cultural_entries_count' => fn ($query) => $query->withTrashed(),
        ]);

        return response()->json(['data' => $this->categoryData($category)]);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $data = $request->validate($this->rules($category));

        if (array_key_exists('parent_id', $data)
            && $data['parent_id'] !== null
            && $this->isDescendant((int) $data['parent_id'], $category)) {
            return response()->json([
                'message' => 'Kategori tidak dapat dipindahkan ke dalam turunannya sendiri.',
                'errors' => ['parent_id' => ['Pilih kategori induk yang tidak membentuk siklus.']],
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $category->fill($this->attributes($data));
            $category->save();
        } catch (UniqueConstraintViolationException) {
            return $this->duplicateSlugResponse();
        }

        $category->loadCount([
            'children',
            'culturalEntries as cultural_entries_count' => fn ($query) => $query->withTrashed(),
        ]);

        return response()->json([
            'message' => 'Kategori berhasil diperbarui.',
            'data' => $this->categoryData($category),
        ]);
    }

    public function setStatus(Request $request, Category $category): JsonResponse
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $category->is_active = (bool) $data['is_active'];
        $category->save();
        $category->loadCount([
            'children',
            'culturalEntries as cultural_entries_count' => fn ($query) => $query->withTrashed(),
        ]);

        return response()->json([
            'message' => $category->is_active
                ? 'Kategori berhasil diaktifkan.'
                : 'Kategori berhasil dinonaktifkan.',
            'data' => $this->categoryData($category),
        ]);
    }

    public function destroy(Category $category): JsonResponse
    {
        $result = DB::transaction(function () use ($category): string {
            $lockedCategory = Category::query()
                ->whereKey($category->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedCategory->children()->exists()
                || DB::table('category_cultural_entry')
                    ->where('category_id', $lockedCategory->getKey())
                    ->exists()) {
                return 'related';
            }

            $lockedCategory->delete();

            return 'deleted';
        });

        if ($result === 'related') {
            return response()->json([
                'message' => 'Kategori tidak dapat dihapus karena masih memiliki kategori turunan atau relasi entri budaya.',
                'errors' => ['category' => ['Pindahkan atau lepaskan relasi terlebih dahulu.']],
            ], JsonResponse::HTTP_CONFLICT);
        }

        return response()->json(['message' => 'Kategori berhasil dihapus.']);
    }

    private function rules(?Category $category = null): array
    {
        $slugRule = Rule::unique('categories', 'slug');
        $parentRules = ['sometimes', 'nullable', 'integer', 'exists:categories,id'];

        if ($category !== null) {
            $slugRule->ignore($category->id);
            $parentRules[] = Rule::notIn([$category->id]);
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'alpha_dash', 'max:255', $slugRule],
            'parent_id' => $parentRules,
            'description' => ['sometimes', 'nullable', 'string', 'max:16000'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function attributes(array $data, bool $forCreation = false): array
    {
        if ($forCreation && ! array_key_exists('sort_order', $data)) {
            $data['sort_order'] = 0;
        }

        if ($forCreation && ! array_key_exists('is_active', $data)) {
            $data['is_active'] = true;
        }

        return $data;
    }

    private function categoryData(Category $category): array
    {
        return [
            'id' => $category->id,
            'parent_id' => $category->parent_id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'sort_order' => $category->sort_order,
            'is_active' => (bool) $category->is_active,
            'children_count' => $category->children_count ?? 0,
            'cultural_entries_count' => $category->cultural_entries_count ?? 0,
            'created_at' => $category->created_at?->toISOString(),
            'updated_at' => $category->updated_at?->toISOString(),
        ];
    }

    private function isDescendant(int $candidateParentId, Category $category): bool
    {
        $pendingIds = [$category->id];
        $visited = [];

        while ($pendingIds !== []) {
            $parentId = array_pop($pendingIds);
            if (isset($visited[$parentId])) {
                continue;
            }

            $visited[$parentId] = true;
            $childIds = Category::query()
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
            'message' => 'Slug kategori sudah digunakan.',
            'errors' => ['slug' => ['Slug harus unik.']],
        ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    }
}
