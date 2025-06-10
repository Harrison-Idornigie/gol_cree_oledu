import { execSync } from 'child_process';

/**
 * Database Helper for E2E Tests
 * 
 * Provides utilities for managing test databases, tenant isolation,
 * and data cleanup during E2E testing.
 */
export class DatabaseHelper {
  private testTenantPrefix: string;
  private createdTenants: string[] = [];

  constructor() {
    this.testTenantPrefix = process.env.TEST_TENANT_PREFIX || 'e2e-test';
  }

  /**
   * Initialize the database helper
   */
  async initialize(): Promise<void> {
    // Ensure we're in testing mode
    process.env.APP_ENV = 'testing';
  }

  /**
   * Create a unique test tenant slug
   */
  generateTestTenantSlug(): string {
    const timestamp = Date.now();
    const random = Math.random().toString(36).substring(2, 8);
    const slug = `${this.testTenantPrefix}-${timestamp}-${random}`;
    this.createdTenants.push(slug);
    return slug;
  }

  /**
   * Generate test admin email
   */
  generateTestAdminEmail(tenantSlug: string): string {
    const domain = process.env.TEST_ADMIN_EMAIL_DOMAIN || 'e2e-test.local';
    return `admin-${tenantSlug}@${domain}`;
  }

  /**
   * Execute Laravel artisan command
   */
  private executeArtisan(command: string): string {
    try {
      return execSync(`cd ../backend && php artisan ${command} --env=testing`, {
        encoding: 'utf8',
        stdio: 'pipe'
      });
    } catch (error) {
      console.error(`Failed to execute artisan command: ${command}`);
      throw error;
    }
  }

  /**
   * Check if tenant exists in database
   */
  async tenantExists(slug: string): Promise<boolean> {
    try {
      const result = this.executeArtisan(`tinker --execute="echo App\\Models\\Landlord\\Tenant::where('slug', '${slug}')->exists() ? 'true' : 'false';"`);
      return result.trim() === 'true';
    } catch (error) {
      console.error(`Failed to check tenant existence: ${slug}`, error);
      return false;
    }
  }

  /**
   * Get tenant by slug
   */
  async getTenant(slug: string): Promise<any | null> {
    try {
      const result = this.executeArtisan(`tinker --execute="$tenant = App\\Models\\Landlord\\Tenant::where('slug', '${slug}')->first(); echo $tenant ? json_encode($tenant->toArray()) : 'null';"`);
      const trimmed = result.trim();
      return trimmed === 'null' ? null : JSON.parse(trimmed);
    } catch (error) {
      console.error(`Failed to get tenant: ${slug}`, error);
      return null;
    }
  }

  /**
   * Verify tenant database was created and is accessible
   */
  async verifyTenantDatabase(tenantSlug: string): Promise<boolean> {
    try {
      // First check if tenant exists
      const tenant = await this.getTenant(tenantSlug);
      if (!tenant) {
        console.error(`Tenant not found: ${tenantSlug}`);
        return false;
      }

      // Try to access tenant database by running a simple query
      const result = this.executeArtisan(`tinker --execute="
        $tenant = App\\Models\\Landlord\\Tenant::where('slug', '${tenantSlug}')->first();
        if ($tenant) {
          $tenant->run(function() {
            echo App\\Models\\User::count();
          });
        } else {
          echo 'tenant_not_found';
        }
      "`);

      const trimmed = result.trim();
      return trimmed !== 'tenant_not_found' && !isNaN(parseInt(trimmed));
    } catch (error) {
      console.error(`Failed to verify tenant database: ${tenantSlug}`, error);
      return false;
    }
  }

  /**
   * Verify tenant admin user was created
   */
  async verifyTenantAdmin(tenantSlug: string, adminEmail: string): Promise<boolean> {
    try {
      const result = this.executeArtisan(`tinker --execute="
        $tenant = App\\Models\\Landlord\\Tenant::where('slug', '${tenantSlug}')->first();
        if ($tenant) {
          $tenant->run(function() {
            $user = App\\Models\\User::where('email', '${adminEmail}')->first();
            echo $user ? 'found' : 'not_found';
          });
        } else {
          echo 'tenant_not_found';
        }
      "`);

      return result.trim() === 'found';
    } catch (error) {
      console.error(`Failed to verify tenant admin: ${tenantSlug}, ${adminEmail}`, error);
      return false;
    }
  }

  /**
   * Clean up a specific test tenant
   */
  async cleanupTenant(tenantSlug: string): Promise<void> {
    try {
      console.log(`🗑️  Cleaning up tenant: ${tenantSlug}`);
      
      // Delete tenant (this should cascade to tenant database via Stancl events)
      this.executeArtisan(`tinker --execute="
        $tenant = App\\Models\\Landlord\\Tenant::where('slug', '${tenantSlug}')->first();
        if ($tenant) {
          $tenant->delete();
          echo 'deleted';
        } else {
          echo 'not_found';
        }
      "`);

      // Remove from our tracking list
      this.createdTenants = this.createdTenants.filter(slug => slug !== tenantSlug);
      
    } catch (error) {
      console.error(`Failed to cleanup tenant: ${tenantSlug}`, error);
    }
  }

  /**
   * Clean up all test data
   */
  async cleanupAllTestData(): Promise<void> {
    try {
      console.log('🗑️  Cleaning up all test tenants...');
      
      // Clean up tracked tenants
      for (const tenantSlug of this.createdTenants) {
        await this.cleanupTenant(tenantSlug);
      }

      // Clean up any remaining test tenants that match our prefix
      this.executeArtisan(`tinker --execute="
        App\\Models\\Landlord\\Tenant::where('slug', 'like', '${this.testTenantPrefix}%')->delete();
        echo 'cleanup_complete';
      "`);

      this.createdTenants = [];
      
    } catch (error) {
      console.error('Failed to cleanup all test data', error);
    }
  }

  /**
   * Close database connections
   */
  async close(): Promise<void> {
    // Laravel will handle connection cleanup
  }
}
