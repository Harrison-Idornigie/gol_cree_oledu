/**
 * Validation Manager
 *
 * Central orchestrator for the pre-test validation system.
 * Manages validator registration, context creation, and pipeline execution.
 * Enhanced with rollback, job monitoring, performance collection, and advanced tenant state validation.
 */

import {
  ValidationContext,
  ValidationConfig,
  EnvironmentConfig,
  TenantInfo,
  ValidationHelpers
} from './ValidationTypes';
import { ValidationPipeline } from './ValidationPipeline';
import { ValidationResults } from './ValidationResults';
import { ValidationLogger } from '../logging/ValidationLogger';

// Import enhanced components
import { TenantRollbackManager, createTenantRollbackManager } from './TenantRollbackManager';
import { JobMonitor, createJobMonitor } from './JobMonitor';
import { PerformanceCollector, createPerformanceCollector } from './PerformanceCollector';

// Import validators
import { BasicSeedingValidator } from '../validators/SeedingValidator';
import {
  BackendHealthValidator,
  FrontendHealthValidator,
  DatabaseConnectivityValidator
} from '../validators/EnvironmentValidators';
import {
  TenantDatabaseValidator,
  AdminUserValidator,
  TenantIsolationValidator
} from '../validators/TenantValidators';
import { TenantStateValidator, TenantStateValidators } from '../validators/TenantStateValidator';

export class ValidationManager {
  private pipeline: ValidationPipeline;
  private logger: ValidationLogger;
  private rollbackManager?: TenantRollbackManager;
  private jobMonitor?: JobMonitor;
  private performanceCollector?: PerformanceCollector;

  constructor(private config: ValidationConfig) {
    this.logger = new ValidationLogger(config.logging);
    this.pipeline = new ValidationPipeline(config);
    this.initializeEnhancedComponents();
    this.registerValidators();
  }

  /**
   * Initialize enhanced components based on configuration
   */
  private initializeEnhancedComponents(): void {
    // Initialize rollback manager if enabled
    if (this.config.rollback?.enabled) {
      // Will be initialized with actual dbHelper when needed
      this.logger.debug('🔄 Rollback manager will be initialized on first use');
    }

    // Initialize job monitor if enabled
    if (this.config.jobMonitoring?.enabled) {
      this.logger.debug('🔍 Job monitor will be initialized on first use');
    }

    // Initialize performance collector if enabled
    if (this.config.performance?.enabled) {
      this.performanceCollector = createPerformanceCollector(this.logger, this.config.performance);
      this.logger.debug('📊 Performance collector initialized');
    }
  }

