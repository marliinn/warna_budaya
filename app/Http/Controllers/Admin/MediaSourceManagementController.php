<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CulturalEntry;
use App\Models\Media;
use App\Models\Source;
use App\Support\MediaPayload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MediaSourceManagementController extends Controller
{
    public function mediaIndex(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'cultural_entry_id' => ['nullable', 'integer', 'min:1'],
            'media_type' => ['nullable', 'string', 'max:100'],
            'permission_status' => ['nullable', 'string', 'max:50'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $media = Media::query()
            ->with(['culturalEntry' => fn (BelongsTo $query) => $query->withTrashed()])
            ->when(isset($filters['cultural_entry_id']), fn (Builder $query) => $query->where(
                'cultural_entry_id',
                $filters['cultural_entry_id']
            ))
            ->when(isset($filters['media_type']), fn (Builder $query) => $query->where(
                'media_type',
                $filters['media_type']
            ))
            ->when(isset($filters['permission_status']), fn (Builder $query) => $query->where(
                'permission_status',
                $filters['permission_status']
            ))
            ->when(trim($filters['search'] ?? '') !== '', function (Builder $query) use ($filters): void {
                $search = trim($filters['search']);
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('caption', 'like', "%{$search}%")
                        ->orWhere('alt_text', 'like', "%{$search}%")
                        ->orWhere('creator', 'like', "%{$search}%")
                        ->orWhere('copyright_holder', 'like', "%{$search}%")
                        ->orWhereHas('culturalEntry', fn (Builder $entryQuery) => $entryQuery
                            ->withTrashed()
                            ->where('title', 'like', "%{$search}%"));
                });
            })
            ->orderBy('cultural_entry_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return response()->json([
            'data' => $media->getCollection()
                ->map(fn (Media $item): array => $this->mediaData($item))
                ->all(),
            'meta' => [
                'current_page' => $media->currentPage(),
                'per_page' => $media->perPage(),
                'last_page' => $media->lastPage(),
                'total' => $media->total(),
                'from' => $media->firstItem(),
                'to' => $media->lastItem(),
            ],
            'links' => [
                'first' => $media->url(1),
                'last' => $media->url($media->lastPage()),
                'prev' => $media->previousPageUrl(),
                'next' => $media->nextPageUrl(),
            ],
        ]);
    }

    public function mediaStore(Request $request): JsonResponse
    {
        $data = $request->validate(MediaPayload::rules());
        $this->ensureMediaLocation($data);

        $item = DB::transaction(function () use ($data): Media {
            $item = Media::query()->create(MediaPayload::attributes($data) + [
                'permission_status' => 'pending',
            ]);

            $this->makeCoverUnique($item);

            return $item->load(['culturalEntry' => fn (BelongsTo $query) => $query->withTrashed()]);
        });

        return response()->json([
            'message' => 'Media berhasil ditambahkan dengan status izin pending.',
            'data' => $this->mediaData($item),
        ], JsonResponse::HTTP_CREATED);
    }

    public function mediaShow(int $media): JsonResponse
    {
        $item = Media::query()
            ->with(['culturalEntry' => fn (BelongsTo $query) => $query->withTrashed()])
            ->findOrFail($media);

        return response()->json(['data' => $this->mediaData($item)]);
    }

    public function mediaUpdate(Request $request, int $media): JsonResponse
    {
        $item = Media::query()->findOrFail($media);
        $data = $request->validate(MediaPayload::rules($item));
        $this->ensureMediaLocation($data);

        $item = DB::transaction(function () use ($data, $item): Media {
            $item = Media::query()->lockForUpdate()->findOrFail($item->id);
            $item->fill(MediaPayload::attributes($data))->save();

            $this->makeCoverUnique($item);

            return $item->load(['culturalEntry' => fn (BelongsTo $query) => $query->withTrashed()]);
        });

        return response()->json([
            'message' => 'Media berhasil diperbarui.',
            'data' => $this->mediaData($item),
        ]);
    }

    public function mediaDestroy(int $media): JsonResponse
    {
        DB::transaction(function () use ($media): void {
            Media::query()->lockForUpdate()->findOrFail($media)->delete();
        });

        return response()->json([
            'message' => 'Data media berhasil dihapus. Berkas fisik tidak dihapus.',
        ]);
    }

    public function sourceIndex(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'source_type' => ['nullable', 'string', 'max:100'],
            'cultural_entry_id' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $sources = Source::query()
            ->when(isset($filters['source_type']), fn (Builder $query) => $query->where(
                'source_type',
                $filters['source_type']
            ))
            ->when(isset($filters['cultural_entry_id']), function (Builder $query) use ($filters): void {
                $query->whereIn('id', DB::table('cultural_entry_source')
                    ->select('source_id')
                    ->where('cultural_entry_id', $filters['cultural_entry_id']));
            })
            ->when(trim($filters['search'] ?? '') !== '', function (Builder $query) use ($filters): void {
                $search = trim($filters['search']);
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('author', 'like', "%{$search}%")
                        ->orWhere('publisher', 'like', "%{$search}%")
                        ->orWhere('identifier', 'like', "%{$search}%");
                });
            })
            ->orderBy('title')
            ->orderBy('id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return response()->json([
            'data' => $sources->getCollection()
                ->map(fn (Source $source): array => $this->sourceData($source))
                ->all(),
            'meta' => [
                'current_page' => $sources->currentPage(),
                'per_page' => $sources->perPage(),
                'last_page' => $sources->lastPage(),
                'total' => $sources->total(),
                'from' => $sources->firstItem(),
                'to' => $sources->lastItem(),
            ],
            'links' => [
                'first' => $sources->url(1),
                'last' => $sources->url($sources->lastPage()),
                'prev' => $sources->previousPageUrl(),
                'next' => $sources->nextPageUrl(),
            ],
        ]);
    }

    public function sourceStore(Request $request): JsonResponse
    {
        $data = $request->validate($this->sourceRules());
        $this->ensureLiveEntryIds($data['cultural_entry_ids'] ?? []);

        $source = DB::transaction(function () use ($data): Source {
            $source = Source::query()->create($this->sourceAttributes($data));
            $entryIds = $data['cultural_entry_ids'] ?? [];
            $this->syncSourceEntries($source->id, $entryIds);
            $this->requestReviewForEntries(
                $entryIds,
                sprintf('Sumber referensi baru ditautkan: %s.', $source->title)
            );

            return $source;
        });

        return response()->json([
            'message' => 'Sumber berhasil ditambahkan.',
            'data' => $this->sourceData($source),
        ], JsonResponse::HTTP_CREATED);
    }

    public function sourceShow(int $source): JsonResponse
    {
        return response()->json([
            'data' => $this->sourceData(Source::query()->findOrFail($source)),
        ]);
    }

    public function sourceUpdate(Request $request, int $source): JsonResponse
    {
        $sourceModel = Source::query()->findOrFail($source);
        $data = $request->validate($this->sourceRules());
        $this->ensureLiveEntryIds($data['cultural_entry_ids'] ?? []);

        $sourceModel = DB::transaction(function () use ($data, $sourceModel): Source {
            $sourceModel = Source::query()->lockForUpdate()->findOrFail($sourceModel->id);
            $linkedEntryIds = DB::table('cultural_entry_source')
                ->where('source_id', $sourceModel->id)
                ->pluck('cultural_entry_id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            sort($linkedEntryIds);
            $sourceModel->fill($this->sourceAttributes($data));
            $metadataChanged = $sourceModel->isDirty();
            $sourceModel->save();

            $affectedEntryIds = $linkedEntryIds;
            $linksChanged = false;
            if (array_key_exists('cultural_entry_ids', $data)) {
                $nextEntryIds = array_values(array_unique(array_map('intval', $data['cultural_entry_ids'])));
                sort($nextEntryIds);
                $linksChanged = $linkedEntryIds !== $nextEntryIds;

                if ($linksChanged) {
                    $this->syncSourceEntries($sourceModel->id, $data['cultural_entry_ids']);
                    $affectedEntryIds = array_merge($affectedEntryIds, $nextEntryIds);
                }
            }

            if ($metadataChanged || $linksChanged) {
                $this->requestReviewForEntries(
                    $affectedEntryIds,
                    sprintf('Metadata atau tautan sumber referensi berubah: %s.', $sourceModel->title)
                );
            }

            return $sourceModel;
        });

        return response()->json([
            'message' => 'Sumber berhasil diperbarui.',
            'data' => $this->sourceData($sourceModel),
        ]);
    }

    public function sourceDestroy(int $source): JsonResponse
    {
        $hasLinks = DB::transaction(function () use ($source): bool {
            $sourceModel = Source::query()->lockForUpdate()->findOrFail($source);
            $linked = DB::table('cultural_entry_source')
                ->where('source_id', $sourceModel->id)
                ->exists();

            if (! $linked) {
                $sourceModel->delete();
            }

            return $linked;
        });

        if ($hasLinks) {
            return response()->json([
                'message' => 'Sumber masih terhubung dengan entri budaya. Lepaskan relasinya sebelum menghapus sumber.',
            ], JsonResponse::HTTP_CONFLICT);
        }

        return response()->json(['message' => 'Sumber berhasil dihapus.']);
    }

    private function sourceRules(): array
    {
        return [
            'source_type' => ['nullable', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'publication_year' => ['nullable', 'integer', 'min:1000', 'max:9999'],
            'url' => ['nullable', 'url:http,https', 'max:10000'],
            'identifier' => ['nullable', 'string', 'max:255'],
            'accessed_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'cultural_entry_ids' => ['sometimes', 'array', 'max:100'],
            'cultural_entry_ids.*' => [
                'required',
                'integer',
                'distinct',
                'min:1',
                Rule::exists('cultural_entries', 'id')->whereNull('deleted_at'),
            ],
        ];
    }

    private function ensureMediaLocation(array $data): void
    {
        if (blank($data['file_path'] ?? null) && blank($data['external_url'] ?? null)) {
            abort(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'Isi file_path atau external_url untuk lokasi media.');
        }
    }

    private function ensureLiveEntryIds(array $entryIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $entryIds)));

        if ($ids !== [] && CulturalEntry::query()->whereIn('id', $ids)->count() !== count($ids)) {
            abort(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'Relasi hanya dapat menggunakan entri budaya yang aktif.');
        }
    }

    private function makeCoverUnique(Media $item): void
    {
        if (! $item->is_cover) {
            return;
        }

        CulturalEntry::query()->lockForUpdate()->findOrFail($item->cultural_entry_id);
        Media::query()
            ->where('cultural_entry_id', $item->cultural_entry_id)
            ->where('id', '<>', $item->id)
            ->update(['is_cover' => false]);
    }

    private function sourceAttributes(array $data): array
    {
        return collect($data)->only([
            'source_type',
            'title',
            'author',
            'publisher',
            'publication_year',
            'url',
            'identifier',
            'accessed_at',
            'notes',
        ])->all();
    }

    private function syncSourceEntries(int $sourceId, array $entryIds): void
    {
        DB::table('cultural_entry_source')->where('source_id', $sourceId)->delete();

        if ($entryIds !== []) {
            DB::table('cultural_entry_source')->insert(array_map(
                fn (int $entryId): array => [
                    'cultural_entry_id' => $entryId,
                    'source_id' => $sourceId,
                ],
                array_map('intval', $entryIds)
            ));
        }
    }

    private function requestReviewForEntries(array $entryIds, string $reason): void
    {
        $entryIds = array_values(array_unique(array_map('intval', $entryIds)));

        if ($entryIds === []) {
            return;
        }

        $entries = CulturalEntry::withTrashed()
            ->whereIn('id', $entryIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($entries as $entry) {
            if ($entry->trashed()) {
                continue;
            }

            $previousVerificationStatus = $entry->verification_status;
            $previousPublicationStatus = $entry->publication_status;
            $needsReview = $previousVerificationStatus === 'verified'
                || $previousPublicationStatus === 'published';

            if ($needsReview) {
                $entry->forceFill([
                    'verification_status' => 'pending',
                    'verified_at' => null,
                    'publication_status' => 'draft',
                    'published_at' => null,
                    'updated_by' => auth()->id(),
                ])->save();
            }

            $entry->verificationLogs()->create([
                'reviewer_id' => auth()->id(),
                'decision' => 'source_changed',
                'notes' => sprintf(
                    '%s Status verifikasi: %s → %s. Status publikasi: %s → %s.',
                    $reason,
                    $previousVerificationStatus,
                    $needsReview ? 'pending' : $previousVerificationStatus,
                    $previousPublicationStatus,
                    $needsReview ? 'draft' : $previousPublicationStatus
                ),
                'reviewed_at' => now(),
            ]);
        }
    }

    private function mediaData(Media $item): array
    {
        return MediaPayload::data($item);
    }

    private function sourceData(Source $source): array
    {
        $entryIds = DB::table('cultural_entry_source')
            ->where('source_id', $source->id)
            ->pluck('cultural_entry_id');
        $entries = CulturalEntry::withTrashed()
            ->whereIn('id', $entryIds)
            ->get(['id', 'title', 'slug', 'deleted_at'])
            ->map(fn (CulturalEntry $entry): array => [
                'id' => $entry->id,
                'title' => $entry->title,
                'slug' => $entry->slug,
                'is_deleted' => $entry->trashed(),
            ])
            ->all();

        return [
            'id' => $source->id,
            'source_type' => $source->source_type,
            'title' => $source->title,
            'author' => $source->author,
            'publisher' => $source->publisher,
            'publication_year' => $source->publication_year,
            'url' => $source->url,
            'identifier' => $source->identifier,
            'accessed_at' => $source->accessed_at,
            'notes' => $source->notes,
            'cultural_entries' => $entries,
            'created_at' => $source->created_at,
            'updated_at' => $source->updated_at,
        ];
    }
}
