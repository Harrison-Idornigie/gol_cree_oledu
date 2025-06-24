<?php

namespace App\Http\Controllers\API\Tenant\Team;

use App\Http\Controllers\API\BaseAPIController;
use App\Services\Tenants\Media\MediaManagementService;
use App\Models\Tenants\MediaFile;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Exception;

/**
 * Team Media Controller
 * 
 * Handles media upload and management operations for team members.
 * Access Level: Team (Teams/Content Creators)
 * Scope: Tenant-specific
 * 
 * This controller allows team members to upload and manage media files
 * for their content within their tenant scope.
 */
class TeamMediaController extends BaseAPIController
{
    use BelongsToTenant;

    protected MediaManagementService $mediaService;

    /**
     * Constructor - Apply team middleware
     */
    public function __construct(MediaManagementService $mediaService)
    {
        $this->mediaService = $mediaService;

        // Apply policies
        $this->authorizeResource(MediaFile::class, 'media');
    }

    /**
     * Upload media file.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function upload(Request $request): JsonResponse
    {
        $this->authorize('create', MediaFile::class);

        try {
            $validatedData = $request->validate([
                'file' => 'required|file|max:51200', // 50MB max
                'description' => 'nullable|string|max:255',
                'alt_text' => 'nullable|string|max:255',
                'collection' => 'nullable|string|max:50',
                'status' => 'nullable|string|in:draft,published,archived',
            ]);

            $file = $request->file('file');
            $mediaFile = $this->mediaService->uploadMediaFile($file, $validatedData, Auth::user());

            return $this->sendCreatedResponse($mediaFile, 'Media uploaded successfully.');
        } catch (ValidationException $e) {
            return $this->sendError('Validation failed.', $e->errors(), 422);
        } catch (Exception $e) {
            return $this->sendError('Failed to upload media.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified media file.
     * 
     * @param Request $request
     * @param MediaFile $media
     * @return JsonResponse
     */
    public function destroy(Request $request, MediaFile $media): JsonResponse
    {
        $this->authorize('delete', $media);

        try {
            $deleted = $this->mediaService->deleteMediaFile($media, Auth::user());

            if (!$deleted) {
                return $this->sendError('Failed to delete media file.');
            }

            return $this->sendNoContentResponse();
        } catch (Exception $e) {
            return $this->sendError('Failed to delete media file.', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get team member's media files.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function myMedia(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MediaFile::class);

        try {
            // Filter to only current user's media
            $filteredRequest = $request->duplicate();
            $filteredRequest->merge(['uploader_id' => Auth::id()]);

            $mediaFiles = $this->mediaService->getFilteredMediaFiles($filteredRequest, 'team');

            return $this->sendResponse($mediaFiles, 'Media files retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendError('Failed to retrieve media files.', ['error' => $e->getMessage()]);
        }
    }
}
