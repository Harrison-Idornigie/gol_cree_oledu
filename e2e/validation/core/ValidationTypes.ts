/**
 * Core Type Definitions for Pre-Test Validation System
 * 
 * Defines the foundational interfaces and types used throughout
 * the validation framework for tenant testing infrastructure.
 */

export interface IValidator {
  name: string;
  isCritical?: boolean;
  execute(context: ValidationContext): Promise<ValidationResult>;
  getDependencies(): string[];
}

export interface ValidationContext {
  tenant?: TenantInfo;
  environment: EnvironmentConfig;
  config: ValidationConfig;
  logger: ValidationLogger;
  helpers: ValidationHelpers;
}

export interface TenantInfo {
  slug: string;
  name: string;
  id?: string;
  adminEmail?: string;
  status?: string;
}

export interface EnvironmentConfig {
  name: string;
  backendUrl: string;
  frontendUrl: string;
  apiBaseUrl: string;
  testMode: boolean;
}

export interface ValidationHelpers {
  dbHelper: any; // DatabaseHelper instance
  apiHelper: any; // ApiHelper instance
  fixtures: any; // TenantFixtures instance
}

export interface ValidationResult {
  validator: string;
  status: 'passed' | 'failed' | 'skipped';
  message: string;
  details?: any;
  duration: number;
  timestamp: Date;
  phase?: string;
}

export interface ValidationConfig {
  phases: {
    environment: PhaseConfig;
    database: PhaseConfig;
    tenant: PhaseConfig;
    seeding: SeedingPhaseConfig;
  };
  async: AsyncConfig;
  logging: LoggingConfig;
  failFast: boolean;
  skipValidationOnEnvVar?: string;
  rollback?: RollbackConfig;
  jobMonitoring?: JobMonitoringConfig;
  performance?: PerformanceConfig;
  tenantState?: TenantStateConfig;
}

export interface RollbackConfig {
  enabled: boolean;
  autoRollbackOnFailure: boolean;
  preserveOnSuccess: boolean;
  customCleanupActions?: Array<() => Promise<void>>;
  skipStepTypes?: string[];
}

export interface JobMonitoringConfig {
  enabled: boolean;
  timeout: number;
  pollInterval: number;
  trackTenantJobs: boolean;
  trackSeedingJobs: boolean;
  queues: string[];
  connections: string[];
}

export interface PerformanceConfig {
  enabled: boolean;
  collectMemory: boolean;
  collectDatabase: boolean;
  collectNetwork: boolean;
  reportFormat: 'console' | 'json' | 'html';
  outputPath?: string;
  thresholds: {
    maxValidatorDuration: number;
    maxTotalDuration: number;
    maxMemoryUsage: number;
    maxDatabaseQueries: number;
    maxNetworkRequests: number;
  };
}

export interface TenantStateConfig {
  enabled: boolean;
  validationLevel: 'basic' | 'complete' | 'custom';
  checkFilePermissions: boolean;
  customValidations: Array<{
    name: string;
    validator: (context: ValidationContext) => Promise<boolean>;
    description: string;
  }>;
}

export interface PhaseConfig {
  enabled: boolean;
  validators: string[];
  timeout: number;
}

export interface SeedingPhaseConfig extends PhaseConfig {
  requiredTables: string[];
  minRecordCounts: Record<string, number>;
}

export interface AsyncConfig {
  tenantCreationTimeout: number;
  progressCheckInterval: number;
  maxRetries: number;
}

export interface LoggingConfig {
  level: 'debug' | 'info' | 'warn' | 'error';
  outputs: ('console' | 'file' | 'json' | 'html')[];
  metricsEnabled: boolean;
}

export interface ValidationResults {
  summary: ValidationSummary;
  phases: Map<string, PhaseResult>;
  allResults: ValidationResult[];
  startTime: Date;
  endTime?: Date;
}

export interface ValidationSummary {
  totalValidators: number;
  passed: number;
  failed: number;
  skipped: number;
  duration: number;
  success: boolean;
}

export interface PhaseResult {
  name: string;
  status: 'passed' | 'failed' | 'partial';
  results: ValidationResult[];
  duration: number;
  criticalFailures: ValidationResult[];
}

export interface ValidationLogger {
  debug(message: string, context?: any): void;
  info(message: string, context?: any): void;
  warn(message: string, context?: any): void;
  error(message: string, context?: any): void;
  logValidationStart(context: ValidationContext): void;
  logValidationResult(result: ValidationResult): void;
  logValidationComplete(results: ValidationResults): void;
}

export interface ValidationError extends Error {
  validator?: string;
  phase?: string;
  details?: any;
  context?: ValidationContext;
}

export interface ValidationReport {
  summary: ValidationSummary;
  phases: PhaseResult[];
  failures: ValidationResult[];
  performance: PerformanceData;
  recommendations: string[];
  timestamp: Date;
}

export interface PerformanceData {
  totalDuration: number;
  averageValidatorDuration: number;
  slowestValidators: Array<{
    validator: string;
    duration: number;
  }>;
  phaseBreakdown: Array<{
    phase: string;
    duration: number;
    percentage: number;
  }>;
}

export interface ProgressData {
  stage: string;
  message: string;
  percentage: number;
  timestamp: string;
  error?: string;
}

export interface AsyncOperation {
  id: string;
  type: string;
  status: 'pending' | 'running' | 'completed' | 'failed';
  progress: ProgressData;
  startTime: Date;
  endTime?: Date;
  result?: any;
  error?: string;
}

export interface SeedingValidationResult {
  tables: Map<string, TableValidationResult>;
  overallStatus: 'passed' | 'failed';
  totalTables: number;
  passedTables: number;
  failedTables: number;
}

export interface TableValidationResult {
  table: string;
  required: number;
  actual: number;
  passed: boolean;
  error?: string;
}

export class ValidationException extends Error {
  constructor(
    message: string,
    public validator?: string,
    public phase?: string,
    public details?: any
  ) {
    super(message);
    this.name = 'ValidationException';
  }
}