  /**
   * Execute validation pipeline for a specific tenant
   */
  async validateTenant(
    tenant: TenantInfo,
    environment: EnvironmentConfig,
    helpers: ValidationHelpers
  ): Promise<ValidationResults> {
    const context = this.createValidationContext(tenant, environment, helpers);
    
    // Initialize components that need helpers
    this.initializeHelperDependentComponents(helpers);
    
    // Start performance monitoring if enabled
    let performanceSessionId: string | undefined;
    if (this.performanceCollector) {
      performanceSessionId = this.performanceCollector.startSession(tenant.slug);
      this.performanceCollector.startPhase('tenant-validation');
    }

    // Create rollback snapshot if enabled
    if (this.rollbackManager && this.config.rollback?.enabled) {
      this.rollbackManager.createSnapshot(tenant.slug);
      this.rollbackManager.addRollbackStep(
        tenant.slug,
        'tenant_creation',
        `Validation for tenant ${tenant.slug}`,
        async () => {
          await helpers.dbHelper.cleanupTenant(tenant.slug);
        }
      );
    }
    
    this.logger.info(`🚀 Starting enhanced validation for tenant: ${tenant.slug}`);
    
    try {
      // Start job monitoring if enabled
      if (this.jobMonitor && this.config.jobMonitoring?.trackTenantJobs) {
        // Monitor tenant creation jobs in parallel
        this.jobMonitor.monitorTenantCreation(tenant.slug).catch(error => {
          this.logger.warn(`Job monitoring failed for ${tenant.slug}:`, error);
        });
      }

      const results = await this.pipeline.execute(context);
      
      if (results.summary.success) {
        this.logger.info(`✅ Validation completed successfully for tenant: ${tenant.slug}`);
        
        // Clear rollback snapshot if preserveOnSuccess is false
        if (this.rollbackManager && !this.config.rollback?.preserveOnSuccess) {
          this.rollbackManager.clearAllSnapshots();
        }
      } else {
        this.logger.error(`❌ Validation failed for tenant: ${tenant.slug}`);
        this.logFailureDetails(results);
        
        // Auto-rollback if enabled
        if (this.rollbackManager && this.config.rollback?.autoRollbackOnFailure) {
          await this.rollbackManager.autoRollbackOnFailure(
            tenant.slug,
            new Error('Validation failed')
          );
        }
      }
      
      return results;
    } catch (error) {
      this.logger.error(`💥 Validation pipeline error for tenant ${tenant.slug}:`, error);
      
      // Auto-rollback on error if enabled
      if (this.rollbackManager && this.config.rollback?.autoRollbackOnFailure) {
        await this.rollbackManager.autoRollbackOnFailure(
          tenant.slug,
          error instanceof Error ? error : new Error('Unknown validation error')
        );
      }
      
      throw error;
    } finally {
      // End performance monitoring if enabled
      if (this.performanceCollector && performanceSessionId) {
        this.performanceCollector.endPhase();
        const report = this.performanceCollector.endSession();
        
        if (report && report.warnings.length > 0) {
          this.logger.warn(`⚠️ Performance warnings for ${tenant.slug}:`);
          report.warnings.forEach(warning => {
            this.logger.warn(`  - ${warning.message}`);
          });
        }
      }
    }
  }

  /**
   * Execute environment-only validation (no tenant context)
   */
  async validateEnvironment(
    environment: EnvironmentConfig,
    helpers: ValidationHelpers
  ): Promise<ValidationResults> {
    const context = this.createValidationContext(undefined, environment, helpers);
    
    this.logger.info('🚀 Starting environment validation');
    
    try {
      const results = await this.pipeline.execute(context);
      
      if (results.summary.success) {
        this.logger.info('✅ Environment validation completed successfully');
      } else {
        this.logger.error('❌ Environment validation failed');
        this.logFailureDetails(results);
      }
      
      return results;
    } catch (error) {
      this.logger.error('💥 Environment validation pipeline error:', error);
      throw error;
    }
  }

  /**
   * Check if validation should be skipped
   */
  shouldSkipValidation(): boolean {
    if (this.config.skipValidationOnEnvVar && process.env[this.config.skipValidationOnEnvVar]) {
      this.logger.info(`⏭️ Skipping validation due to environment variable: ${this.config.skipValidationOnEnvVar}`);
      return true;
    }
    return false;
  }

  /**
   * Get list of registered validators
   */
  getRegisteredValidators(): string[] {
    return this.pipeline.getRegisteredValidators();
  }

  /**
   * Create validation context
   */
  private createValidationContext(
    tenant: TenantInfo | undefined,
    environment: EnvironmentConfig,
    helpers: ValidationHelpers
  ): ValidationContext {
    return {
      tenant,
      environment,
      config: this.config,
      logger: this.logger,
      helpers
    };
  }

  /**
   * Initialize components that depend on validation helpers
   */
  private initializeHelperDependentComponents(helpers: ValidationHelpers): void {
    // Initialize rollback manager if enabled and not already initialized
    if (this.config.rollback?.enabled && !this.rollbackManager) {
      this.rollbackManager = createTenantRollbackManager(this.logger, helpers.dbHelper);
      this.logger.debug('🔄 Rollback manager initialized');
    }

    // Initialize job monitor if enabled and not already initialized
    if (this.config.jobMonitoring?.enabled && !this.jobMonitor) {
      this.jobMonitor = createJobMonitor(this.logger, helpers.dbHelper, this.config.jobMonitoring);
      this.logger.debug('🔍 Job monitor initialized');
    }
  }

