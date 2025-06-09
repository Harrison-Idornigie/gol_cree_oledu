<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\MediaLibrary\HasMedia;

class MediaService
{
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
