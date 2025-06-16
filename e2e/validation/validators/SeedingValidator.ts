/**
 * Basic Seeding Validator
 * 
 * Validates that essential tenant data has been properly seeded
 * including languages, roles, and permissions with minimum counts.
 */

import { 
  IValidator, 
  ValidationContext, 
  ValidationResult,
  TableValidationResult,
  SeedingValidationResult
} from '../core/ValidationTypes';
import { DatabaseHelper } from '../../utils/database-helpers';

export class BasicSeedingValidator implements IValidator {
  name = 'basic-seeding-validation';
  isCritical = true;

  async execute(context: ValidationContext): Promise<ValidationResult> {
    const startTime = Date.now();
    
    try {
      const tenantSlug = context.tenant?.slug;
      if (!tenantSlug) {
        throw new Error('No tenant context provided for seeding validation');
      }

      context.logger.debug(`🌱 Validating seeding for tenant: ${tenantSlug}`);

      const dbHelper = context.helpers.dbHelper as DatabaseHelper;
      const seedingConfig = context.config.phases.seeding;
      
      // Validate required tables and minimum record counts
      const validationResult = await this.validateTenantSeeding(
        dbHelper, 
        tenantSlug, 
        seedingConfig.requiredTables,
        seedingConfig.minRecordCounts
      );

      if (!validationResult.overallStatus || validationResult.overallStatus === 'failed') {
        const failedTables = Array.from(validationResult.tables.values())
          .filter(table => !table.passed);

        return {
          validator: this.name,
          status: 'failed',
          message: `Insufficient seed data in ${failedTables.length} table(s): ${failedTables.map(t => t.table).join(', ')}`,
          details: {
            failedTables: failedTables.map(t => ({
              table: t.table,
              required: t.required,
              actual: t.actual,
              error: t.error
            })),
            summary: {
              totalTables: validationResult.totalTables,
              passedTables: validationResult.passedTables,
              failedTables: validationResult.failedTables
            }
          },
          duration: Date.now() - startTime,
          timestamp: new Date()
        };
      }

      return {
        validator: this.name,
        status: 'passed',
        message: `All ${validationResult.totalTables} required tables have sufficient seed data`,
        details: {
          validatedTables: Array.from(validationResult.tables.values()).map(t => ({
            table: t.table,
            required: t.required,
            actual: t.actual
          })),
          summary: {
            totalTables: validationResult.totalTables,
            passedTables: validationResult.passedTables,
            failedTables: validationResult.failedTables
          }
        },
        duration: Date.now() - startTime,
        timestamp: new Date()
      };
      
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : String(error);
      const errorStack = error instanceof Error ? error.stack : String(error);
      
      return {
        validator: this.name,
        status: 'failed',
        message: `Seeding validation error: ${errorMessage}`,
        details: { error: errorStack },
        duration: Date.now() - startTime,
        timestamp: new Date()
      };
    }
  }

  getDependencies(): string[] {
    return ['tenant-database', 'admin-user'];
  }

  private async validateTenantSeeding(
    dbHelper: DatabaseHelper,
    tenantSlug: string,
    requiredTables: string[],
    minRecordCounts: Record<string, number>
  ): Promise<SeedingValidationResult> {
    const result: SeedingValidationResult = {
      tables: new Map(),
      overallStatus: 'passed',
      totalTables: requiredTables.length,
      passedTables: 0,
      failedTables: 0
    };

    for (const table of requiredTables) {
      try {
        const actualCount = await this.getTenantTableCount(dbHelper, tenantSlug, table);
        const requiredCount = minRecordCounts[table] || 1;
        
        const tableResult: TableValidationResult = {
          table,
          required: requiredCount,
          actual: actualCount,
          passed: actualCount >= requiredCount
        };

        result.tables.set(table, tableResult);
        
        if (tableResult.passed) {
          result.passedTables++;
        } else {
          result.failedTables++;
        }

      } catch (error) {
        const errorMessage = error instanceof Error ? error.message : String(error);
        
        const tableResult: TableValidationResult = {
          table,
          required: minRecordCounts[table] || 1,
          actual: 0,
          passed: false,
          error: errorMessage
        };

        result.tables.set(table, tableResult);
        result.failedTables++;
      }
    }

    result.overallStatus = result.failedTables > 0 ? 'failed' : 'passed';
    return result;
  }

  private async getTenantTableCount(dbHelper: DatabaseHelper, tenantSlug: string, table: string): Promise<number> {
    try {
      // Use the existing database helper pattern from your tests
      const modelName = this.getModelName(table);
      
      const result = await this.executeInTenantContext(dbHelper, tenantSlug, modelName);
      return parseInt(result.trim()) || 0;
      
    } catch (error) {
      // If the specific model doesn't exist, try generic table count
      return this.getGenericTableCount(dbHelper, tenantSlug, table);
    }
  }

  private async executeInTenantContext(dbHelper: DatabaseHelper, tenantSlug: string, modelName: string): Promise<string> {
    // Use the same pattern as your existing DatabaseHelper
    const command = `tinker --execute="\\$tenant = App\\\\Models\\\\Landlord\\\\Tenant::where('slug', '${tenantSlug}')->first(); if (\\$tenant) { \\$tenant->run(function() { echo App\\\\Models\\\\Tenants\\\\${modelName}::count(); }); } else { echo '0'; }"`;
    
    return (dbHelper as any).executeArtisan(command);
  }

  private async getGenericTableCount(dbHelper: DatabaseHelper, tenantSlug: string, table: string): Promise<number> {
    try {
      // Fallback to generic table count query
      const command = `tinker --execute="\\$tenant = App\\\\Models\\\\Landlord\\\\Tenant::where('slug', '${tenantSlug}')->first(); if (\\$tenant) { \\$tenant->run(function() { echo DB::table('${table}')->count(); }); } else { echo '0'; }"`;
      
      const result = await (dbHelper as any).executeArtisan(command);
      return parseInt(result.trim()) || 0;
    } catch (error) {
      throw new Error(`Failed to count records in table '${table}': ${error instanceof Error ? error.message : String(error)}`);
    }
  }

  private getModelName(table: string): string {
    // Convert table name to model name (e.g., 'languages' -> 'Language')
    const modelMap: Record<string, string> = {
      'languages': 'Language',
      'roles': 'Role', 
      'permissions': 'Permission',
      'users': 'User',
      'learning_paths': 'LearningPath',
      'units': 'Unit',
      'lessons': 'Lesson',
      'exercises': 'Exercise'
    };

    return modelMap[table] || this.capitalizeFirst(this.singularize(table));
  }

  private capitalizeFirst(str: string): string {
    return str.charAt(0).toUpperCase() + str.slice(1);
  }

  private singularize(word: string): string {
    // Simple singularization - can be enhanced
    if (word.endsWith('ies')) {
      return word.slice(0, -3) + 'y';
    }
    if (word.endsWith('s')) {
      return word.slice(0, -1);
    }
    return word;
  }
}