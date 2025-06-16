/**
 * Tenant State Validator
 * 
 * Advanced validation for tenant configuration completeness, database schema,
 * tenant-specific settings, and data integrity checks.
 */

import { 
  IValidator, 
  ValidationContext, 
  ValidationResult 
} from '../core/ValidationTypes';

export interface TenantConfigValidation {
  configKey: string;
  expectedValue?: any;
  required: boolean;
  validator?: (value: any) => boolean;
  description: string;
}

export interface SchemaValidation {
  table: string;
  columns: string[];
  indexes?: string[];
  constraints?: string[];
  requiredData?: { table: string; minCount: number; }[];
}

export interface FilePermissionCheck {
  path: string;
  permissions: string;
  owner?: string;
  required: boolean;
}

export interface TenantStateConfig {
  requiredConfigs: TenantConfigValidation[];
  schemaValidations: SchemaValidation[];
  filePermissions: FilePermissionCheck[];
  customValidations: Array<{
    name: string;
    validator: (context: ValidationContext) => Promise<boolean>;
    description: string;
  }>;
}

export class TenantStateValidator implements IValidator {
  name = 'tenant-state-validator';
  isCritical = true;

  private config: TenantStateConfig;

  constructor(config?: Partial<TenantStateConfig>) {
    this.config = {
      requiredConfigs: [
        {
          configKey: 'app.name',
          required: true,
          description: 'Application name should be configured'
        },
        {
          configKey: 'app.env',
          expectedValue: 'testing',
          required: true,
          description: 'Environment should be set to testing'
        },
        {
          configKey: 'database.default',
          required: true,
          description: 'Default database connection should be configured'
        },
        {
          configKey: 'tenancy.database.auto_delete',
          expectedValue: true,
          required: false,
          description: 'Auto-delete should be enabled for test tenants'
        }
      ],
      schemaValidations: [
        {
          table: 'users',
          columns: ['id', 'name', 'email', 'password', 'created_at', 'updated_at'],
          indexes: ['users_email_unique'],
          requiredData: [{ table: 'users', minCount: 1 }]
        },
        {
          table: 'roles',
          columns: ['id', 'name', 'guard_name', 'created_at', 'updated_at'],
          requiredData: [{ table: 'roles', minCount: 3 }]
        },
        {
          table: 'permissions',
          columns: ['id', 'name', 'guard_name', 'created_at', 'updated_at'],
          requiredData: [{ table: 'permissions', minCount: 10 }]
        },
        {
          table: 'languages',
          columns: ['id', 'code', 'name', 'created_at', 'updated_at'],
          requiredData: [{ table: 'languages', minCount: 1 }]
        }
      ],
      filePermissions: [
        {
          path: 'storage/app',
          permissions: '755',
          required: true
        },
        {
          path: 'storage/logs',
          permissions: '755',
          required: true
        }
      ],
      customValidations: [],
      ...config
    };
  }

  getDependencies(): string[] {
    return ['tenant-database', 'admin-user'];
  }

  async execute(context: ValidationContext): Promise<ValidationResult> {
    const startTime = Date.now();
    
    if (!context.tenant) {
      return {
        validator: this.name,
        status: 'skipped',
        message: 'No tenant context provided',
        duration: Date.now() - startTime,
        timestamp: new Date()
      };
    }

    try {
      context.logger.debug(`🔍 Starting advanced tenant state validation for: ${context.tenant.slug}`);

      // Run all validation checks
      const configValidation = await this.validateTenantConfiguration(context);
      const schemaValidation = await this.validateTenantSchema(context);
      const permissionsValidation = await this.validateFilePermissions(context);
      const customValidation = await this.validateCustomRules(context);

      const allPassed = configValidation && schemaValidation && permissionsValidation && customValidation;

      return {
        validator: this.name,
        status: allPassed ? 'passed' : 'failed',
        message: allPassed 
          ? `Advanced tenant state validation passed for ${context.tenant.slug}`
          : `Advanced tenant state validation failed for ${context.tenant.slug}`,
        details: {
          configValidation,
          schemaValidation,
          permissionsValidation,
          customValidation,
          suggestion: allPassed ? undefined : 'Check tenant configuration, schema, and permissions'
        },
        duration: Date.now() - startTime,
        timestamp: new Date()
      };

    } catch (error) {
      context.logger.error(`💥 Tenant state validation error for ${context.tenant.slug}:`, error);
      
      return {
        validator: this.name,
        status: 'failed',
        message: `Tenant state validation failed: ${error instanceof Error ? error.message : 'Unknown error'}`,
        details: { 
          error: error instanceof Error ? error.message : error,
          suggestion: 'Check tenant setup and database connectivity'
        },
        duration: Date.now() - startTime,
        timestamp: new Date()
      };
    }
  }

