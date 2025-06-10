<?php

namespace App\Models\Tenants;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class UserFeedback extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'user_id',
        'content_type',
        'content_id',
        'message',
        'rating'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function content(): MorphTo
    {
        return $this->morphTo();
    }
}