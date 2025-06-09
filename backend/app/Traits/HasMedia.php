<?php

namespace App\Models\Traits;

use App\Models\MediaFile;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Facades\Image;

trait HasMedia
{
    /**
     * Get media files for this model.
     */
    public function media(): MorphMany
    {
        return $this->morphMany(MediaFile::class, 'mediable');
    }

    /**
     * Add a media file to the model.
     */
    public function addMedia(
        UploadedFile $file,
        string $collection = 'default',
        array $options = []
    ): MediaFile {
        $fileName = $this->generateFileName($file);
        $path = $this->generatePath($collection, $fileName);
        
        // Store the original file
        $disk = $options['disk'] ?? config('filesystems.default');
        $file->storeAs(dirname($path), $fileName, $disk);

        // Create media record
        $media = $this->media()->create([
            'collection_name' => $collection,
            'file_name' => $fileName,
            'mime_type' => $file->getMimeType(),
            'disk' => $disk,
            'path' => $path,
            'size' => $file->getSize(),
            'order' => $options['order'] ?? $this->getNextMediaOrder($collection),
            'custom_properties' => $options['custom_properties'] ?? [],
            'metadata' => $this->extractMetadata($file)
        ]);

        // Generate conversions if needed
        if (!empty($options['conversions'])) {
            $this->performConversions($media, $file, $options['conversions']);
        }

        // Generate responsive images if it's an image
        if ($media->isImage() && ($options['responsive'] ?? true)) {
            $this->generateResponsiveImages($media, $file);
        }

        return $media;
    }

    /**
     * Generate a unique filename.
     */
    protected function generateFileName(UploadedFile $file): string 
    {
        $extension = $file->getClientOriginalExtension();
        return sprintf(
            '%s-%s.%s',
            Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)),
            Str::random(10),
            $extension
        );
    }

    /**
     * Generate the storage path.
     */
    protected function generatePath(string $collection, string $fileName): string
    {
        return sprintf(
            '%s/%s/%s/%s',
            Str::plural(Str::snake(class_basename($this))),
            $this->getKey(),
            $collection,
            $fileName
        );
    }

    /**
     * Get the next order number for media in a collection.
     */
    protected function getNextMediaOrder(string $collection): int
    {
        return $this->media()
            ->where('collection_name', $collection)
            ->max('order') + 1;
    }

    /**
     * Extract metadata from file.
     */
    protected function extractMetadata(UploadedFile $file): array 
    {
        $metadata = [
            'original_name' => $file->getClientOriginalName(),
            'extension' => $file->getClientOriginalExtension(),
        ];

        if ($this->isImageFile($file)) {
            try {
                $image = Image::make($file->getRealPath());
                $metadata['dimensions'] = [
                    'width' => $image->width(),
                    'height' => $image->height(),
                ];
            } catch (\Exception $e) {
                Log::warning('Failed to extract image dimensions: ' . $e->getMessage());
                $metadata['dimensions'] = [
                    'width' => 0,
                    'height' => 0,
                ];
            }
        }

        return $metadata;
    }

    /**
     * Check if the file is an image.
     */
    protected function isImageFile(UploadedFile $file): bool
    {
        return in_array($file->getMimeType(), [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
        ]);
    }

    /**
     * Perform conversions on the media file.
     */
    protected function performConversions(
        MediaFile $media,
        UploadedFile $file,
        array $conversions
    ): void {
        $conversionsData = [];

        foreach ($conversions as $name => $conversion) {
            $fileName = sprintf(
                '%s-%s.%s',
                pathinfo($media->file_name, PATHINFO_FILENAME),
                $name,
                $media->getExtension()
            );
            
            $path = sprintf(
                '%s/conversions/%s',
                dirname($media->path),
                $fileName
            );

            try {
                $image = Image::make($file->getRealPath());

                // Apply conversion operations
                if (!empty($conversion['width']) || !empty($conversion['height'])) {
                    $image->resize($conversion['width'] ?? null, $conversion['height'] ?? null, function ($constraint) {
                        $constraint->aspectRatio();
                    });
                }

                // Apply additional operations
                if (!empty($conversion['operations'])) {
                    foreach ($conversion['operations'] as $operation => $params) {
                        $image->{$operation}(...$params);
                    }
                }

                // Save conversion
                Storage::disk($media->disk)->put(
                    $path,
                    $image->encode($media->getExtension(), $conversion['quality'] ?? 90)
                );

                $conversionsData[$name] = $path;
            } catch (\Exception $e) {
                Log::error('Failed to perform conversion ' . $name . ': ' . $e->getMessage());
                continue;
            }
        }

        $media->update(['conversions' => $conversionsData]);
    }

    /**
     * Generate responsive images.
     */
    protected function generateResponsiveImages(
        MediaFile $media,
        UploadedFile $file
    ): void {
        if (!$this->isImageFile($file)) {
            return;
        }

        $responsiveImages = [];
        $sizes = [2048, 1024, 768, 480];

        try {
            $image = Image::make($file->getRealPath());
            $originalWidth = $image->width();

            foreach ($sizes as $size) {
                if ($size >= $originalWidth) {
                    continue;
                }

                $fileName = sprintf(
                    '%s-%dw.%s',
                    pathinfo($media->file_name, PATHINFO_FILENAME),
                    $size,
                    $media->getExtension()
                );
            
                $path = sprintf(
                    '%s/responsive/%s',
                    dirname($media->path),
                    $fileName
                );

                $image = Image::make($file->getRealPath())->resize($size, null, function ($constraint) {
                    $constraint->aspectRatio();
                });

                Storage::disk($media->disk)->put(
                    $path,
                    $image->encode($media->getExtension(), 80)
                );

                $responsiveImages[$size] = $path;
            }

            $media->update(['responsive_images' => $responsiveImages]);
        } catch (\Exception $e) {
            Log::error('Failed to generate responsive images: ' . $e->getMessage());
        }
    }

    /**
     * Get media from a specific collection.
     */
    public function getMedia(string $collection = 'default'): Collection
    {
        return $this->media()
            ->where('collection_name', $collection)
            ->orderBy('order')
            ->get();
    }

    /**
     * Clear media from a specific collection.
     */
    public function clearMedia(string $collection = 'default'): void
    {
        $this->media()
            ->where('collection_name', $collection)
            ->get()
            ->each
            ->delete();
    }
}