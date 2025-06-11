<?php

namespace App\Models\Landlord;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

/**
 * Custom Tenant Model
 * 
 * Extends Stancl's base tenant model to provide custom database naming
 * and additional tenant management features.
 */
class Tenant extends BaseTenant implements \Stancl\Tenancy\Contracts\TenantWithDatabase
{
    use HasDatabase, HasDomains;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'slug',
        'database_name',
        'description',
        'contact_email',
        'contact_phone',
        'address',
        'settings',
        'status',
        'trial_ends_at',
        'subscription_ends_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'settings' => 'array',
        'trial_ends_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden.
     */
    protected $hidden = [
        // Add any sensitive fields here
    ];


    /**
     * Get the custom columns that should NOT be stored in the data JSON column.
     * These columns have their own dedicated database columns.
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'slug',
            'database_name',
            'description',
            'contact_email',
            'contact_phone',
            'address',
            'settings',
            'status',
            'trial_ends_at',
            'subscription_ends_at',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * Override to disable auto-incrementing since we use string IDs
     */
    public function getIncrementing()
    {
        return false;
    }

    /**
     * Override to specify the key type as string
     */
    public function getKeyType()
    {
        return 'string';
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tenant) {
            // Generate slug first if not provided
            if (empty($tenant->slug)) {
                $tenant->slug = static::generateSlug($tenant->name);
            }

            // Let Stancl handle ID generation, but generate database name
            if (empty($tenant->database_name)) {
                $tenant->database_name = static::generateDatabaseName($tenant);
            }
        });
    }

    /**
     * Generate a custom tenant ID
     */
    protected static function generateCustomId($tenant): string
    {
        // Use slug-based ID with fallback to name-based slug
        $baseId = $tenant->slug ?: Str::slug($tenant->name);

        // Fallback to UUID if no name is available
        if (empty($baseId)) {
            return Str::uuid()->toString();
        }

        // Ensure uniqueness
        $id = $baseId;
        $counter = 1;

        while (static::where('id', $id)->exists()) {
            $id = $baseId . '_' . $counter;
            $counter++;
        }

        return $id;
    }

    /**
     * Generate a URL-friendly slug
     */
    protected static function generateSlug(string $name): string
    {
        $slug = Str::slug($name);
        
        // Ensure uniqueness
        $originalSlug = $slug;
        $counter = 1;
        
        while (static::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }

    /**
     * Generate database name based on tenant properties
     */
    protected static function generateDatabaseName($tenant): string
    {
        $prefix = config('tenancy.database.prefix', 'gol_tenant_');
        $suffix = config('tenancy.database.suffix', '');
        
        // Use slug for database name (more readable than UUID)
        $identifier = $tenant->slug ?: static::generateSlug($tenant->name);
        
        // Sanitize for database naming rules
        $identifier = static::sanitizeForDatabase($identifier);
        
        return $prefix . $identifier . $suffix;
    }

    /**
     * Sanitize string for database name
     */
    protected static function sanitizeForDatabase(string $input): string
    {
        // Remove special characters, keep only alphanumeric and underscores
        $sanitized = preg_replace('/[^a-zA-Z0-9_]/', '_', $input);
        
        // Ensure it starts with a letter
        if (is_numeric(substr($sanitized, 0, 1))) {
            $sanitized = 'tenant_' . $sanitized;
        }
        
        // Limit length (MySQL database names max 64 chars)
        return substr($sanitized, 0, 50);
    }

    /**
     * Get the database name for this tenant
     */
    public function getDatabaseName(): string
    {
        return $this->database_name ?: static::generateDatabaseName($this);
    }

    /**
     * Scope for active tenants
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for trial tenants
     */
    public function scopeOnTrial($query)
    {
        return $query->whereNotNull('trial_ends_at')
                    ->where('trial_ends_at', '>', now());
    }

    /**
     * Check if tenant is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if tenant is on trial
     */
    public function isOnTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Get tenant users (via tenant database context)
     * Note: This is a virtual relationship - not a true Eloquent relationship
     */
    public function getTenantUsers()
    {
        $users = collect();

        try {
            $this->run(function () use (&$users) {
                $users = \App\Models\Tenants\User::all();
            });
        } catch (\Exception $e) {
            // Tenant database might not exist yet
            Log::warning('Could not load tenant users', [
                'tenant_id' => $this->id,
                'error' => $e->getMessage()
            ]);
        }

        return $users;
    }

    /**
     * Get tenant statistics
     */
    public function getStatistics(): array
    {
        $stats = [
            'total_users' => 0,
            'total_students' => 0,
            'total_teachers' => 0,
            'total_learning_paths' => 0,
            'total_languages' => 0,
        ];

        try {
            $this->run(function () use (&$stats) {
                $stats['total_users'] = \App\Models\Tenants\User::count();
                $stats['total_students'] = \App\Models\Tenants\User::whereHas('memberships', function($q) {
                    $q->where('slug', 'student');
                })->count();
                $stats['total_teachers'] = \App\Models\Tenants\User::whereHas('memberships', function($q) {
                    $q->where('slug', 'team');
                })->count();
                $stats['total_learning_paths'] = \App\Models\Tenants\LearningPath::count();
                $stats['total_languages'] = \App\Models\Tenants\Language::count();
            });
        } catch (\Exception $e) {
            // Tenant database might not exist yet
            Log::warning('Could not load tenant statistics', [
                'tenant_id' => $this->id,
                'error' => $e->getMessage()
            ]);
        }

        return $stats;
    }

    /**
     * Get the primary domain for this tenant
     */
    public function getPrimaryDomain(): ?string
    {
        return $this->domains()->first()?->domain;
    }

    /**
     * Get all URLs for this tenant
     */
    public function getUrls(): array
    {
        return $this->domains()->pluck('domain')->toArray();
    }
}
