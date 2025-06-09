<?php

namespace App\Models;

use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Tenant extends Model
{
    use HasFactory, HasAuditLog;

    const AUDIT_AREA = 'tenants';

    protected $fillable = [
        'name',
        'slug',
        'domain',
        'description',
        'settings',
        'status',
        'contact_info',
        'trial_ends_at',
        'subscription_ends_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'contact_info' => 'array',
        'trial_ends_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'active',
        'settings' => '{}',
    ];

    protected array $auditLogEvents = [
        'created' => 'Created new tenant: :name',
        'updated' => 'Updated tenant: :name',
        'deleted' => 'Deleted tenant: :name',
    ];

    protected array $auditLogProperties = [
        'name',
        'slug',
        'domain',
        'status',
        'settings',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tenant) {
            if (empty($tenant->slug)) {
                $tenant->slug = Str::slug($tenant->name);
            }
        });
    }

    /**
     * Get the users for this tenant.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the learning paths for this tenant.
     */
    public function learningPaths(): HasMany
    {
        return $this->hasMany(LearningPath::class);
    }

    /**
     * Get the languages for this tenant.
     */
    public function languages(): HasMany
    {
        return $this->hasMany(Language::class);
    }

    /**
     * Get the roles for this tenant.
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    /**
     * Get the audit logs for this tenant.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Check if tenant is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if tenant is on trial.
     */
    public function isOnTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Check if tenant subscription is active.
     */
    public function hasActiveSubscription(): bool
    {
        return $this->subscription_ends_at && $this->subscription_ends_at->isFuture();
    }

    /**
     * Get tenant setting by key.
     */
    public function getSetting(string $key, $default = null)
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Set tenant setting.
     */
    public function setSetting(string $key, $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->settings = $settings;
        $this->save();
    }

    /**
     * Get tenant administrators.
     */
    public function administrators(): HasMany
    {
        return $this->users()->whereHas('roles', function ($query) {
            $query->where('slug', 'tenant-admin');
        });
    }

    /**
     * Get tenant teachers.
     */
    public function teachers(): HasMany
    {
        return $this->users()->whereHas('roles', function ($query) {
            $query->where('slug', 'teacher');
        });
    }

    /**
     * Get tenant students.
     */
    public function students(): HasMany
    {
        return $this->users()->whereHas('roles', function ($query) {
            $query->where('slug', 'student');
        });
    }

    /**
     * Scope for active tenants.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Get tenant statistics.
     */
    public function getStatistics(): array
    {
        return [
            'total_users' => $this->users()->count(),
            'total_students' => $this->students()->count(),
            'total_teachers' => $this->teachers()->count(),
            'total_learning_paths' => $this->learningPaths()->count(),
            'published_learning_paths' => $this->learningPaths()->where('status', 'published')->count(),
            'total_languages' => $this->languages()->count(),
        ];
    }
}
