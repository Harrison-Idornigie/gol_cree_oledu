<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\MediaFile;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Image;

class AdminMediaController extends BaseAPIController
{
    /**
     * Upload a single media file.
     */
    public function upload(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'file' => 'required|file|max:102400', // 100MB max
                'mediable_type' => 'required|string',
                'mediable_id' => 'required|integer',
                'collection_name' => 'required|string',
                'disk' => 'nullable|string|in:local,s3,public',
                'conversions' => 'nullable|array',
                'custom_properties' => 'nullable|array'
            ]);

            $file = $request->file('file');
            if (!$file || !$file->isValid()) {
                return $this->sendError('Invalid file upload', [], 422);
            }

            $model = $this->getModelFromType($request->mediable_type, $request->mediable_id);
            if (!$model) {
                return $this->sendError('Invalid mediable type or ID.', ['error' => 'Model not found'], 404);
            }

            if (!method_exists($model, 'addMedia')) {
                return $this->sendError('Model does not support media attachments', [], 422);
            }

            $mediaFile = $model->addMedia(
                $file,
                $request->collection_name,
                [
                    'disk' => $request->input('disk', config('filesystems.default')),
                    'conversions' => $request->input('conversions', []),
                    'custom_properties' => $request->input('custom_properties', [])
                ]
            );

            return $this->sendCreatedResponse($mediaFile);
        } catch (Exception $e) {
            Log::error('Failed to upload file: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to upload file: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Upload multiple media files.
     */
    public function bulkUpload(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'files' => 'required|array',
                'files.*' => 'required|file|max:102400', // 100MB max per file
                'mediable_type' => 'required|string',
                'mediable_id' => 'required|integer',
                'collection_name' => 'required|string',
                'disk' => 'nullable|string|in:local,s3,public',
                'conversions' => 'nullable|array',
                'custom_properties' => 'nullable|array'
            ]);

            $model = $this->getModelFromType($request->mediable_type, $request->mediable_id);
            if (!$model) {
                return $this->sendError('Invalid mediable type or ID.', ['error' => 'Model not found'], 404);
            }

            if (!method_exists($model, 'addMedia')) {
                return $this->sendError('Model does not support media attachments', [], 422);
            }

            $files = $request->file('files');
            if (empty($files)) {
                return $this->sendError('No valid files provided', [], 422);
            }

            $uploadedFiles = [];
            $failedFiles = [];

            foreach ($files as $file) {
                if (!$file->isValid()) {
                    $failedFiles[] = [
                        'name' => $file->getClientOriginalName(),
                        'error' => 'Invalid file'
                    ];
                    continue;
                }

                try {
                    $mediaFile = $model->addMedia(
                        $file,
                        $request->collection_name,
                        [
                            'disk' => $request->input('disk', config('filesystems.default')),
                            'conversions' => $request->input('conversions', []),
                            'custom_properties' => $request->input('custom_properties', [])
                        ]
                    );
                    $uploadedFiles[] = $mediaFile;
                } catch (Exception $e) {
                    Log::error('Failed to upload file: ' . $e->getMessage(), [
                        'file' => $file->getClientOriginalName()
                    ]);
                    $failedFiles[] = [
                        'name' => $file->getClientOriginalName(),
                        'error' => $e->getMessage()
                    ];
                }
            }

            return $this->sendResponse([
                'uploaded' => $uploadedFiles,
                'failed' => $failedFiles
            ]);
        } catch (Exception $e) {
            Log::error('Bulk upload failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Bulk upload failed: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Delete a media file.
     */
    public function destroy(MediaFile $media): JsonResponse
    {
        try {
            $disk = Storage::disk($media->disk);
            if (!$disk->exists($media->path)) {
                Log::warning('Deleting media file but physical file not found', ['media' => $media]);
            }

            $media->delete();
            return $this->sendNoContentResponse();
        } catch (Exception $e) {
            Log::error('Failed to delete file: ' . $e->getMessage(), [
                'media_id' => $media->id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to delete file: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Optimize an image file.
     */
    public function optimize(MediaFile $media): JsonResponse
    {
        if (!$media->isImage()) {
            return $this->sendError('Only image files can be optimized.', ['error' => 'Only image files can be optimized'], 422);
        }

        try {
            $disk = Storage::disk($media->disk);

            if (!$disk->exists($media->path)) {
                return $this->sendError('Image file not found on disk', [], 404);
            }

            try {
                $image = Image::make($disk->get($media->path));
            } catch (Exception $e) {
                return $this->sendError('Failed to open image file: ' . $e->getMessage(), [], 422);
            }

            // Optimize image while maintaining quality
            $image->encode(null, 85); // 85% quality

            // Save optimized version
            $disk->put($media->path, $image->stream());

            // Update file size
            $media->update([
                'size' => $disk->size($media->path)
            ]);

            return $this->sendResponse($media);
        } catch (Exception $e) {
            Log::error('Failed to optimize image: ' . $e->getMessage(), [
                'media_id' => $media->id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to optimize image: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Generate additional image conversions.
     */
    public function generateConversions(Request $request, MediaFile $media): JsonResponse
    {
        if (!$media->isImage()) {
            return $this->sendError('Only image files can have conversions.', ['error' => 'Only image files can have conversions'], 422);
        }

        try {
            $request->validate([
                'conversions' => 'required|array',
                'conversions.*.name' => 'required|string',
                'conversions.*.width' => 'nullable|integer|min:1',
                'conversions.*.height' => 'nullable|integer|min:1',
                'conversions.*.quality' => 'nullable|integer|min:1|max:100',
                'conversions.*.format' => 'nullable|string|in:jpg,png,webp'
            ]);

            $disk = Storage::disk($media->disk);

            if (!$disk->exists($media->path)) {
                return $this->sendError('Image file not found on disk', [], 404);
            }

            try {
                $image = Image::make($disk->get($media->path));
            } catch (Exception $e) {
                return $this->sendError('Failed to open image file: ' . $e->getMessage(), [], 422);
            }

            $conversions = [];

            foreach ($request->conversions as $conversion) {
                try {
                    $convertedImage = clone $image;

                    // Resize if dimensions provided
                    if (!empty($conversion['width']) || !empty($conversion['height'])) {
                        $convertedImage->resize(
                            $conversion['width'] ?? null,
                            $conversion['height'] ?? null,
                            function ($constraint) {
                                $constraint->aspectRatio();
                                $constraint->upsize();
                            }
                        );
                    }

                    // Generate conversion path
                    $format = $conversion['format'] ?? pathinfo($media->file_name, PATHINFO_EXTENSION);
                    $conversionName = $conversion['name'];
                    $fileName = sprintf(
                        '%s-%s.%s',
                        pathinfo($media->file_name, PATHINFO_FILENAME),
                        $conversionName,
                        $format
                    );
                    $path = sprintf(
                        '%s/conversions/%s',
                        dirname($media->path),
                        $fileName
                    );

                    // Ensure conversions directory exists
                    $conversionDir = dirname($path);
                    if (!$disk->exists($conversionDir)) {
                        $disk->makeDirectory($conversionDir);
                    }

                    // Save conversion
                    $disk->put(
                        $path,
                        $convertedImage->encode($format, $conversion['quality'] ?? 90)
                    );

                    $conversions[$conversionName] = $path;
                } catch (Exception $e) {
                    Log::error('Failed to generate conversion: ' . $e->getMessage(), [
                        'conversion' => $conversion['name'],
                        'media_id' => $media->id
                    ]);
                }
            }

            if (empty($conversions)) {
                return $this->sendError('Failed to generate any conversions', [], 500);
            }

            // Update media record with new conversions
            $media->update([
                'conversions' => array_merge(
                    $media->conversions ?? [],
                    $conversions
                )
            ]);

            return $this->sendResponse($media);
        } catch (Exception $e) {
            Log::error('Failed to generate conversions: ' . $e->getMessage(), [
                'media_id' => $media->id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to generate conversions: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get model instance from type and ID.
     */
    private function getModelFromType(string $type, int $id): ?object
    {
        try {
            $modelClass = 'App\\Models\\' . Str::studly(Str::singular($type));

            if (!class_exists($modelClass)) {
                Log::warning('Invalid model class', ['class' => $modelClass]);
                return null;
            }

            $model = $modelClass::find($id);
            if (!$model) {
                Log::warning('Model not found', ['class' => $modelClass, 'id' => $id]);
            }

            return $model;
        } catch (Exception $e) {
            Log::error('Error in getModelFromType: ' . $e->getMessage(), [
                'type' => $type,
                'id' => $id
            ]);
            return null;
        }
    }
}
