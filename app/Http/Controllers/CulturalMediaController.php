<?php

namespace App\Http\Controllers;

use App\Models\CulturalEntry;
use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CulturalMediaController extends Controller
{
    public function show(int $media): BinaryFileResponse
    {
        $mediaItem = Media::query()
            ->whereKey($media)
            ->where('media_type', 'image')
            ->where('permission_status', Media::PERMISSION_GRANTED)
            ->whereHas('culturalEntry', fn ($query) => $query->approvedForPublication())
            ->firstOrFail();

        return $this->fileResponse($mediaItem);
    }

    public function preview(int $entry, int $media): BinaryFileResponse
    {
        CulturalEntry::query()->findOrFail($entry);

        $mediaItem = Media::query()
            ->where('cultural_entry_id', $entry)
            ->where('media_type', 'image')
            ->findOrFail($media);

        return $this->fileResponse($mediaItem);
    }

    private function fileResponse(Media $media): BinaryFileResponse
    {
        abort_unless(
            is_string($media->file_path)
                && preg_match('/\Acultural\/[A-Za-z0-9][A-Za-z0-9._-]*\.jpg\z/i', $media->file_path),
            404
        );

        $path = Storage::disk('local')->path($media->file_path);
        abort_unless(is_file($path), 404);

        $response = response()->file($path, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setPrivate();

        return $response;
    }
}