  /**
   * Register all validators with the pipeline
   */
  private registerValidators(): void {
    // Environment validators
    this.pipeline.registerValidator(new BackendHealthValidator());
    this.pipeline.registerValidator(new FrontendHealthValidator());
    this.pipeline.registerValidator(new DatabaseConnectivityValidator());

    // Tenant validators
    this.pipeline.registerValidator(new TenantDatabaseValidator());
    this.pipeline.registerValidator(new AdminUserValidator());
    this.pipeline.registerValidator(new TenantIsolationValidator());

    // Enhanced tenant state validator
    if (this.config.tenantState?.enabled) {
      let tenantStateValidator: TenantStateValidator;
      
      switch (this.config.tenantState.validationLevel) {
        case 'basic':
          tenantStateValidator = TenantStateValidators.basic();
          break;
        case 'complete':
          tenantStateValidator = TenantStateValidators.complete();
          break;
        case 'custom':
          tenantStateValidator = new TenantStateValidator({
            customValidations: this.config.tenantState.customValidations || []
          });
          break;
        default:
          tenantStateValidator = TenantStateValidators.languageLearning();
      }
      
      this.pipeline.registerValidator(tenantStateValidator);
      this.logger.debug(`Registered tenant state validator: ${this.config.tenantState.validationLevel}`);
    }

    // Seeding validators
    this.pipeline.registerValidator(new BasicSeedingValidator());

    this.logger.debug(`Registered ${this.getRegisteredValidators().length} validators`);
  }

  /**
   * Log detailed failure information
   */
  private logFailureDetails(results: ValidationResults): void {
    const failures = results.getFailures();
    
    this.logger.error(`Found ${failures.length} validation failures:`);
    
    failures.forEach((failure, index) => {
      this.logger.error(`  ${index + 1}. ${failure.validator}: ${failure.message}`);
      
      if (failure.details?.suggestion) {
        this.logger.info(`     💡 Suggestion: ${failure.details.suggestion}`);
      }
    });

    // Log performance insights
    const performance = results.getPerformanceData();
    if (performance.slowestValidators.length > 0) {
      this.logger.warn('⚡ Slowest validators:');
      performance.slowestValidators.slice(0, 3).forEach(validator => {
        this.logger.warn(`  - ${validator.validator}: ${validator.duration}ms`);
      });
    }
  }
}

/**
 * Factory function to create ValidationManager with default config
 */
export function createValidationManager(config?: Partial<ValidationConfig>): ValidationManager {
  const { getValidationConfig } = require('../../config/validation.config');
  const defaultConfig = getValidationConfig();
  
  const finalConfig = config ? { ...defaultConfig, ...config } : defaultConfig;
  
  return new ValidationManager(finalConfig);
}

/**
 * Utility function to create environment config from process.env
 */
export function createEnvironmentConfig(): EnvironmentConfig {
  return {
    name: process.env.NODE_ENV || 'test',
    backendUrl: process.env.BACKEND_URL || 'http://localhost:8000',
    frontendUrl: process.env.FRONTEND_URL || 'http://localhost:3000',
    apiBaseUrl: process.env.API_BASE_URL || 'http://localhost:8000/api',
    testMode: process.env.NODE_ENV === 'test' || process.env.APP_ENV === 'testing'
  };
}

/**
 * Utility function to create validation helpers from existing test utilities
 */
export function createValidationHelpers(
  dbHelper: any,
  apiHelper: any,
  fixtures: any
): ValidationHelpers {
  return {
    dbHelper,
    apiHelper,
    fixtures
  };
}