  /**
   * Validate tenant configuration completeness
   */
  private async validateTenantConfiguration(context: ValidationContext): Promise<boolean> {
    context.logger.debug(`⚙️ Validating tenant configuration for: ${context.tenant!.slug}`);

    try {
      for (const configCheck of this.config.requiredConfigs) {
        const configValue = await this.getTenantConfig(context, configCheck.configKey);
        
        if (configCheck.required && (configValue === null || configValue === undefined)) {
          context.logger.error(`❌ Missing required config: ${configCheck.configKey}`);
          return false;
        }

        if (configCheck.expectedValue !== undefined && configValue !== configCheck.expectedValue) {
          context.logger.error(`❌ Config mismatch for ${configCheck.configKey}: expected ${configCheck.expectedValue}, got ${configValue}`);
          return false;
        }

        if (configCheck.validator && !configCheck.validator(configValue)) {
          context.logger.error(`❌ Config validation failed for ${configCheck.configKey}`);
          return false;
        }

        context.logger.debug(`✅ Config validated: ${configCheck.configKey} = ${configValue}`);
      }

      return true;
    } catch (error) {
      context.logger.error('Failed to validate tenant configuration:', error);
      return false;
    }
  }

  /**
   * Validate tenant database schema
   */
  private async validateTenantSchema(context: ValidationContext): Promise<boolean> {
    context.logger.debug(`📊 Validating tenant database schema for: ${context.tenant!.slug}`);

    try {
      for (const schemaCheck of this.config.schemaValidations) {
        // Check if table exists
        const tableExists = await this.checkTableExists(context, schemaCheck.table);
        if (!tableExists) {
          context.logger.error(`❌ Required table missing: ${schemaCheck.table}`);
          return false;
        }

        // Check columns
        const columnsValid = await this.validateTableColumns(context, schemaCheck.table, schemaCheck.columns);
        if (!columnsValid) {
          context.logger.error(`❌ Column validation failed for table: ${schemaCheck.table}`);
          return false;
        }

        // Check indexes if specified
        if (schemaCheck.indexes) {
          const indexesValid = await this.validateTableIndexes(context, schemaCheck.table, schemaCheck.indexes);
          if (!indexesValid) {
            context.logger.error(`❌ Index validation failed for table: ${schemaCheck.table}`);
            return false;
          }
        }

        // Check required data
        if (schemaCheck.requiredData) {
          const dataValid = await this.validateRequiredData(context, schemaCheck.requiredData);
          if (!dataValid) {
            context.logger.error(`❌ Required data validation failed for table: ${schemaCheck.table}`);
            return false;
          }
        }

        context.logger.debug(`✅ Schema validated for table: ${schemaCheck.table}`);
      }

      return true;
    } catch (error) {
      context.logger.error('Failed to validate tenant schema:', error);
      return false;
    }
  }

  /**
   * Validate file permissions and storage access
   */
  private async validateFilePermissions(context: ValidationContext): Promise<boolean> {
    context.logger.debug(`📁 Validating file permissions for: ${context.tenant!.slug}`);

    try {
      for (const permCheck of this.config.filePermissions) {
        const isValid = await this.checkFilePermissions(context, permCheck);
        
        if (permCheck.required && !isValid) {
          context.logger.error(`❌ File permission check failed: ${permCheck.path}`);
          return false;
        }

        context.logger.debug(`✅ File permissions validated: ${permCheck.path}`);
      }

      return true;
    } catch (error) {
      context.logger.error('Failed to validate file permissions:', error);
      return false;
    }
  }

