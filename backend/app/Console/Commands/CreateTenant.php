<?php

namespace App\Console\Commands;

use App\Models\Landlord\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Create Tenant Command
 * 
 * Creates a new tenant with custom naming and database setup.
 */
class CreateTenant extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tenant:create 
                            {name : The tenant name}
                            {--id= : Custom tenant ID (optional)}
                            {--slug= : Custom slug (optional)}
                            {--domain= : Primary domain (optional)}
                            {--subdomain= : Subdomain (optional)}
                            {--email= : Contact email}
                            {--phone= : Contact phone}
                            {--description= : Description}
                            {--migrate : Run migrations after creation}';

    /**
     * The console command description.
     */
    protected $description = 'Create a new tenant with custom database naming';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->argument('name');
        
        $this->info("🏢 Creating tenant: {$name}");
        
        try {
            // Prepare tenant data
            $tenantData = [
                'name' => $name,
                'description' => $this->option('description'),
                'contact_email' => $this->option('email'),
                'contact_phone' => $this->option('phone'),
                'status' => 'trial',
                'trial_ends_at' => now()->addDays(30), // 30-day trial
            ];
            
            // Add custom ID if provided
            if ($customId = $this->option('id')) {
                $tenantData['id'] = $customId;
            }
            
            // Add custom slug if provided
            if ($customSlug = $this->option('slug')) {
                $tenantData['slug'] = $customSlug;
            }
            
            // Create tenant
            $tenant = Tenant::create($tenantData);
            
            $this->info("✅ Tenant created successfully!");
            $this->displayTenantInfo($tenant);
            
            // Add domain if provided
            if ($domain = $this->option('domain')) {
                $tenant->domains()->create(['domain' => $domain]);
                $this->info("🌐 Domain added: {$domain}");
            }
            
            // Add subdomain if provided
            if ($subdomain = $this->option('subdomain')) {
                $centralDomain = config('app.url');
                $fullDomain = $subdomain . '.' . parse_url($centralDomain, PHP_URL_HOST);
                $tenant->domains()->create(['domain' => $fullDomain]);
                $this->info("🌐 Subdomain added: {$fullDomain}");
            }
            
            // Run migrations if requested
            if ($this->option('migrate')) {
                $this->info("🔄 Running tenant migrations...");
                $this->call('tenants:migrate', ['--tenants' => [$tenant->id]]);
            }
            
            $this->newLine();
            $this->info("🎉 Tenant setup completed!");
            $this->displayNextSteps($tenant);
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error("❌ Error creating tenant: " . $e->getMessage());
            return 1;
        }
    }
    
    /**
     * Display tenant information
     */
    protected function displayTenantInfo(Tenant $tenant): void
    {
        $this->newLine();
        $this->info("📋 Tenant Information:");
        $this->line("  ID: {$tenant->id}");
        $this->line("  Name: {$tenant->name}");
        $this->line("  Slug: {$tenant->slug}");
        $this->line("  Database: {$tenant->getDatabaseName()}");
        $this->line("  Status: {$tenant->status}");
        
        if ($tenant->trial_ends_at) {
            $this->line("  Trial ends: {$tenant->trial_ends_at->format('Y-m-d H:i:s')}");
        }
    }
    
    /**
     * Display next steps
     */
    protected function displayNextSteps(Tenant $tenant): void
    {
        $this->info("🔧 Next Steps:");
        
        if (!$this->option('migrate')) {
            $this->line("  1. Run migrations: php artisan tenants:migrate --tenants={$tenant->id}");
        }
        
        if (!$this->option('domain') && !$this->option('subdomain')) {
            $this->line("  2. Add domain: php artisan tenants:domain {$tenant->id} example.com");
        }
        
        $this->line("  3. Test access via configured domain");
        $this->line("  4. Create admin user for the tenant");
        
        $this->newLine();
        $this->info("💡 Example Usage:");
        $this->line("  # Create with subdomain");
        $this->line("  php artisan tenant:create 'School District ABC' --subdomain=abc --migrate");
        $this->line("");
        $this->line("  # Create with custom domain");
        $this->line("  php artisan tenant:create 'University XYZ' --domain=learn.university.edu --migrate");
    }
}
