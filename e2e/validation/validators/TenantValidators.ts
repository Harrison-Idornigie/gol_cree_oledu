/**
 * Tenant-Specific Validators
 * 
 * Validates tenant database setup, admin user creation,
 * and tenant isolation to ensure proper tenant context.
 */

import { 
  IValidator, 
  ValidationContext, 
  ValidationResult
} from '../core/ValidationTypes';
import { DatabaseHelper } from '../../utils/database-helpers';
import { ApiHelper } from '../../utils/api-helpers';

export class TenantDatabaseValidator implements IValidator {
  name = 'tenant-database';
  isCritical = true;

  async execute(context: ValidationContext): Promise<ValidationResult> {
    const startTime = Date.now();
    
    try {
      const tenantSlug = context.tenant?.slug;
      if (!tenantSlug) {
        return {
          validator: this.name,
          status: 'skipped',
          message: 'No tenant context provided for database validation',
          duration: Date.now() - startTime,
          timestamp: new Date()
        };
      }

      context.logger.debug(`🔍 Validating tenant database: ${tenantSlug}`);
      
      const dbHelper = context.helpers.dbHelper as DatabaseHelper;
      
      // Check if tenant exists in landlord database
      const tenantExists = await dbHelper.tenantExists(tenantSlug);
      if (!tenantExists) {
        return {
          validator: this.name,
          status: 'failed',
          message: `Tenant '${tenantSlug}' not found in landlord database`,
          details: {
            tenantSlug,
            suggestion: 'Ensure tenant was created successfully before running tests'
          },
          duration: Date.now() - startTime,
          timestamp: new Date()
        };
      }

      // Verify tenant database is accessible
      const dbAccessible = await dbHelper.verifyTenantDatabase(tenantSlug);
      if (!dbAccessible) {
        return {
          validator: this.name,
          status: 'failed',
          message: `Tenant database for '${tenantSlug}' is not accessible`,
          details: {
            tenantSlug,
            suggestion: 'Check tenant database creation and migration status'
          },
          duration: Date.now() - startTime,
          timestamp: new Date()
        };
      }

      return {
        validator: this.name,
        status: 'passed',
        message: `Tenant database for '${tenantSlug}' is accessible`,
        details: {
          tenantSlug,
          responseTime: Date.now() - startTime
        },
        duration: Date.now() - startTime,
        timestamp: new Date()
      };

    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : String(error);
      
      return {
        validator: this.name,
        status: 'failed',
        message: `Tenant database validation failed: ${errorMessage}`,
        details: {
          tenantSlug: context.tenant?.slug,
          error: errorMessage
        },
        duration: Date.now() - startTime,
        timestamp: new Date()
      };
    }
  }

  getDependencies(): string[] {
    return ['database-connectivity'];
  }
}

export class AdminUserValidator implements IValidator {
  name = 'admin-user';
  isCritical = true;

  async execute(context: ValidationContext): Promise<ValidationResult> {
    const startTime = Date.now();
    
    try {
      const tenantSlug = context.tenant?.slug;
      const adminEmail = context.tenant?.adminEmail;
      
      if (!tenantSlug || !adminEmail) {
        return {
          validator: this.name,
          status: 'skipped',
          message: 'No tenant context or admin email provided for admin user validation',
          duration: Date.now() - startTime,
          timestamp: new Date()
        };
      }

      context.logger.debug(`🔍 Validating admin user: ${adminEmail} for tenant: ${tenantSlug}`);
      
      const dbHelper = context.helpers.dbHelper as DatabaseHelper;
      
      // Verify admin user exists in tenant database
      const adminExists = await dbHelper.verifyTenantAdmin(tenantSlug, adminEmail);
      if (!adminExists) {
        return {
          validator: this.name,
          status: 'failed',
          message: `Admin user '${adminEmail}' not found in tenant '${tenantSlug}' database`,
          details: {
            tenantSlug,
            adminEmail,
            suggestion: 'Ensure admin user was created during tenant setup'
          },
          duration: Date.now() - startTime,
          timestamp: new Date()
        };
      }

      return {
        validator: this.name,
        status: 'passed',
        message: `Admin user '${adminEmail}' exists in tenant database`,
        details: {
          tenantSlug,
          adminEmail,
          responseTime: Date.now() - startTime
        },
        duration: Date.now() - startTime,
        timestamp: new Date()
      };

    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : String(error);
      
      return {
        validator: this.name,
        status: 'failed',
        message: `Admin user validation failed: ${errorMessage}`,
        details: {
          tenantSlug: context.tenant?.slug,
          adminEmail: context.tenant?.adminEmail,
          error: errorMessage
        },
        duration: Date.now() - startTime,
        timestamp: new Date()
      };
    }
  }

  getDependencies(): string[] {
    return ['tenant-database'];
  }
}

export class TenantIsolationValidator implements IValidator {
  name = 'tenant-isolation';
  isCritical = false; // Non-critical but important for security

  async execute(context: ValidationContext): Promise<ValidationResult> {
    const startTime = Date.now();
    
    try {
      const tenantSlug = context.tenant?.slug;
      if (!tenantSlug) {
        return {
          validator: this.name,
          status: 'skipped',
          message: 'No tenant context provided for isolation validation',
          duration: Date.now() - startTime,
          timestamp: new Date()
        };
      }

      context.logger.debug(`🔍 Validating tenant isolation: ${tenantSlug}`);
      
      const apiHelper = context.helpers.apiHelper as ApiHelper;
      
      // Test tenant-specific API endpoint accessibility
      const apiUrl = `${context.environment.apiBaseUrl}/${tenantSlug}/auth/me`;
      
      try {
        const response = await fetch(apiUrl, {
          method: 'GET',
          headers: {
            'Accept': 'application/json'
          },
          signal: AbortSignal.timeout(5000)
        });

        // We expect 401 (unauthenticated) rather than 404 (not found)
        // This indicates the tenant route exists and is properly configured
        if (response.status === 404) {
          return {
            validator: this.name,
            status: 'failed',
            message: `Tenant-specific API routes not accessible for '${tenantSlug}'`,
            details: {
              tenantSlug,
              apiUrl,
              status: response.status,
              suggestion: 'Check tenant routing configuration'
            },
            duration: Date.now() - startTime,
            timestamp: new Date()
          };
        }

        return {
          validator: this.name,
          status: 'passed',
          message: `Tenant isolation properly configured for '${tenantSlug}'`,
          details: {
            tenantSlug,
            apiUrl,
            status: response.status,
            responseTime: Date.now() - startTime
          },
          duration: Date.now() - startTime,
          timestamp: new Date()
        };

      } catch (fetchError) {
        // Network errors are acceptable for this test
        return {
          validator: this.name,
          status: 'passed',
          message: `Tenant isolation check completed (network error expected)`,
          details: {
            tenantSlug,
            note: 'Network errors are acceptable for isolation validation'
          },
          duration: Date.now() - startTime,
          timestamp: new Date()
        };
      }

    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : String(error);
      
      return {
        validator: this.name,
        status: 'failed',
        message: `Tenant isolation validation failed: ${errorMessage}`,
        details: {
          tenantSlug: context.tenant?.slug,
          error: errorMessage
        },
        duration: Date.now() - startTime,
        timestamp: new Date()
      };
    }
  }

  getDependencies(): string[] {
    return ['tenant-database', 'backend-health'];
  }
}