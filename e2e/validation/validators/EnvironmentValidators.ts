/**
 * Environment Health Validators
 * 
 * Validates that backend and frontend services are accessible
 * and database connectivity is working properly.
 */

import { 
  IValidator, 
  ValidationContext, 
  ValidationResult
} from '../core/ValidationTypes';

export class BackendHealthValidator implements IValidator {
  name = 'backend-health';
  isCritical = true;

  async execute(context: ValidationContext): Promise<ValidationResult> {
    const startTime = Date.now();
    
    try {
      const backendUrl = context.environment.backendUrl;
      context.logger.debug(`🔍 Checking backend health: ${backendUrl}`);
      
      const healthEndpoint = `${backendUrl}/up`;
      const response = await fetch(healthEndpoint, {
        method: 'GET',
        signal: AbortSignal.timeout(10000) // 10 second timeout
      });

      if (!response.ok) {
        return {
          validator: this.name,
          status: 'failed',
          message: `Backend health check failed with status ${response.status}`,
          details: {
            url: healthEndpoint,
            status: response.status,
            statusText: response.statusText
          },
          duration: Date.now() - startTime,
          timestamp: new Date()
        };
      }

      return {
        validator: this.name,
        status: 'passed',
        message: 'Backend service is accessible',
        details: {
          url: healthEndpoint,
          status: response.status,
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
        message: `Backend health check failed: ${errorMessage}`,
        details: {
          url: context.environment.backendUrl,
          error: errorMessage,
          suggestion: 'Start Laravel server: cd backend && php artisan serve'
        },
        duration: Date.now() - startTime,
        timestamp: new Date()
      };
    }
  }

  getDependencies(): string[] {
    return [];
  }
}

export class FrontendHealthValidator implements IValidator {
  name = 'frontend-health';
  isCritical = false; // Non-critical since some tests might be API-only

  async execute(context: ValidationContext): Promise<ValidationResult> {
    const startTime = Date.now();
    
    try {
      const frontendUrl = context.environment.frontendUrl;
      context.logger.debug(`🔍 Checking frontend health: ${frontendUrl}`);
      
      const response = await fetch(frontendUrl, {
        method: 'GET',
        signal: AbortSignal.timeout(10000) // 10 second timeout
      });

      if (!response.ok) {
        return {
          validator: this.name,
          status: 'failed',
          message: `Frontend health check failed with status ${response.status}`,
          details: {
            url: frontendUrl,
            status: response.status,
            statusText: response.statusText
          },
          duration: Date.now() - startTime,
          timestamp: new Date()
        };
      }

      return {
        validator: this.name,
        status: 'passed',
        message: 'Frontend service is accessible',
        details: {
          url: frontendUrl,
          status: response.status,
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
        message: `Frontend health check failed: ${errorMessage}`,
        details: {
          url: context.environment.frontendUrl,
          error: errorMessage,
          suggestion: 'Start frontend server: cd frontend && npm run dev'
        },
        duration: Date.now() - startTime,
        timestamp: new Date()
      };
    }
  }

  getDependencies(): string[] {
    return [];
  }
}

export class DatabaseConnectivityValidator implements IValidator {
  name = 'database-connectivity';
  isCritical = true;

  async execute(context: ValidationContext): Promise<ValidationResult> {
    const startTime = Date.now();
    
    try {
      context.logger.debug('🔍 Checking database connectivity');
      
      const dbHelper = context.helpers.dbHelper;
      
      // Test basic database connectivity using existing DatabaseHelper
      const testQuery = `tinker --execute="try { DB::connection()->getPdo(); echo 'connection_ok'; } catch (Exception \\$e) { echo 'connection_failed: ' . \\$e->getMessage(); }"`;
      
      const result = await dbHelper.executeArtisan(testQuery);
      
      if (!result.includes('connection_ok')) {
        return {
          validator: this.name,
          status: 'failed',
          message: 'Database connectivity test failed',
          details: {
            result: result.trim(),
            suggestion: 'Check database configuration in .env.testing'
          },
          duration: Date.now() - startTime,
          timestamp: new Date()
        };
      }

      // Test landlord database access
      const landlordTest = `tinker --execute="echo App\\\\Models\\\\Landlord\\\\Tenant::count();"`;
      const landlordResult = await dbHelper.executeArtisan(landlordTest);
      
      if (isNaN(parseInt(landlordResult.trim()))) {
        return {
          validator: this.name,
          status: 'failed',
          message: 'Landlord database access failed',
          details: {
            result: landlordResult.trim(),
            suggestion: 'Run landlord migrations: php artisan migrate:landlord --env=testing'
          },
          duration: Date.now() - startTime,
          timestamp: new Date()
        };
      }

      return {
        validator: this.name,
        status: 'passed',
        message: 'Database connectivity verified',
        details: {
          landlordTenants: parseInt(landlordResult.trim()) || 0,
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
        message: `Database connectivity check failed: ${errorMessage}`,
        details: {
          error: errorMessage,
          suggestion: 'Verify database is running and .env.testing is configured correctly'
        },
        duration: Date.now() - startTime,
        timestamp: new Date()
      };
    }
  }

  getDependencies(): string[] {
    return [];
  }
}