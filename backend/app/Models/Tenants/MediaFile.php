<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class MediaFile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'collection_name',
        'file_name',
        'mime_type',
        'disk',
        'path',
        'cdn_url',
        'size',
        'conversions',
        'responsive_images',
        'custom_properties',
        'generated_conversions',
        'metadata',
        'order'
    ];

    protected $casts = [
        'conversions' => 'array',
        'responsive_images' => 'array',
        'custom_properties' => 'array',
        'generated_conversions' => 'array',
        'metadata' => 'array',
        'order' => 'integer'
    ];

    /**
     * The model that owns the media.
     */
    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The user who uploaded the media.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the full URL for the media file.
     */
    public function getUrl(string $conversion = ''): string
    {
        if ($this->cdn_url) {
            return $this->getCdnUrl($conversion);
        }

        return $this->getLocalUrl($conversion);
    }

    /**
     * Get the CDN URL for the media file.
     */
    protected function getCdnUrl(string $conversion = ''): string
    {
        $path = $conversion ? 
            $this->getConversionPath($conversion) : 
            $this->path;

        return $this->cdn_url . '/' . ltrim($path, '/');
    }

    /**
     * Get the local URL for the media file.
     */
    protected function getLocalUrl(string $conversion = ''): string
    {
        $path = $conversion ? 
            $this->getConversionPath($conversion) : 
            $this->path;

        return Storage::disk($this->disk)->url($path);
    }

    /**
     * Get the path for a specific conversion.
     */
    protected function getConversionPath(string $conversion): string
    {
        return $this->conversions[$conversion] ?? $this->path;
    }

    /**
     * Get responsive image URLs.
     */
    public function getResponsiveUrls(): array
    {
        if (empty($this->responsive_images)) {
            return [];
        }

        return collect($this->responsive_images)
            ->map(fn ($path) => $this->cdn_url ? 
                $this->cdn_url . '/' . ltrim($path, '/') :
                Storage::disk($this->disk)->url($path)
            )
            ->toArray();
    }

    /**
     * Check if the file has a specific conversion.
     */
    public function hasConversion(string $conversion): bool
    {
        return isset($this->conversions[$conversion]);
    }

    /**
     * Get the file extension.
     */
    public function getExtension(): string
    {
        return pathinfo($this->file_name, PATHINFO_EXTENSION);
    }

    /**
     * Check if the file is an image.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Check if the file is a video.
     */
    public function isVideo(): bool
    {
        return str_starts_with($this->mime_type, 'video/');
    }

    /**
     * Check if the file is an audio.
     */
    public function isAudio(): bool
    {
        return str_starts_with($this->mime_type, 'audio/');
    }

    /**
     * Get human readable file size.
     */
    public function getHumanReadableSize(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Scope a query to a specific collection.
     */
    public function scopeInCollection($query, string $collection)
    {
        return $query->where('collection_name', $collection);
    }

    /**
     * Scope a query to order by position.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    /**
     * Update the order of media items in a collection.
     */
    public static function updateOrder(array $orderedIds): void
    {
        foreach ($orderedIds as $order => $id) {
            static::where('id', $id)->update(['order' => $order + 1]);
        }
    }

    /**
     * Delete the file from storage when the model is deleted.
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($media) {
            // Delete original file
            Storage::disk($media->disk)->delete($media->path);

            // Delete conversions
            foreach ($media->conversions ?? [] as $path) {
                Storage::disk($media->disk)->delete($path);
            }

            // Delete responsive images
            foreach ($media->responsive_images ?? [] as $path) {
                Storage::disk($media->disk)->delete($path);
            }
        });
    }
}