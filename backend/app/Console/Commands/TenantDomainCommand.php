<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantResolutionService;
use Illuminate\Console\Command;

/**
 * Tenant Domain Management Command
 * 
 * Provides CLI tools for managing tenant domains and troubleshooting
 * tenant resolution issues.
 */
class TenantDomainCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tenant:domain 
                            {action : Action to perform (list|validate|test|clear-cache)}
                            {--tenant= : Tenant ID or slug}
                            {--domain= : Domain to test}
                            {--subdomain= : Subdomain to test}';

    /**
     * The console command description.
     */
    protected $description = 'Manage tenant domains and test resolution';

    /**
     * Tenant resolution service
     */
    protected TenantResolutionService $tenantResolver;

    /**
     * Constructor
     */
    public function __construct(TenantResolutionService $tenantResolver)
    {
        parent::__construct();
        $this->tenantResolver = $tenantResolver;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'list' => $this->listTenants(),
            'validate' => $this->validateTenant(),
            'test' => $this->testResolution(),
            'clear-cache' => $this->clearCache(),
            default => $this->error("Unknown action: {$action}"),
        };
    }

    /**
     * List all tenants with their domain configuration
     */
    protected function listTenants(): int
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->info('No tenants found.');
            return 0;
        }

        $headers = ['ID', 'Name', 'Subdomain', 'Custom Domain', 'Status', 'URLs'];
        $rows = [];

        foreach ($tenants as $tenant) {
            $urls = [];
            if ($tenant->subdomain) {
                $urls[] = $tenant->getSubdomainUrl();
            }
            if ($tenant->custom_domain) {
                $urls[] = $tenant->getCustomDomainUrl();
            }

            $rows[] = [
                $tenant->id,
                $tenant->name,
                $tenant->subdomain ?: '-',
                $tenant->custom_domain ?: '-',
                $tenant->status,
                implode("\n", $urls) ?: '-'
            ];
        }

        $this->table($headers, $rows);
        return 0;
    }

    /**
     * Validate tenant domain configuration
     */
    protected function validateTenant(): int
    {
        $tenantOption = $this->option('tenant');
        
        if (!$tenantOption) {
            $this->error('Please specify a tenant using --tenant option');
            return 1;
        }

        $tenant = $this->findTenant($tenantOption);
        if (!$tenant) {
            return 1;
        }

        $this->info("Validating tenant: {$tenant->name} (ID: {$tenant->id})");
        $this->newLine();

        // Validate domain configuration
        $issues = $this->tenantResolver->validateTenantDomains($tenant);

        if (empty($issues)) {
            $this->info('✅ No domain configuration issues found.');
        } else {
            $this->error('❌ Domain configuration issues found:');
            foreach ($issues as $issue) {
                $this->line("  • {$issue}");
            }
        }

        // Show current configuration
        $this->newLine();
        $this->info('Current Configuration:');
        $this->line("Subdomain: " . ($tenant->subdomain ?: 'Not set'));
        $this->line("Custom Domain: " . ($tenant->custom_domain ?: 'Not set'));
        $this->line("Status: {$tenant->status}");

        // Show available URLs
        $this->newLine();
        $this->info('Available URLs:');
        $urls = $tenant->getAllUrls();
        foreach ($urls as $type => $url) {
            $this->line("  {$type}: {$url}");
        }

        return empty($issues) ? 0 : 1;
    }

    /**
     * Test tenant resolution
     */
    protected function testResolution(): int
    {
        $domain = $this->option('domain');
        $subdomain = $this->option('subdomain');

        if (!$domain && !$subdomain) {
            $this->error('Please specify either --domain or --subdomain option');
            return 1;
        }

        if ($domain) {
            $this->info("Testing custom domain resolution: {$domain}");
            $tenant = $this->tenantResolver->resolveByCustomDomain(
                request()->create("http://{$domain}")
            );
        } else {
            $appDomain = config('app.domain');
            $fullDomain = "{$subdomain}.{$appDomain}";
            $this->info("Testing subdomain resolution: {$fullDomain}");
            $tenant = $this->tenantResolver->resolveBySubdomain(
                request()->create("http://{$fullDomain}")
            );
        }

        if ($tenant) {
            $this->info("✅ Tenant resolved successfully:");
            $this->line("  ID: {$tenant->id}");
            $this->line("  Name: {$tenant->name}");
            $this->line("  Status: {$tenant->status}");
        } else {
            $this->error('❌ No tenant found for the specified domain');
            return 1;
        }

        return 0;
    }

    /**
     * Clear tenant resolution cache
     */
    protected function clearCache(): int
    {
        $tenantOption = $this->option('tenant');

        if ($tenantOption) {
            $tenant = $this->findTenant($tenantOption);
            if (!$tenant) {
                return 1;
            }

            $this->tenantResolver->clearCache($tenant);
            $this->info("Cache cleared for tenant: {$tenant->name}");
        } else {
            $this->tenantResolver->clearCache();
            $this->info('All tenant resolution cache cleared');
        }

        return 0;
    }

    /**
     * Find tenant by ID or slug
     */
    protected function findTenant(string $identifier): ?Tenant
    {
        $tenant = null;

        if (is_numeric($identifier)) {
            $tenant = Tenant::find($identifier);
        } else {
            $tenant = Tenant::where('slug', $identifier)->first();
        }

        if (!$tenant) {
            $this->error("Tenant not found: {$identifier}");
            return null;
        }

        return $tenant;
    }
}
