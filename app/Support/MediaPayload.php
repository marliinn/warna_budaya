<?php

namespace App\Support;

use App\Models\Media;
use Illuminate\Validation\Rule;

class MediaPayload
{
    public static function rules(?Media $media = null): array
    {
        return [
            'cultural_entry_id' => [
                'required',
                'integer',
                Rule::exists('cultural_entries', 'id')->whereNull('deleted_at'),
            ],
            'media_type' => ['nullable', 'string', 'max:100'],
            'file_path' => ['nullable', 'string', 'max:255', 'required_without:external_url'],
            'external_url' => ['nullable', 'url:http,https', 'max:10000', 'required_without:file_path'],
            'caption' => ['nullable', 'string', 'max:10000'],
            'alt_text' => ['nullable', 'string', 'max:10000'],
            'creator' => ['nullable', 'string', 'max:255'],
            'copyright_holder' => ['nullable', 'string', 'max:255'],
            'license' => ['nullable', 'string', 'max:255'],
            'permission_notes' => [$media ? 'prohibited' : 'nullable', 'string', 'max:10000'],
            'is_cover' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'permission_status' => ['prohibited'],
        ];
    }

    public static function attributes(array $data): array
    {
        return collect($data)->only([
            'cultural_entry_id',
            'media_type',
            'file_path',
            'external_url',
            'caption',
            'alt_text',
            'creator',
            'copyright_holder',
            'license',
            'permission_notes',
            'is_cover',
            'sort_order',
        ])->all();
    }

    public static function data(Media $item): array
    {
        return [
            'id' => $item->id,
            'cultural_entry_id' => $item->cultural_entry_id,
            'cultural_entry' => $item->culturalEntry ? [
                'id' => $item->culturalEntry->id,
                'title' => $item->culturalEntry->title,
                'slug' => $item->culturalEntry->slug,
                'is_deleted' => $item->culturalEntry->trashed(),
            ] : null,
            'media_type' => $item->media_type,
            'file_path' => $item->file_path,
            'external_url' => $item->external_url,
            'caption' => $item->caption,
            'alt_text' => $item->alt_text,
            'creator' => $item->creator,
            'copyright_holder' => $item->copyright_holder,
            'license' => $item->license,
            'permission_status' => $item->permission_status,
            'permission_notes' => $item->permission_notes,
            'is_cover' => $item->is_cover,
            'sort_order' => $item->sort_order,
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
        ];
    }
}
