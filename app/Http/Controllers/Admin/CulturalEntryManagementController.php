<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CulturalEntry;
use App\Models\Media;
use App\Support\MediaPayload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CulturalEntryManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'region_id' => ['nullable', 'integer', 'exists:regions,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'publication_status' => ['nullable', Rule::in(['draft', 'published'])],
            'verification_status' => ['nullable', Rule::in(['pending', 'verified'])],
            'trashed' => ['nullable', Rule::in(['only', 'with'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = CulturalEntry::query()
            ->with(['region:id,name,slug', 'categories:id,name,slug'])
            ->withCount('media')
            ->when(($filters['trashed'] ?? null) === 'only', fn (Builder $query) => $query->onlyTrashed())
            ->when(($filters['trashed'] ?? null) === 'with', fn (Builder $query) => $query->withTrashed())
            ->when(trim($filters['search'] ?? '') !== '', function (Builder $query) use ($filters): void {
                $search = trim($filters['search']);
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('local_name', 'like', "%{$search}%")
                        ->orWhere('short_description', 'like', "%{$search}%");
                });
            })
            ->when($filters['region_id'] ?? null, fn (Builder $query, int $regionId) => $query->where('origin_region_id', $regionId))
            ->when($filters['category_id'] ?? null, fn (Builder $query, int $categoryId) => $query->whereHas(
                'categories',
                fn (Builder $categoryQuery) => $categoryQuery->whereKey($categoryId)
            ))
            ->when($filters['publication_status'] ?? null, fn (Builder $query, string $status) => $query->where('publication_status', $status))
            ->when($filters['verification_status'] ?? null, fn (Builder $query, string $status) => $query->where('verification_status', $status))
            ->orderByDesc('updated_at')
            ->orderBy('title');

        $entries = $query
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return response()->json([
            'data' => $entries->getCollection()
                ->map(fn (CulturalEntry $entry): array => $this->entryData($entry))
                ->all(),
            'meta' => [
                'current_page' => $entries->currentPage(),
                'per_page' => $entries->perPage(),
                'last_page' => $entries->lastPage(),
                'total' => $entries->total(),
                'from' => $entries->firstItem(),
                'to' => $entries->lastItem(),
            ],
            'links' => [
                'first' => $entries->url(1),
                'last' => $entries->url($entries->lastPage()),
                'prev' => $entries->previousPageUrl(),
                'next' => $entries->nextPageUrl(),
            ],
        ]);
    }

    public function show(int $entry): JsonResponse
    {
        $culturalEntry = CulturalEntry::withTrashed()
            ->with(['region:id,name,slug', 'categories:id,name,slug'])
            ->withCount('media')
            ->findOrFail($entry);

        return response()->json(['data' => $this->entryData($culturalEntry)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        try {
            $entry = DB::transaction(function () use ($data, $request): CulturalEntry {
                $entry = CulturalEntry::query()->create([
                    ...$this->contentAttributes($data),
                    'publication_status' => 'draft',
                    'verification_status' => 'pending',
                    'created_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ]);

                if (array_key_exists('category_ids', $data)) {
                    $entry->categories()->sync($data['category_ids'] ?? []);
                }

                return $entry->load(['region:id,name,slug', 'categories:id,name,slug'])
                    ->loadCount('media');
            });
        } catch (UniqueConstraintViolationException) {
            return $this->duplicateSlugResponse();
        }

        return response()->json([
            'message' => 'Entri budaya berhasil ditambahkan sebagai draft dan menunggu verifikasi.',
            'data' => $this->entryData($entry),
        ], JsonResponse::HTTP_CREATED);
    }

    public function storeWithMedia(Request $request): JsonResponse
    {
        $request->validate([
            'entry' => ['required', 'array'],
            'media' => ['required', 'array'],
            'publication_status' => ['prohibited'],
            'verification_status' => ['prohibited'],
            'permission_status' => ['prohibited'],
        ]);

        $entryRules = collect($this->rules())
            ->mapWithKeys(fn (array $rules, string $field): array => ["entry.{$field}" => $rules])
            ->all();
        $mediaRules = MediaPayload::rules();
        $mediaRules['permission_status'] = ['prohibited'];
        $mediaRules['cultural_entry_id'] = ['prohibited'];
        $mediaRules = collect($mediaRules)
            ->mapWithKeys(function (array $rules, string $field): array {
                $rules = array_map(
                    fn (mixed $rule): mixed => is_string($rule)
                        ? preg_replace('/(?<=required_without:)([a-z_]+)/', 'media.$1', $rule)
                        : $rule,
                    $rules
                );

                return ["media.{$field}" => $rules];
            })
            ->all();
        $validated = $request->validate($entryRules + $mediaRules);
        $entryData = data_get($validated, 'entry', []);
        $mediaData = data_get($validated, 'media', []);

        try {
            $result = DB::transaction(function () use ($entryData, $mediaData, $request): array {
                $entry = CulturalEntry::query()->create([
                    ...$this->contentAttributes($entryData),
                    'publication_status' => 'draft',
                    'verification_status' => 'pending',
                    'created_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ]);

                if (array_key_exists('category_ids', $entryData)) {
                    $entry->categories()->sync($entryData['category_ids'] ?? []);
                }

                $media = Media::query()->create(MediaPayload::attributes($mediaData) + [
                    'cultural_entry_id' => $entry->id,
                    'permission_status' => 'pending',
                ]);

                $entry->load(['region:id,name,slug', 'categories:id,name,slug'])
                    ->loadCount('media');
                $media->load(['culturalEntry' => fn (BelongsTo $query) => $query->withTrashed()]);

                return ['entry' => $entry, 'media' => $media];
            });
        } catch (UniqueConstraintViolationException) {
            return $this->duplicateSlugResponse();
        }

        return response()->json([
            'message' => 'Entri budaya dan media berhasil dibuat. Entri berstatus draft dan pending; izin media berstatus pending.',
            'data' => [
                'cultural_entry' => $this->entryData($result['entry']),
                'media' => MediaPayload::data($result['media']),
            ],
        ], JsonResponse::HTTP_CREATED);
    }

    public function update(Request $request, int $entry): JsonResponse
    {
        $data = $request->validate($this->rules($entry));

        try {
            $result = DB::transaction(function () use ($data, $entry, $request): array {
                $culturalEntry = CulturalEntry::query()
                    ->lockForUpdate()
                    ->findOrFail($entry);

                $culturalEntry->fill($this->contentAttributes($data));

                $categoriesChanged = false;
                if (array_key_exists('category_ids', $data)) {
                    $categoryIds = array_map('intval', $data['category_ids'] ?? []);
                    $currentCategoryIds = $culturalEntry->categories()
                        ->orderBy('categories.id')
                        ->pluck('categories.id')
                        ->map(fn ($id): int => (int) $id)
                        ->all();
                    sort($categoryIds);
                    $categoriesChanged = $currentCategoryIds !== $categoryIds;
                    $culturalEntry->categories()->sync($categoryIds);
                }

                $contentChanged = $culturalEntry->isDirty() || $categoriesChanged;
                $needsReview = $contentChanged
                    && ($culturalEntry->verification_status === 'verified'
                        || $culturalEntry->publication_status === 'published');

                $culturalEntry->updated_by = $request->user()->id;

                if ($needsReview) {
                    $culturalEntry->forceFill([
                        'verification_status' => 'pending',
                        'verified_at' => null,
                        'publication_status' => 'draft',
                        'published_at' => null,
                    ]);
                }

                $culturalEntry->save();

                if ($needsReview) {
                    $culturalEntry->verificationLogs()->create([
                        'reviewer_id' => $request->user()->id,
                        'decision' => 'content_changed',
                        'notes' => 'Konten budaya diubah; status verifikasi dan publikasi dikembalikan ke pending dan draft.',
                        'reviewed_at' => now(),
                    ]);
                }

                return [
                    'entry' => $culturalEntry->load(['region:id,name,slug', 'categories:id,name,slug'])
                        ->loadCount('media'),
                    'needs_review' => $needsReview,
                ];
            });
        } catch (UniqueConstraintViolationException) {
            return $this->duplicateSlugResponse();
        }

        $culturalEntry = $result['entry'];

        return response()->json([
            'message' => $result['needs_review']
                ? 'Entri diperbarui, ditarik dari publikasi, dan dikembalikan ke antrean verifikasi.'
                : 'Entri budaya berhasil diperbarui.',
            'data' => $this->entryData($culturalEntry),
        ]);
    }

    public function destroy(Request $request, int $entry): JsonResponse
    {
        $culturalEntry = DB::transaction(function () use ($entry, $request): CulturalEntry {
            $culturalEntry = CulturalEntry::query()
                ->lockForUpdate()
                ->findOrFail($entry);

            $culturalEntry->updated_by = $request->user()->id;
            $culturalEntry->delete();

            return $culturalEntry;
        });

        return response()->json([
            'message' => 'Entri budaya dihapus secara lunak dan dapat tetap ditinjau oleh pengelola.',
            'data' => [
                'id' => $culturalEntry->id,
                'deleted_at' => $culturalEntry->deleted_at?->toISOString(),
            ],
        ]);
    }

    private function rules(?int $entryId = null): array
    {
        $slugRule = Rule::unique('cultural_entries', 'slug');
        if ($entryId !== null) {
            $slugRule->ignore($entryId);
        }

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'alpha_dash', 'max:255', $slugRule],
            'local_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'short_description' => ['sometimes', 'nullable', 'string', 'max:16000'],
            'description' => ['sometimes', 'nullable', 'string', 'max:100000'],
            'history' => ['sometimes', 'nullable', 'string', 'max:100000'],
            'meaning_and_function' => ['sometimes', 'nullable', 'string', 'max:100000'],
            'techniques' => ['sometimes', 'nullable', 'string', 'max:100000'],
            'materials' => ['sometimes', 'nullable', 'string', 'max:16000'],
            'community_context' => ['sometimes', 'nullable', 'string', 'max:16000'],
            'keywords' => ['sometimes', 'nullable', 'array', 'max:100'],
            'keywords.*' => ['string', 'max:100'],
            'origin_region_id' => ['sometimes', 'nullable', 'integer', 'exists:regions,id'],
            'category_ids' => ['sometimes', 'nullable', 'array', 'max:100'],
            'category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
            'publication_status' => ['prohibited'],
            'verification_status' => ['prohibited'],
            'verified_at' => ['prohibited'],
            'published_at' => ['prohibited'],
            'created_by' => ['prohibited'],
            'updated_by' => ['prohibited'],
            'deleted_at' => ['prohibited'],
        ];
    }

    private function contentAttributes(array $data): array
    {
        $fields = [
            'title',
            'slug',
            'local_name',
            'short_description',
            'description',
            'history',
            'meaning_and_function',
            'techniques',
            'materials',
            'community_context',
            'keywords',
            'origin_region_id',
        ];

        return array_intersect_key($data, array_flip($fields));
    }

    private function entryData(CulturalEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'title' => $entry->title,
            'slug' => $entry->slug,
            'local_name' => $entry->local_name,
            'short_description' => $entry->short_description,
            'description' => $entry->description,
            'history' => $entry->history,
            'meaning_and_function' => $entry->meaning_and_function,
            'techniques' => $entry->techniques,
            'materials' => $entry->materials,
            'community_context' => $entry->community_context,
            'keywords' => $entry->keywords,
            'origin_region_id' => $entry->origin_region_id,
            'region' => $entry->region ? [
                'id' => $entry->region->id,
                'name' => $entry->region->name,
                'slug' => $entry->region->slug,
            ] : null,
            'categories' => $entry->categories
                ->map(fn (Category $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                ])
                ->values()
                ->all(),
            'media_count' => $entry->media_count ?? 0,
            'publication_status' => $entry->publication_status,
            'verification_status' => $entry->verification_status,
            'verified_at' => $entry->verified_at?->toISOString(),
            'published_at' => $entry->published_at?->toISOString(),
            'created_by' => $entry->created_by,
            'updated_by' => $entry->updated_by,
            'deleted_at' => $entry->deleted_at?->toISOString(),
            'created_at' => $entry->created_at?->toISOString(),
            'updated_at' => $entry->updated_at?->toISOString(),
        ];
    }

    private function duplicateSlugResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Slug entri budaya sudah digunakan.',
            'errors' => ['slug' => ['Slug harus unik.']],
        ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    }
}
