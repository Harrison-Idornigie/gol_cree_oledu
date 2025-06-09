<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ContentVersion extends Model
{
    protected $fillable = [
        'version_number',
        'content',
        'changes',
        'change_type',
        'change_reason',
        'is_major_version',
        'published_at',
        'metadata',
    ];

    protected $casts = [
        'content' => 'array',
        'changes' => 'array',
        'metadata' => 'array',
        'is_major_version' => 'boolean',
        'published_at' => 'datetime',
    ];

    /**
     * Get the parent versionable model.
     */
    public function versionable(): MorphTo
    {
        return $this->morphTo();
    }
}