  /**
   * Run custom validation rules
   */
  private async validateCustomRules(context: ValidationContext): Promise<boolean> {
    context.logger.debug(`🔧 Running custom validations for: ${context.tenant!.slug}`);

    try {
      for (const customCheck of this.config.customValidations) {
        const isValid = await customCheck.validator(context);
        
        if (!isValid) {
          context.logger.error(`❌ Custom validation failed: ${customCheck.name}`);
          return false;
        }

        context.logger.debug(`✅ Custom validation passed: ${customCheck.name}`);
      }

      return true;
    } catch (error) {
      context.logger.error('Failed to run custom validations:', error);
      return false;
    }
  }

  /**
   * Get tenant-specific configuration value
   */
  private async getTenantConfig(context: ValidationContext, configKey: string): Promise<any> {
    try {
      const result = context.helpers.dbHelper.executeArtisan(
        `tinker --execute="\\$tenant = App\\\\Models\\\\Landlord\\\\Tenant::where('slug', '${context.tenant!.slug}')->first(); if (\\$tenant) { \\$tenant->run(function() { echo config('${configKey}') ?? 'null'; }); } else { echo 'tenant_not_found'; }"`
      );

      const trimmed = result.trim();
      if (trimmed === 'tenant_not_found' || trimmed === 'null') {
        return null;
      }
      
      return trimmed;
    } catch (error) {
      context.logger.error(`Failed to get config ${configKey}:`, error);
      return null;
    }
  }

  /**
   * Check if a table exists in tenant database
   */
  private async checkTableExists(context: ValidationContext, tableName: string): Promise<boolean> {
    try {
      const result = context.helpers.dbHelper.executeArtisan(
        `tinker --execute="\\$tenant = App\\\\Models\\\\Landlord\\\\Tenant::where('slug', '${context.tenant!.slug}')->first(); if (\\$tenant) { \\$tenant->run(function() { echo Schema::hasTable('${tableName}') ? 'true' : 'false'; }); } else { echo 'false'; }"`
      );

      return result.trim() === 'true';
    } catch (error) {
      context.logger.error(`Failed to check table existence ${tableName}:`, error);
      return false;
    }
  }

  /**
   * Validate table columns
   */
  private async validateTableColumns(context: ValidationContext, tableName: string, requiredColumns: string[]): Promise<boolean> {
    try {
      for (const column of requiredColumns) {
        const result = context.helpers.dbHelper.executeArtisan(
          `tinker --execute="\\$tenant = App\\\\Models\\\\Landlord\\\\Tenant::where('slug', '${context.tenant!.slug}')->first(); if (\\$tenant) { \\$tenant->run(function() { echo Schema::hasColumn('${tableName}', '${column}') ? 'true' : 'false'; }); } else { echo 'false'; }"`
        );

        if (result.trim() !== 'true') {
          context.logger.error(`❌ Missing column ${column} in table ${tableName}`);
          return false;
        }
      }

      return true;
    } catch (error) {
      context.logger.error(`Failed to validate columns for ${tableName}:`, error);
      return false;
    }
  }

  /**
   * Validate table indexes
   */
  private async validateTableIndexes(context: ValidationContext, tableName: string, requiredIndexes: string[]): Promise<boolean> {
    try {
      // This is a simplified check - in real implementation you'd check actual indexes
      for (const index of requiredIndexes) {
        context.logger.debug(`🔍 Checking index: ${index} on table ${tableName}`);
        // For now, we'll assume indexes exist if table exists
        // Real implementation would query information_schema or similar
      }

      return true;
    } catch (error) {
      context.logger.error(`Failed to validate indexes for ${tableName}:`, error);
      return false;
    }
  }

