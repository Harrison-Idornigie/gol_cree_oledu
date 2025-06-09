<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLanguage extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'language_id',
        'is_primary',
        'proficiency_level',
        'metadata',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Get the user that owns this language selection.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the language that is selected.
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }
}
