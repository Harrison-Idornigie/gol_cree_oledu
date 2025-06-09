'use client';

import { useCallback } from 'react';
import { useDropzone } from 'react-dropzone';
import { MediaItem } from '@/types/section';
import { Button } from '@/components/ui/button';
import { Media } from './types';

export interface MediaUploaderProps {
  // Support both old and new interfaces
  files?: MediaItem[];
  media?: Media[] | MediaItem[];
  onUpload?: (files: MediaItem[]) => void;
  onUpdate?: (media: Media[]) => void;
  maxFiles?: number;
  allowedTypes?: string[];
}

export default function MediaUploader({
  files,
  media,
  onUpload,
  onUpdate,
  maxFiles = 5,
  allowedTypes = ['image/*', 'audio/*', 'video/*'],
}: MediaUploaderProps) {
  const displayFiles = (files || media || []) as (Media | MediaItem)[];
  const updateHandler = onUpdate || onUpload;

  const onDrop = useCallback(
    async (acceptedFiles: File[]) => {
      // In a real app, you would upload files to your server/storage here
      const newMedia = acceptedFiles.map((file) => ({
        type: file.type.startsWith('image/')
          ? 'image'
          : file.type.startsWith('audio/')
          ? 'audio'
          : 'video',
        url: URL.createObjectURL(file),
        alt: file.name,
      })) as Media[];

      if (updateHandler) {
        updateHandler(newMedia);
      }
    },
    [updateHandler]
  );

  const { getRootProps, getInputProps, isDragActive } = useDropzone({
    onDrop,
    accept: allowedTypes.reduce(
      (acc, type) => ({ ...acc, [type]: [] }),
      {}
    ),
    maxFiles,
  });

  const handleRemove = (index: number) => {
    if (!updateHandler) return;
    
    const newFiles = [...displayFiles];
    newFiles.splice(index, 1);
    updateHandler(newFiles as Media[]);
  };

  return (
    <div className="space-y-4">
      <div
        {...getRootProps()}
        className={`border-2 border-dashed rounded-lg p-6 text-center transition-colors ${
          isDragActive ? 'border-primary bg-primary/10' : 'border-border'
        }`}
      >
        <input {...getInputProps()} />
        <p className="text-sm text-muted-foreground">
          {isDragActive
            ? 'Drop the files here...'
            : 'Drag and drop files here, or click to select files'}
        </p>
        <p className="text-xs text-muted-foreground mt-2">
          Supported formats: Images, Audio, Video (Max {maxFiles} files)
        </p>
      </div>

      {displayFiles.length > 0 && (
        <div className="space-y-2">
          {displayFiles.map((file, index) => (
            <div
              key={index}
              className="flex items-center justify-between p-2 border rounded"
            >
              <div className="flex items-center gap-2">
                <div className="w-10 h-10 bg-muted rounded flex items-center justify-center">
                  {file.type === 'image' ? (
                    <img
                      src={file.url}
                      alt={file.alt || ''}
                      className="w-full h-full object-cover rounded"
                    />
                  ) : file.type === 'audio' ? (
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      width="24"
                      height="24"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      strokeWidth="2"
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      className="w-6 h-6"
                    >
                      <path d="M9 18V5l12-2v13" />
                      <circle cx="6" cy="18" r="3" />
                      <circle cx="18" cy="16" r="3" />
                    </svg>
                  ) : (
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      width="24"
                      height="24"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      strokeWidth="2"
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      className="w-6 h-6"
                    >
                      <polygon points="23 7 16 12 23 17 23 7" />
                      <rect x="1" y="5" width="15" height="14" rx="2" ry="2" />
                    </svg>
                  )}
                </div>
                <span className="text-sm truncate max-w-[200px]">
                  {file.alt || 'Unnamed file'}
                </span>
              </div>
              <Button
                variant="ghost"
                size="sm"
                onClick={() => handleRemove(index)}
              >
                Remove
              </Button>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}