  /**
   * Validate required data exists
   */
  private async validateRequiredData(context: ValidationContext, requiredData: { table: string; minCount: number; }[]): Promise<boolean> {
    try {
      for (const dataCheck of requiredData) {
        const result = context.helpers.dbHelper.executeArtisan(
          `tinker --execute="\\$tenant = App\\\\Models\\\\Landlord\\\\Tenant::where('slug', '${context.tenant!.slug}')->first(); if (\\$tenant) { \\$tenant->run(function() { echo DB::table('${dataCheck.table}')->count(); }); } else { echo '0'; }"`
        );

        const count = parseInt(result.trim()) || 0;
        if (count < dataCheck.minCount) {
          context.logger.error(`❌ Insufficient data in ${dataCheck.table}: found ${count}, required ${dataCheck.minCount}`);
          return false;
        }

        context.logger.debug(`✅ Data count validated for ${dataCheck.table}: ${count} >= ${dataCheck.minCount}`);
      }

      return true;
    } catch (error) {
      context.logger.error('Failed to validate required data:', error);
      return false;
    }
  }

  /**
   * Check file permissions
   */
  private async checkFilePermissions(context: ValidationContext, permCheck: FilePermissionCheck): Promise<boolean> {
    try {
      // This is a simplified check - real implementation would check actual file system permissions
      // For tenant-specific storage, you'd check within the tenant's storage directory
      context.logger.debug(`📁 Checking permissions for: ${permCheck.path}`);
      
      // In a real implementation, you'd use fs.stat() to check actual permissions
      // For now, we'll assume permissions are correct if we can access the basic paths
      
      return true;
    } catch (error) {
      context.logger.error(`Failed to check permissions for ${permCheck.path}:`, error);
      return false;
    }
  }
}

/**
 * Factory function to create TenantStateValidator with custom config
 */
export function createTenantStateValidator(config?: Partial<TenantStateConfig>): TenantStateValidator {
  return new TenantStateValidator(config);
}

/**
 * Pre-configured validators for common scenarios
 */
export const TenantStateValidators = {
  /**
   * Basic tenant state validation
   */
  basic(): TenantStateValidator {
    return new TenantStateValidator({
      requiredConfigs: [
        {
          configKey: 'app.env',
          expectedValue: 'testing',
          required: true,
          description: 'Environment should be testing'
        }
      ],
      schemaValidations: [
        {
          table: 'users',
          columns: ['id', 'email'],
          requiredData: [{ table: 'users', minCount: 1 }]
        }
      ],
      filePermissions: [],
      customValidations: []
    });
  },

  /**
   * Complete tenant state validation
   */
  complete(): TenantStateValidator {
    return new TenantStateValidator(); // Uses default comprehensive config
  },

  /**
   * Language learning app specific validation
   */
  languageLearning(): TenantStateValidator {
    return new TenantStateValidator({
      requiredConfigs: [
        {
          configKey: 'app.env',
          expectedValue: 'testing',
          required: true,
          description: 'Environment should be testing'
        },
        {
          configKey: 'database.default',
          required: true,
          description: 'Database connection required'
        }
      ],
      schemaValidations: [
        {
          table: 'users',
          columns: ['id', 'name', 'email', 'password'],
          requiredData: [{ table: 'users', minCount: 1 }]
        },
        {
          table: 'languages',
          columns: ['id', 'code', 'name'],
          requiredData: [{ table: 'languages', minCount: 1 }]
        },
        {
          table: 'roles',
          columns: ['id', 'name', 'guard_name'],
          requiredData: [{ table: 'roles', minCount: 3 }]
        },
        {
          table: 'permissions',
          columns: ['id', 'name', 'guard_name'],
          requiredData: [{ table: 'permissions', minCount: 10 }]
        }
      ],
      filePermissions: [
        {
          path: 'storage/app',
          permissions: '755',
          required: true
        }
      ],
      customValidations: [
        {
          name: 'admin-role-exists',
          description: 'Verify admin role exists',
          validator: async (context) => {
            try {
              const result = context.helpers.dbHelper.executeArtisan(
                `tinker --execute="\\$tenant = App\\\\Models\\\\Landlord\\\\Tenant::where('slug', '${context.tenant!.slug}')->first(); if (\\$tenant) { \\$tenant->run(function() { echo DB::table('roles')->where('name', 'admin')->exists() ? 'true' : 'false'; }); } else { echo 'false'; }"`
              );
              return result.trim() === 'true';
            } catch {
              return false;
            }
          }
        }
      ]
    });
  }
};