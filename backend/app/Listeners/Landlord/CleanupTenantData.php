<?php

namespace App\Listeners\Landlord;

use App\Events\Landlord\TenantDeleting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Cleanup Tenant Data Listener
 * 
 * Handles the cleanup of tenant-specific data when a tenant is being deleted.
 * This listener automatically discovers tenant-scoped tables and cleans them up.
 */
class CleanupTenantData
{
    /**
     * Handle the event.
     */
    public function handle(TenantDeleting $event): void
    {
        $tenant = $event->tenant;

        try {
            // Set tenant context
            app()->instance('current_tenant', $tenant);

            // Get all tenant-scoped tables dynamically
            $tenantTables = $this->discoverTenantTables();
            
            // Drop tenant-specific tables
            $this->dropTenantTables($tenantTables);

            Log::info('Tenant data cleanup completed', [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'tables_dropped' => count($tenantTables)
            ]);

        } catch (Exception $e) {
            Log::error('Failed to cleanup tenant data', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Don't throw - let tenant deletion proceed even if cleanup fails
        }
    }

    /**
     * Dynamically discover tenant-scoped tables
     * 
     * This method looks for tables that have a 'tenant_id' column,
     * making the cleanup process flexible and automatic.
     */
    protected function discoverTenantTables(): array
    {
        $tenantTables = [];
        $allTables = $this->getAllTables();

        foreach ($allTables as $table) {
            if ($this->hasTenantIdColumn($table)) {
                $tenantTables[] = $table;
            }
        }

        // Add any additional tables from configuration
        $configTables = config('tenant.cleanup_tables', []);
        $tenantTables = array_merge($tenantTables, $configTables);

        return array_unique($tenantTables);
    }

    /**
     * Get all tables in the database
     */
    protected function getAllTables(): array
    {
        $database = config('database.connections.' . config('database.default') . '.database');
        
        $tables = DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = ?", [$database]);
        
        return array_map(function ($table) {
            return $table->table_name ?? $table->TABLE_NAME;
        }, $tables);
    }

    /**
     * Check if a table has a tenant_id column
     */
    protected function hasTenantIdColumn(string $table): bool
    {
        try {
            return Schema::hasColumn($table, 'tenant_id');
        } catch (Exception $e) {
            // If we can't check the table, skip it
            return false;
        }
    }

    /**
     * Drop tenant-specific database tables
     */
    protected function dropTenantTables(array $tenantTables): void
    {
        // Disable foreign key checks temporarily
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($tenantTables as $table) {
                if (Schema::hasTable($table)) {
                    Schema::drop($table);
                    Log::debug("Dropped tenant table: {$table}");
                }
            }
        } finally {
            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}
