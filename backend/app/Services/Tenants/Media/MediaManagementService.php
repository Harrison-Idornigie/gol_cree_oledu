<?php

namespace App\Services\Tenants\Media;

use App\Models\Tenants\MediaFile;
use App\Models\Tenants\User;
use App\Models\Tenants\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

/**
 * Media Management Service
 * 
 * Handles CRUD operations and business logic for media files including:
 * - File uploads and processing
 * - Media file management and organization
 * - Thumbnail generation and processing
 * - File validation and security
 */
class MediaManagementService
{
    protected MediaService $mediaService;

    public function __construct(MediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    /**
     * Get filtered media files with membership-based access.
     */
    public function getFilteredMediaFiles(Request $request, string $membership = 'student'): LengthAwarePaginator
    {
        $query = MediaFile::query();

        // Apply membership-based filtering
        if (!in_array($membership, ['super-admin', 'tenant-admin', 'team'])) {
            // Students can only see published media
            $query->where('status', 'published');
        }

        // Apply filters
        if ($request->has('uploader_id')) {
            $query->where('uploader_id', $request->uploader_id);
        }

        if ($request->has('file_type')) {
            $query->where('file_type', $request->file_type);
        }

        if ($request->has('collection')) {
            $query->where('collection', $request->collection);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('original_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('alt_text', 'like', "%{$search}%");
            });
        }

        // Include relationships if requested
        if ($request->has('with_uploader')) {
            $query->with('uploader');
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        return $query->paginate($perPage);
    }

    /**
     * Upload and create a new media file.
     */
    public function uploadMediaFile(UploadedFile $file, array $data, User $user): MediaFile
    {
        return DB::transaction(function () use ($file, $data, $user) {
            // Validate file type and size
            $this->validateFile($file);

            // Generate unique filename
            $filename = $this->generateUniqueFilename($file);

            // Store file
            $path = $file->storeAs('media', $filename, 'public');

            // Create media record
            $mediaData = array_merge($data, [
                'original_name' => $file->getClientOriginalName(),
                'filename' => $filename,
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'file_type' => $file->getMimeType(),
                'extension' => $file->getClientOriginalExtension(),
                'uploader_id' => $user->id,
                'status' => $data['status'] ?? 'draft',
            ]);

            $mediaFile = MediaFile::create($mediaData);

            // Generate thumbnails for images
            if (Str::startsWith($file->getMimeType(), 'image/')) {
                $this->generateThumbnails($mediaFile, $file);
            }

            // Log the upload
            AuditLog::log(
                'create',
                'media',
                $mediaFile,
                [],
                $mediaData
            );

            return $mediaFile->load('uploader');
        });
    }

    /**
     * Update an existing media file.
     */
    public function updateMediaFile(MediaFile $mediaFile, array $data, User $user): MediaFile
    {
        return DB::transaction(function () use ($mediaFile, $data, $user) {
            $originalData = $mediaFile->toArray();
            $mediaFile->update($data);

            // Log the update
            AuditLog::log(
                'update',
                'media',
                $mediaFile,
                $originalData,
                $data
            );

            return $mediaFile->load('uploader');
        });
    }

    /**
     * Delete a media file.
     */
    public function deleteMediaFile(MediaFile $mediaFile, User $user): bool
    {
        return DB::transaction(function () use ($mediaFile, $user) {
            // Store data before deletion for audit
            $mediaData = $mediaFile->toArray();

            // Delete physical file
            if (Storage::disk('public')->exists($mediaFile->file_path)) {
                Storage::disk('public')->delete($mediaFile->file_path);
            }

            // Delete thumbnails if they exist
            if ($mediaFile->thumbnails) {
                foreach ($mediaFile->thumbnails as $thumbnail) {
                    if (Storage::disk('public')->exists($thumbnail['path'])) {
                        Storage::disk('public')->delete($thumbnail['path']);
                    }
                }
            }

            // Delete the media record
            $deleted = $mediaFile->delete();

            if ($deleted) {
                // Log the deletion
                AuditLog::log(
                    'delete',
                    'media',
                    null,
                    $mediaData,
                    []
                );
            }

            return $deleted;
        });
    }

    /**
     * Get media files by uploader.
     */
    public function getMediaByUploader(int $uploaderId, string $membership = 'student'): \Illuminate\Database\Eloquent\Collection
    {
        $query = MediaFile::where('uploader_id', $uploaderId);

        // Apply membership-based filtering
        if (!in_array($membership, ['super-admin', 'tenant-admin', 'team'])) {
            $query->where('status', 'published');
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Validate uploaded file.
     */
    protected function validateFile(UploadedFile $file): void
    {
        $allowedMimeTypes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'audio/mpeg',
            'audio/mp4',
            'audio/wav',
            'audio/ogg',
            'video/mp4',
            'video/webm',
            'video/ogg',
            'application/pdf',
            'text/plain',
        ];

        if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
            throw new Exception('File type not allowed: ' . $file->getMimeType());
        }

        // 50MB max file size
        if ($file->getSize() > 50 * 1024 * 1024) {
            throw new Exception('File size too large. Maximum size is 50MB.');
        }
    }

    /**
     * Generate unique filename.
     */
    protected function generateUniqueFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $basename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $basename = Str::slug($basename);

        return $basename . '_' . Str::random(8) . '_' . time() . '.' . $extension;
    }

