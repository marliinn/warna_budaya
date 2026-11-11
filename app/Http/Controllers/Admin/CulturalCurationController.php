<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CulturalEntry;
use App\Models\Media;
use App\Models\Region;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CulturalCurationController extends Controller
{
    private const VERIFICATION_PENDING = 'pending';

    private const VERIFICATION_VERIFIED = 'verified';

    private const PUBLICATION_DRAFT = 'draft';

    private const PUBLICATION_PUBLISHED = 'published';

    private const PERMISSION_PENDING = 'pending';

    private const PERMISSION_GRANTED = 'granted';

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'region_id' => ['nullable', 'integer', 'exists:regions,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'verification_status' => ['nullable', 'in:pending,verified'],
            'publication_status' => ['nullable', 'in:draft,published'],
        ]);

        $allEntries = CulturalEntry::withTrashed();
        $stats = [
            'total' => (clone $allEntries)->count(),
            'draft' => (clone $allEntries)->where('publication_status', self::PUBLICATION_DRAFT)->count(),
            'pending' => (clone $allEntries)->where('verification_status', self::VERIFICATION_PENDING)->count(),
            'verified' => (clone $allEntries)->where('verification_status', self::VERIFICATION_VERIFIED)->count(),
            'published' => (clone $allEntries)->where('publication_status', self::PUBLICATION_PUBLISHED)->count(),
            'deleted' => (clone $allEntries)->whereNotNull('deleted_at')->count(),
            'media_pending' => Media::query()->where('permission_status', self::PERMISSION_PENDING)->count(),
            'media_granted' => Media::query()->where('permission_status', self::PERMISSION_GRANTED)->count(),
        ];

        $entries = CulturalEntry::withTrashed()
            ->with(['region', 'categories'])
            ->withCount('media')
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('local_name', 'like', "%{$search}%");
                });
            })
            ->when($filters['region_id'] ?? null, fn (Builder $query, int $regionId) => $query->where('origin_region_id', $regionId))
            ->when($filters['category_id'] ?? null, fn (Builder $query, int $categoryId) => $query->whereHas(
                'categories',
                fn (Builder $categoryQuery) => $categoryQuery->whereKey($categoryId)
            ))
            ->when($filters['verification_status'] ?? null, fn (Builder $query, string $status) => $query->where('verification_status', $status))
            ->when($filters['publication_status'] ?? null, fn (Builder $query, string $status) => $query->where('publication_status', $status))
            ->orderByDesc('updated_at')
            ->orderBy('title')
            ->paginate(15)
            ->withQueryString();

        $pendingMedia = Media::query()
            ->where('permission_status', self::PERMISSION_PENDING)
            ->with([
                'culturalEntry' => fn ($query) => $query->withTrashed()->with('sources'),
            ])
            ->orderBy('cultural_entry_id')
            ->orderBy('id')
            ->get();

        $pendingMedia->each(function (Media $media): void {
            $entryIsAvailable = $media->culturalEntry && ! $media->culturalEntry->trashed();
            $hasPreviewFile = is_string($media->file_path)
                && preg_match('/\Acultural\/[A-Za-z0-9][A-Za-z0-9._-]*\.jpg\z/i', $media->file_path)
                && Storage::disk('local')->exists($media->file_path);
            $media->setAttribute(
                'preview_url',
                $entryIsAvailable
                    && $media->media_type === 'image'
                    && $hasPreviewFile
                    ? route('admin.curation.media.preview', [
                        'entry' => $media->cultural_entry_id,
                        'media' => $media->id,
                    ])
                    : null
            );
            $media->setAttribute('source_url', $this->safeExternalUrl($media->external_url));
            $media->setAttribute('license_url', $media->licenseUrl());
            $media->setAttribute('bulk_grant_blocker', $this->bulkGrantBlocker($media));
        });

        return view('admin.curation.index', [
            'entries' => $entries,
            'stats' => $stats,
            'pendingMedia' => $pendingMedia,
            'bulkGrantableMediaCount' => $pendingMedia->whereNull('bulk_grant_blocker')->count(),
            'regions' => Region::query()->orderBy('name')->get(),
            'categories' => Category::query()->orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    public function show(int $entry): View
    {
        $culturalEntry = CulturalEntry::withTrashed()
            ->with([
                'region',
                'categories',
                'media' => fn (HasMany $query) => $query->orderByDesc('is_cover')->orderBy('sort_order')->orderBy('id'),
                'sources',
                'createdBy',
                'updatedBy',
                'verificationLogs.reviewer',
            ])
            ->findOrFail($entry);

        $culturalEntry->media->each(function (Media $media) use ($culturalEntry): void {
            $previewUrl = $media->file_path && str_starts_with($media->file_path, 'cultural/')
                ? route('admin.curation.media.preview', [
                    'entry' => $culturalEntry->id,
                    'media' => $media->id,
                ])
                : ($media->file_path ? asset($media->file_path) : $this->safeExternalUrl($media->external_url));

            $media->setAttribute(
                'preview_url',
                $previewUrl
            );
        });
        $culturalEntry->sources->each(fn ($source) => $source->setAttribute('safe_url', $this->safeExternalUrl($source->url)));

        return view('admin.curation.show', [
            'entry' => $culturalEntry,
            'publicationBlockers' => $this->publicationBlockers($culturalEntry),
        ]);
    }

    public function verify(Request $request, int $entry): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'notes' => ['required_if:decision,reject', 'nullable', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($data, $entry): void {
            $culturalEntry = CulturalEntry::withTrashed()->lockForUpdate()->findOrFail($entry);
            abort_if($culturalEntry->trashed(), 404);

            $previousStatus = $culturalEntry->verification_status;
            $nextStatus = $data['decision'] === 'approve'
                ? self::VERIFICATION_VERIFIED
                : self::VERIFICATION_PENDING;
            $reviewedAt = now();

            $culturalEntry->forceFill([
                'verification_status' => $nextStatus,
                'verified_at' => $data['decision'] === 'approve' ? $reviewedAt : null,
                'updated_by' => $this->reviewerId(),
            ])->save();

            $this->recordHistory(
                $culturalEntry,
                $data['decision'] === 'approve' ? 'verified' : 'rejected',
                $reviewedAt,
                sprintf(
                    'Verifikasi: %s → %s.%s',
                    $previousStatus,
                    $nextStatus,
                    $this->noteSuffix($data['notes'] ?? null)
                )
            );
        });

        return redirect()
            ->route('admin.curation.entries.show', $entry)
            ->with('status', $data['decision'] === 'approve'
                ? 'Entri ditandai terverifikasi. Status publikasi tidak berubah.'
                : 'Penolakan verifikasi dan alasannya telah dicatat. Keputusan publikasi tetap terpisah.');
    }

    public function reviewMedia(Request $request, int $entry, int $media): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:grant,reject,pending'],
            'notes' => ['required_if:decision,grant,reject', 'nullable', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($data, $entry, $media): void {
            $culturalEntry = CulturalEntry::withTrashed()->lockForUpdate()->findOrFail($entry);
            abort_if($culturalEntry->trashed(), 404);

            $mediaItem = Media::query()
                ->where('cultural_entry_id', $culturalEntry->id)
                ->lockForUpdate()
                ->findOrFail($media);

            $previousStatus = $mediaItem->permission_status;
            $nextStatus = $data['decision'] === 'grant'
                ? self::PERMISSION_GRANTED
                : self::PERMISSION_PENDING;
            $reviewedAt = now();
            $note = trim((string) ($data['notes'] ?? ''));

            $mediaItem->forceFill([
                'permission_status' => $nextStatus,
                'permission_notes' => $this->appendMediaNote($mediaItem->permission_notes, $note),
            ])->save();

            $decision = match ($data['decision']) {
                'grant' => 'media_granted',
                'reject' => 'media_rejected',
                default => 'media_pending',
            };

            $this->recordHistory(
                $culturalEntry,
                $decision,
                $reviewedAt,
                sprintf(
                    'Media #%d, izin: %s → %s.%s',
                    $mediaItem->id,
                    $previousStatus,
                    $nextStatus,
                    $this->noteSuffix($note)
                )
            );
        });

        return redirect()
            ->route('admin.curation.entries.show', $entry)
            ->with('status', match ($data['decision']) {
                'grant' => 'Izin media disetujui dan dicatat.',
                'reject' => 'Penolakan izin media dicatat; status izin tetap pending agar media tidak tampil ke publik.',
                default => 'Media dikembalikan ke status pending.',
            });
    }

    public function grantPendingMedia(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'return_to' => ['nullable', 'in:media-sources'],
        ]);

        $result = DB::transaction(function (): array {
            $pendingMedia = Media::query()
                ->where('permission_status', self::PERMISSION_PENDING)
                ->with([
                    'culturalEntry' => fn ($query) => $query->withTrashed(),
                ])
                ->orderBy('cultural_entry_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $grantedCount = 0;
            $skippedMedia = [];
            $reviewedAt = now();
            $note = 'Persetujuan izin media massal setelah pemeriksaan sumber dan lisensi pada Dashboard Kurasi.';

            foreach ($pendingMedia as $media) {
                $entry = CulturalEntry::withTrashed()
                    ->lockForUpdate()
                    ->find($media->cultural_entry_id);

                if (! $entry || $entry->trashed()) {
                    $skippedMedia[] = [
                        'title' => $media->caption ?: 'Media #'.$media->id,
                        'reason' => 'Entri budaya sudah tidak tersedia atau telah dihapus.',
                    ];
                    continue;
                }

                $blocker = $this->bulkGrantBlocker($media);
                if ($blocker !== null) {
                    $skippedMedia[] = [
                        'title' => $media->caption ?: 'Media #'.$media->id,
                        'reason' => $blocker,
                    ];
                    continue;
                }

                $media->forceFill([
                    'permission_status' => self::PERMISSION_GRANTED,
                    'permission_notes' => $this->appendMediaNote($media->permission_notes, $note),
                ])->save();

                $this->recordHistory(
                    $entry,
                    'media_granted',
                    $reviewedAt,
                    sprintf(
                        'Persetujuan izin media massal: Media #%d (%s), pending → granted. %s',
                        $media->id,
                        $media->caption ?: $media->file_path,
                        $note
                    )
                );

                $grantedCount++;
            }

            return [
                'granted' => $grantedCount,
                'skipped' => $skippedMedia,
            ];
        });

        $redirect = redirect()
            ->route(($data['return_to'] ?? null) === 'media-sources'
                ? 'admin.media-sources.page'
                : 'admin.home')
            ->with('status', sprintf(
                '%d media pending disetujui; %d media dilewati karena belum memenuhi syarat.',
                $result['granted'],
                count($result['skipped'])
            ))
            ->with('media_bulk_skipped', $result['skipped'])
            ->with('media_bulk_granted_count', $result['granted']);

        return $redirect;
    }

    public function publish(Request $request, int $entry): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:publish,draft'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'return_to' => ['nullable', 'in:manage'],
        ]);

        $culturalEntry = CulturalEntry::withTrashed()->findOrFail($entry);
        $blockers = $data['action'] === 'publish' ? $this->publicationBlockers($culturalEntry) : [];

        if ($blockers !== []) {
            if (($data['return_to'] ?? null) === 'manage') {
                return redirect()
                    ->route('admin.curation.entries.show', $entry)
                    ->with('publication_blockers', $blockers)
                    ->withErrors(['publication' => 'Entri belum dapat dipublikasikan. Periksa syarat yang tercantum.']);
            }

            return back()
                ->withInput()
                ->with('publication_blockers', $blockers)
                ->withErrors(['publication' => 'Entri belum dapat dipublikasikan. Periksa syarat yang tercantum.']);
        }

        DB::transaction(function () use ($data, $entry): void {
            $culturalEntry = CulturalEntry::withTrashed()->lockForUpdate()->findOrFail($entry);
            abort_if($culturalEntry->trashed(), 404);

            if ($data['action'] === 'publish') {
                $currentBlockers = $this->publicationBlockers($culturalEntry);
                if ($currentBlockers !== []) {
                    throw ValidationException::withMessages([
                        'publication' => 'Entri belum dapat dipublikasikan: '.implode(' ', $currentBlockers),
                    ]);
                }
            }

            $previousStatus = $culturalEntry->publication_status;
            $nextStatus = $data['action'] === 'publish'
                ? self::PUBLICATION_PUBLISHED
                : self::PUBLICATION_DRAFT;
            $changedAt = now();

            if ($previousStatus !== $nextStatus) {
                $culturalEntry->forceFill([
                    'publication_status' => $nextStatus,
                    'published_at' => $nextStatus === self::PUBLICATION_PUBLISHED ? $changedAt : null,
                    'updated_by' => $this->reviewerId(),
                ])->save();
            }

            $decision = $nextStatus === self::PUBLICATION_PUBLISHED ? 'published' : 'withdrawn';
            $this->recordHistory(
                $culturalEntry,
                $decision,
                $changedAt,
                sprintf(
                    'Persetujuan publikasi: %s → %s.%s',
                    $previousStatus,
                    $nextStatus,
                    $this->noteSuffix($data['notes'] ?? null)
                )
            );
        });

        return redirect()
            ->route(($data['return_to'] ?? null) === 'manage'
                ? 'admin.cultural-entries.manage'
                : 'admin.curation.entries.show', ($data['return_to'] ?? null) === 'manage' ? [] : $entry)
            ->with('status', $data['action'] === 'publish'
                ? 'Entri berhasil dipublikasikan.'
                : 'Entri ditarik dari publikasi dan disimpan sebagai draft.');
    }

    public function publishEligible(Request $request): RedirectResponse
    {
        $request->validate([
            'confirm_bulk_publish' => ['required', 'accepted'],
        ]);

        $result = DB::transaction(function (): array {
            $entries = CulturalEntry::query()
                ->where('publication_status', self::PUBLICATION_DRAFT)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $publishedCount = 0;
            $skippedEntries = [];

            foreach ($entries as $entry) {
                $blockers = $this->publicationBlockers($entry);
                if ($blockers !== []) {
                    $skippedEntries[] = [
                        'title' => $entry->title,
                        'reasons' => $blockers,
                    ];
                    continue;
                }

                $publishedAt = now();
                $entry->forceFill([
                    'publication_status' => self::PUBLICATION_PUBLISHED,
                    'published_at' => $publishedAt,
                    'updated_by' => $this->reviewerId(),
                ])->save();

                $this->recordHistory(
                    $entry,
                    'published',
                    $publishedAt,
                    'Persetujuan publikasi massal: draft → published.'
                );

                $publishedCount++;
            }

            return [
                'published' => $publishedCount,
                'skipped' => $skippedEntries,
            ];
        });

        $skippedCount = count($result['skipped']);
        $status = sprintf(
            '%d entri berhasil dipublikasikan; %d entri dilewati karena syarat publikasi belum terpenuhi.',
            $result['published'],
            $skippedCount
        );

        return redirect()
            ->route('admin.cultural-entries.manage')
            ->with('status', $status)
            ->with('publication_bulk_skipped', $result['skipped']);
    }

    private function publicationBlockers(CulturalEntry $entry): array
    {
        $blockers = [];

        if ($entry->trashed()) {
            $blockers[] = 'Entri telah dihapus secara lunak dan tidak dapat dipublikasikan.';
        }

        if (trim((string) $entry->title) === '') {
            $blockers[] = 'Nama budaya wajib tersedia.';
        }

        if (trim((string) ($entry->description ?: $entry->short_description)) === '') {
            $blockers[] = 'Deskripsi budaya wajib tersedia.';
        }

        if ($entry->origin_region_id === null || ! Region::query()->whereKey($entry->origin_region_id)->exists()) {
            $blockers[] = 'Wilayah asal yang valid wajib dipilih.';
        }

        return $blockers;
    }

    private function recordHistory(
        CulturalEntry $entry,
        string $decision,
        Carbon $reviewedAt,
        string $notes
    ): void {
        $entry->verificationLogs()->create([
            'reviewer_id' => $this->reviewerId(),
            'decision' => $decision,
            'notes' => trim($notes),
            'reviewed_at' => $reviewedAt,
        ]);
    }

    private function reviewerId(): int
    {
        return (int) auth()->id();
    }

    private function noteSuffix(?string $note): string
    {
        $note = trim((string) $note);

        return $note === '' ? '' : "\nCatatan admin: ".$note;
    }

    private function appendMediaNote(?string $existingNotes, string $note): ?string
    {
        if ($note === '') {
            return $existingNotes;
        }

        $timestampedNote = '['.now()->toDateTimeString().'] '.$note;

        return trim(implode("\n\n", array_filter([$existingNotes, $timestampedNote])));
    }

    private function safeExternalUrl(?string $url): ?string
    {
        if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)
            ? $url
            : null;
    }

    private function bulkGrantBlocker(Media $media): ?string
    {
        if (! $media->culturalEntry || $media->culturalEntry->trashed()) {
            return 'Entri budaya tidak tersedia atau telah dihapus.';
        }

        if ($media->media_type !== 'image') {
            return 'Hanya media gambar yang dapat disetujui melalui tindakan massal.';
        }

        if (
            ! is_string($media->file_path)
            || ! preg_match('/\Acultural\/[A-Za-z0-9][A-Za-z0-9._-]*\.jpg\z/i', $media->file_path)
            || ! Storage::disk('local')->exists($media->file_path)
        ) {
            return 'File gambar lokal tidak tersedia atau jalurnya tidak valid.';
        }

        if ($this->safeExternalUrl($media->external_url) === null) {
            return 'URL halaman sumber yang valid belum dicantumkan.';
        }

        if (! is_string($media->creator) || trim($media->creator) === '') {
            return 'Nama pencipta/fotografer belum dicantumkan.';
        }

        if ($media->licenseUrl() === null) {
            return 'Lisensi terbuka yang dikenali belum dicantumkan.';
        }

        if (! is_string($media->caption) || trim($media->caption) === '') {
            return 'Caption/konteks foto belum dicantumkan.';
        }

        if (! is_string($media->alt_text) || trim($media->alt_text) === '') {
            return 'Alt text belum dicantumkan.';
        }

        return null;
    }
}
