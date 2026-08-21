<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PropertyUnitPublicImage extends Model
{
    protected $table = 'property_unit_public_images';

    /** Laravel file validation max is in kilobytes. */
    public const MAX_UPLOAD_KB = 1048576; // 1 GB

    public const MAX_FILES_PER_BATCH = 12;

    /** @var list<string> */
    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /** @var list<string> */
    public const VIDEO_EXTENSIONS = ['mp4', 'webm', 'mov', 'm4v', 'ogg', 'ogv'];

    protected $fillable = [
        'property_unit_id',
        'path',
        'sort_order',
    ];

    public function propertyUnit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class);
    }

    public function extension(): string
    {
        return strtolower(pathinfo(str_replace('\\', '/', (string) $this->path), PATHINFO_EXTENSION) ?: '');
    }

    public function isVideo(): bool
    {
        return in_array($this->extension(), self::VIDEO_EXTENSIONS, true);
    }

    public function isImage(): bool
    {
        return ! $this->isVideo();
    }

    /**
     * Relative URL so previews work on the current host (avoids broken APP_URL absolute links).
     * Served by {@see \App\Http\Controllers\PublicListingMediaController} so files remain
     * visible even when the public/storage symlink is missing on the host.
     */
    public function publicUrl(): string
    {
        $path = ltrim(str_replace('\\', '/', (string) $this->path), '/');
        if ($path === '') {
            return '';
        }

        $encoded = implode('/', array_map('rawurlencode', explode('/', $path)));

        return '/media/unit-listings/'.$encoded;
    }

    /**
     * @return array{url: string, type: 'image'|'video'}
     */
    public function toGalleryItem(): array
    {
        return [
            'url' => $this->publicUrl(),
            'type' => $this->isVideo() ? 'video' : 'image',
        ];
    }

    public function existsOnDisk(): bool
    {
        $path = ltrim(str_replace('\\', '/', (string) $this->path), '/');

        return $path !== '' && Storage::disk('public')->exists($path);
    }

    /**
     * @return list<string>
     */
    public static function allowedUploadExtensions(): array
    {
        return array_values(array_unique(array_merge(self::IMAGE_EXTENSIONS, self::VIDEO_EXTENSIONS)));
    }

    public static function uploadAcceptAttribute(): string
    {
        return implode(',', [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
            'video/mp4',
            'video/webm',
            'video/quicktime',
            'video/ogg',
            '.mp4',
            '.webm',
            '.mov',
            '.m4v',
            '.ogg',
            '.ogv',
        ]);
    }
}