    /**
     * Generate thumbnails for image files.
     */
    protected function generateThumbnails(MediaFile $mediaFile, UploadedFile $file): void
    {
        try {
            // This would integrate with an image processing service
            // For now, just mark that thumbnails could be generated
            $thumbnails = [
                'small' => ['width' => 150, 'height' => 150],
                'medium' => ['width' => 300, 'height' => 300],
                'large' => ['width' => 600, 'height' => 600],
            ];

            $mediaFile->update(['thumbnails' => $thumbnails]);
        } catch (Exception $e) {
            // Log error but don't fail the upload
            Log::warning('Failed to generate thumbnails for media file: ' . $mediaFile->id, [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get media statistics.
     */
    public function getMediaStatistics(array $filters = []): array
    {
        $query = MediaFile::query();

        // Apply filters
        if (!empty($filters['uploader_id'])) {
            $query->where('uploader_id', $filters['uploader_id']);
        }

        if (!empty($filters['collection'])) {
            $query->where('collection', $filters['collection']);
        }

        $total = $query->count();
        $totalSize = $query->sum('file_size');
        $byType = $query->groupBy('file_type')->selectRaw('file_type, count(*) as count')->pluck('count', 'file_type');
        $byStatus = $query->groupBy('status')->selectRaw('status, count(*) as count')->pluck('count', 'status');

        return [
            'total_files' => $total,
            'total_size_bytes' => $totalSize,
            'total_size_formatted' => $this->formatBytes($totalSize),
            'by_file_type' => $byType,
            'by_status' => $byStatus,
        ];
    }

    /**
     * Format bytes to human readable format.
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }



    /**
     * Add media to a model, checking for duplicates first.
     *
     * @param HasMedia $model The model to attach media to
     * @param UploadedFile|string $file The uploaded file or path to file
     * @param string $collection The collection name
     * @param array $customProperties Additional properties to store
     * @return \Spatie\MediaLibrary\MediaCollections\Models\Media
     */
    public function addMedia(HasMedia $model, $file, string $collection, array $customProperties = [])
    {
        // If the file is an UploadedFile, generate a hash of the content
        if ($file instanceof UploadedFile) {
            try {
                $fileHash = Hash::make($file->getContent());

                // Check if we already have this file in the system
                $existingMedia = DB::table('media')
                    ->where('collection_name', $collection)
                    ->whereJsonContains('custom_properties->file_hash', $fileHash)
                    ->first();

                if ($existingMedia) {
                    // If the file already exists, copy it to the new model
                    return $model->copyMedia(storage_path('app/public/' . $existingMedia->id . '/' . $existingMedia->file_name))
                        ->preservingOriginal()
                        ->toMediaCollection($collection);
                }

                // Add file hash to custom properties
                $customProperties['file_hash'] = $fileHash;
            } catch (\Exception $e) {
                // If we can't get the content (e.g. in tests), just continue without hash
            }

            // Add the media to the model
            return $model->addMedia($file)
                ->withCustomProperties($customProperties)
                ->toMediaCollection($collection);
        }

        // If the file is a string path
        return $model->addMedia($file)
            ->withCustomProperties($customProperties)
            ->toMediaCollection($collection);
    }

    /**
     * Find media by custom properties
     *
     * @param string $collection The collection name
     * @param array $properties Key-value pairs of properties to match
     * @return \Illuminate\Support\Collection
     */
    public function findMediaByProperties(string $collection, array $properties)
    {
        $query = DB::table('media')->where('collection_name', $collection);

        foreach ($properties as $key => $value) {
            $query->whereJsonContains("custom_properties->$key", $value);
        }

        return $query->get();
    }
}
