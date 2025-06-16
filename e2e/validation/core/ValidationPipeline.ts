/**
 * Validation Pipeline Implementation
 * 
 * Orchestrates the execution of validation phases and individual validators
 * with support for fail-fast behavior and progress tracking.
 */

import { 
  IValidator,
  ValidationContext,
  ValidationConfig,
  ValidationLogger as IValidationLogger
} from './ValidationTypes';
import { ValidationResults, PhaseResult } from './ValidationResults';
import { ValidationLogger } from '../logging/ValidationLogger';

export class ValidationPipeline {
  private validators: Map<string, IValidator> = new Map();
  private phases: ValidationPhase[] = [];
  private logger: IValidationLogger;

  constructor(private config: ValidationConfig) {
    this.logger = new ValidationLogger(config.logging);
    this.initializeValidators();
    this.buildPhases();
  }

  async execute(context: ValidationContext): Promise<ValidationResults> {
    const results = new ValidationResults();
    
    // Check if validation should be skipped
    if (this.shouldSkipValidation()) {
      this.logger.info('⏭️ Skipping validation due to environment variable');
      results.complete();
      return results;
    }

    this.logger.logValidationStart(context);

    try {
      for (const phase of this.phases) {
        if (!phase.config.enabled) {
          this.logger.info(`⏭️ Skipping disabled phase: ${phase.name}`);
          continue;
        }

        this.logger.info(`🔍 Starting validation phase: ${phase.name}`);
        
        const phaseResult = await this.executePhase(phase, context);
        results.addPhaseResult(phase.name, phaseResult);

        if (this.config.failFast && phaseResult.hasCriticalFailures()) {
          this.logger.error(`❌ Critical failure in phase: ${phase.name}, stopping validation`);
          break;
        }
      }
    } catch (error) {
      this.logger.error('❌ Validation pipeline error:', error);
      const errorMessage = error instanceof Error ? error.message : String(error);
      results.setFailed(`Pipeline execution failed: ${errorMessage}`);
    } finally {
      results.complete();
      this.logger.logValidationComplete(results);
    }

    return results;
  }

  private async executePhase(phase: ValidationPhase, context: ValidationContext): Promise<PhaseResult> {
    const phaseResult = new PhaseResult(phase.name);
    const phaseTimeout = setTimeout(() => {
      throw new Error(`Phase ${phase.name} exceeded timeout of ${phase.config.timeout}ms`);
    }, phase.config.timeout);

    try {
      for (const validatorName of phase.validators) {
        const validator = this.validators.get(validatorName);
        if (!validator) {
          this.logger.warn(`⚠️ Validator not found: ${validatorName}`);
          continue;
        }

        const startTime = Date.now();
        this.logger.debug(`  Running validator: ${validatorName}`);
        
        try {
          const result = await validator.execute(context);
          result.phase = phase.name;
          
          phaseResult.addResult(result);
          this.logger.logValidationResult(result);

          // Check for critical failures and fail fast if configured
          if (this.config.failFast && result.status === 'failed' && validator.isCritical) {
            this.logger.error(`💥 Critical validator failed: ${validatorName}`);
            break;
          }
        } catch (error) {
          const errorMessage = error instanceof Error ? error.message : String(error);
          const errorStack = error instanceof Error ? error.stack : String(error);
          
          const result = {
            validator: validatorName,
            status: 'failed' as const,
            message: `Validator execution failed: ${errorMessage}`,
            details: { error: errorStack },
            duration: Date.now() - startTime,
            timestamp: new Date(),
            phase: phase.name
          };
          
          phaseResult.addResult(result);
          this.logger.logValidationResult(result);

          if (this.config.failFast && validator.isCritical) {
            break;
          }
        }
      }
    } finally {
      clearTimeout(phaseTimeout);
      phaseResult.complete();
    }

    return phaseResult;
  }

  private initializeValidators(): void {
    // Import and register validators
    const validators = this.getValidatorInstances();
    
    for (const validator of validators) {
      this.validators.set(validator.name, validator);
    }
  }

  private buildPhases(): void {
    this.phases = [
      new ValidationPhase('environment', this.config.phases.environment, [
        'backend-health',
        'frontend-health', 
        'database-connectivity'
      ]),
      new ValidationPhase('database', this.config.phases.database, [
        'schema-validation',
        'migration-status',
        'cleanup-orphaned'
      ]),
      new ValidationPhase('tenant', this.config.phases.tenant, [
        'tenant-database',
        'admin-user',
        'tenant-isolation'
      ]),
      new ValidationPhase('seeding', this.config.phases.seeding, [
        'basic-seeding-validation'
      ])
    ];
  }

  private getValidatorInstances(): IValidator[] {
    // Import validator classes dynamically to avoid circular dependencies
    // For now, return empty array - validators will be implemented separately
    return [];
  }

  private shouldSkipValidation(): boolean {
    return !!(this.config.skipValidationOnEnvVar && 
              process.env[this.config.skipValidationOnEnvVar]);
  }

  // Method to register validators dynamically
  registerValidator(validator: IValidator): void {
    this.validators.set(validator.name, validator);
  }

  // Method to get validation status
  getRegisteredValidators(): string[] {
    return Array.from(this.validators.keys());
  }
}

class ValidationPhase {
  constructor(
    public name: string,
    public config: { enabled: boolean; timeout: number },
    public validators: string[]
  ) {}
}

export { ValidationPhase };