<?php

namespace App\Models\Traits;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait BelongsToTenant
{
    /**
     * Boot the trait.
     */
    protected static function bootBelongsToTenant()
    {
        // Automatically scope queries to current tenant
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (static::shouldApplyTenantScope()) {
                $tenantId = static::getCurrentTenantId();
                if ($tenantId) {
                    $builder->where('tenant_id', $tenantId);
                }
            }
        });

        // Automatically set tenant_id when creating
        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                $model->tenant_id = static::getCurrentTenantId();
            }
        });
    }

    /**
     * Get the tenant that owns the model.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope query to specific tenant.
     */
    public function scopeForTenant(Builder $query, $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope query without tenant restrictions (for super admins).
     */
    public function scopeWithoutTenantScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('tenant');
    }

    /**
     * Get current tenant ID from authenticated user.
     */
    protected static function getCurrentTenantId(): ?int
    {
        $user = Auth::user();
        
        if (!$user) {
            return null;
        }

        // Super admins can access all tenants
        if ($user->hasRole('super-admin')) {
            return request()->header('X-Tenant-ID') ?? session('current_tenant_id');
        }

        return $user->tenant_id;
    }

    /**
     * Determine if tenant scope should be applied.
     */
    protected static function shouldApplyTenantScope(): bool
    {
        // Don't apply scope during migrations or seeding
        if (app()->runningInConsole() && !app()->runningUnitTests()) {
            return false;
        }

        return true;
    }

    /**
     * Check if model belongs to current tenant.
     */
    public function belongsToCurrentTenant(): bool
    {
        $currentTenantId = static::getCurrentTenantId();
        return $currentTenantId && $this->tenant_id === $currentTenantId;
    }

    /**
     * Check if model belongs to specific tenant.
     */
    public function belongsToTenant($tenantId): bool
    {
        return $this->tenant_id === $tenantId;
    }
}
