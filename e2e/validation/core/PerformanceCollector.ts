/**
 * Performance Collector
 * 
 * Collects performance metrics during validation processes, including timing data,
 * memory usage, database query performance, and generates comprehensive reports.
 */

import { ValidationLogger } from '../logging/ValidationLogger';
import { ValidationResult, ValidationContext } from './ValidationTypes';

export interface PerformanceMetric {
  name: string;
  value: number;
  unit: 'ms' | 'bytes' | 'count' | 'percentage';
  timestamp: Date;
  category: 'timing' | 'memory' | 'database' | 'network' | 'custom';
  metadata?: any;
}

export interface PhasePerformance {
  phase: string;
  startTime: Date;
  endTime?: Date;
  duration: number;
  memoryStart: number;
  memoryEnd: number;
  memoryDelta: number;
  metrics: PerformanceMetric[];
  validatorMetrics: Map<string, ValidatorPerformance>;
}

export interface ValidatorPerformance {
  validator: string;
  startTime: Date;
  endTime?: Date;
  duration: number;
  memoryBefore: number;
  memoryAfter: number;
  memoryDelta: number;
  queries?: DatabaseQueryMetric[];
  networkCalls?: NetworkCallMetric[];
  customMetrics: PerformanceMetric[];
}

export interface DatabaseQueryMetric {
  query: string;
  duration: number;
  timestamp: Date;
  affectedRows?: number;
  connection?: string;
}

export interface NetworkCallMetric {
  url: string;
  method: string;
  duration: number;
  statusCode: number;
  responseSize: number;
  timestamp: Date;
}

export interface PerformanceReport {
  sessionId: string;
  tenantSlug?: string;
  startTime: Date;
  endTime: Date;
  totalDuration: number;
  phases: PhasePerformance[];
  summary: PerformanceSummary;
  thresholds: PerformanceThresholds;
  warnings: PerformanceWarning[];
  recommendations: string[];
}

export interface PerformanceSummary {
  totalValidators: number;
  fastestValidator: { name: string; duration: number };
  slowestValidator: { name: string; duration: number };
  averageValidatorDuration: number;
  totalMemoryUsed: number;
  peakMemoryUsage: number;
  totalDatabaseQueries: number;
  totalNetworkRequests: number;
  performanceScore: number; // 0-100
}

export interface PerformanceThresholds {
  maxValidatorDuration: number;
  maxTotalDuration: number;
  maxMemoryUsage: number;
  maxDatabaseQueries: number;
  maxNetworkRequests: number;
}

export interface PerformanceWarning {
  type: 'slow_validator' | 'high_memory' | 'too_many_queries' | 'slow_network' | 'custom';
  message: string;
  severity: 'low' | 'medium' | 'high' | 'critical';
  metric: PerformanceMetric;
  suggestion?: string;
}

export interface PerformanceConfig {
  enabled: boolean;
  collectMemory: boolean;
  collectDatabase: boolean;
  collectNetwork: boolean;
  thresholds: PerformanceThresholds;
  reportFormat: 'json' | 'html' | 'console';
  outputPath?: string;
}

export class PerformanceCollector {
  private logger: ValidationLogger;
  private config: PerformanceConfig;
  private currentSession: string | null = null;
  private sessions: Map<string, PerformanceSession> = new Map();
  private globalMetrics: PerformanceMetric[] = [];

  constructor(logger: ValidationLogger, config: PerformanceConfig) {
    this.logger = logger;
    this.config = config;
  }

  /**
   * Start a new performance collection session
   */
  startSession(tenantSlug?: string): string {
    const sessionId = `perf_${Date.now()}_${Math.random().toString(36).substring(2, 8)}`;
    
    const session: PerformanceSession = {
      id: sessionId,
      tenantSlug,
      startTime: new Date(),
      phases: [],
      currentPhase: null,
      currentValidator: null,
      metrics: []
    };

    this.sessions.set(sessionId, session);
    this.currentSession = sessionId;
    
    this.logger.debug(`📊 Started performance collection session: ${sessionId}`);
    
    return sessionId;
  }

  /**
   * Start tracking a validation phase
   */
  startPhase(phase: string): void {
    if (!this.config.enabled || !this.currentSession) return;

    const session = this.sessions.get(this.currentSession);
    if (!session) return;

    const phasePerf: PhasePerformance = {
      phase,
      startTime: new Date(),
      duration: 0,
      memoryStart: this.getMemoryUsage(),
      memoryEnd: 0,
      memoryDelta: 0,
      metrics: [],
      validatorMetrics: new Map()
    };

    session.phases.push(phasePerf);
    session.currentPhase = phasePerf;
    
    this.logger.debug(`⏱️ Started phase performance tracking: ${phase}`);
  }

  /**
   * End tracking a validation phase
   */
  endPhase(): void {
    if (!this.config.enabled || !this.currentSession) return;

    const session = this.sessions.get(this.currentSession);
    if (!session || !session.currentPhase) return;

    const phase = session.currentPhase;
    const endTime = new Date();
    const memoryEnd = this.getMemoryUsage();

    phase.endTime = endTime;
    phase.duration = endTime.getTime() - phase.startTime.getTime();
    phase.memoryEnd = memoryEnd;
    phase.memoryDelta = memoryEnd - phase.memoryStart;

    session.currentPhase = null;
    
    this.logger.debug(`✅ Ended phase performance tracking: ${phase.phase} (${phase.duration}ms)`);
  }

  /**
   * Start tracking validator performance
   */
  startValidator(validatorName: string): void {
    if (!this.config.enabled || !this.currentSession) return;

    const session = this.sessions.get(this.currentSession);
    if (!session || !session.currentPhase) return;

    const validatorPerf: ValidatorPerformance = {
      validator: validatorName,
      startTime: new Date(),
      duration: 0,
      memoryBefore: this.getMemoryUsage(),
      memoryAfter: 0,
      memoryDelta: 0,
      queries: [],
      networkCalls: [],
      customMetrics: []
    };

    session.currentPhase.validatorMetrics.set(validatorName, validatorPerf);
    session.currentValidator = validatorPerf;
    
    this.logger.debug(`🔍 Started validator performance tracking: ${validatorName}`);
  }

  /**
   * End tracking validator performance
   */
  endValidator(result: ValidationResult): void {
    if (!this.config.enabled || !this.currentSession) return;

    const session = this.sessions.get(this.currentSession);
    if (!session || !session.currentValidator) return;

    const validator = session.currentValidator;
    const endTime = new Date();
    const memoryAfter = this.getMemoryUsage();

    validator.endTime = endTime;
    validator.duration = result.duration;
    validator.memoryAfter = memoryAfter;
    validator.memoryDelta = memoryAfter - validator.memoryBefore;

    session.currentValidator = null;
    
    // Check for performance warnings
    this.checkValidatorThresholds(validator);
    
    this.logger.debug(`✅ Ended validator performance tracking: ${validator.validator} (${validator.duration}ms)`);
  }

  /**
   * Record a custom performance metric
   */
  recordMetric(
    name: string,
    value: number,
    unit: PerformanceMetric['unit'],
    category: PerformanceMetric['category'] = 'custom',
    metadata?: any
  ): void {
    if (!this.config.enabled) return;

    const metric: PerformanceMetric = {
      name,
      value,
      unit,
      timestamp: new Date(),
      category,
      metadata
    };

    // Add to global metrics
    this.globalMetrics.push(metric);

    // Add to current session if active
    if (this.currentSession) {
      const session = this.sessions.get(this.currentSession);
      if (session) {
        session.metrics.push(metric);
        
        // Add to current validator if active
        if (session.currentValidator) {
          session.currentValidator.customMetrics.push(metric);
        }
        
        // Add to current phase if active
        if (session.currentPhase) {
          session.currentPhase.metrics.push(metric);
        }
      }
    }

    this.logger.debug(`📈 Recorded metric: ${name} = ${value}${unit}`);
  }

  /**
   * Record database query performance
   */
  recordDatabaseQuery(query: string, duration: number, affectedRows?: number, connection?: string): void {
    if (!this.config.enabled || !this.config.collectDatabase) return;

    const queryMetric: DatabaseQueryMetric = {
      query: query.substring(0, 200), // Truncate long queries
      duration,
      timestamp: new Date(),
      affectedRows,
      connection
    };

    // Add to current validator if active
    if (this.currentSession) {
      const session = this.sessions.get(this.currentSession);
      if (session?.currentValidator) {
        if (!session.currentValidator.queries) {
          session.currentValidator.queries = [];
        }
        session.currentValidator.queries.push(queryMetric);
      }
    }

    // Record as a general metric
    this.recordMetric('database_query', duration, 'ms', 'database', queryMetric);
  }

  /**
   * Record network call performance
   */
  recordNetworkCall(
    url: string,
    method: string,
    duration: number,
    statusCode: number,
    responseSize: number
  ): void {
    if (!this.config.enabled || !this.config.collectNetwork) return;

    const networkMetric: NetworkCallMetric = {
      url,
      method,
      duration,
      statusCode,
      responseSize,
      timestamp: new Date()
    };

    // Add to current validator if active
    if (this.currentSession) {
      const session = this.sessions.get(this.currentSession);
      if (session?.currentValidator) {
        if (!session.currentValidator.networkCalls) {
          session.currentValidator.networkCalls = [];
        }
        session.currentValidator.networkCalls.push(networkMetric);
      }
    }

    // Record as a general metric
    this.recordMetric('network_call', duration, 'ms', 'network', networkMetric);
  }

  /**
   * End session and generate report
   */
  endSession(): PerformanceReport | null {
    if (!this.config.enabled || !this.currentSession) return null;

    const session = this.sessions.get(this.currentSession);
    if (!session) return null;

    session.endTime = new Date();
    
    const report = this.generateReport(session);
    
    // Output report
    this.outputReport(report);
    
    this.currentSession = null;
    
    this.logger.info(`📊 Performance collection completed: ${report.sessionId}`);
    
    return report;
  }

  /**
   * Generate comprehensive performance report
   */
  private generateReport(session: PerformanceSession): PerformanceReport {
    const totalDuration = session.endTime!.getTime() - session.startTime.getTime();
    
    // Calculate summary statistics
    const allValidators = session.phases.flatMap(phase =>
      Array.from(phase.validatorMetrics.values())
    );
    
    // Handle empty validators array to prevent reduce errors
    const validatorDurations = allValidators.map(v => v.duration);
    const fastestValidator = allValidators.length > 0
      ? allValidators.reduce((prev, curr) => prev.duration < curr.duration ? prev : curr)
      : { validator: 'none', duration: 0 };
    const slowestValidator = allValidators.length > 0
      ? allValidators.reduce((prev, curr) => prev.duration > curr.duration ? prev : curr)
      : { validator: 'none', duration: 0 };
    
    const summary: PerformanceSummary = {
      totalValidators: allValidators.length,
      fastestValidator: { name: fastestValidator.validator, duration: fastestValidator.duration },
      slowestValidator: { name: slowestValidator.validator, duration: slowestValidator.duration },
      averageValidatorDuration: validatorDurations.length > 0
        ? validatorDurations.reduce((a, b) => a + b, 0) / validatorDurations.length
        : 0,
      totalMemoryUsed: Math.max(...session.phases.map(p => p.memoryDelta)),
      peakMemoryUsage: Math.max(...session.phases.map(p => p.memoryEnd)),
      totalDatabaseQueries: allValidators.reduce((total, v) => total + (v.queries?.length || 0), 0),
      totalNetworkRequests: allValidators.reduce((total, v) => total + (v.networkCalls?.length || 0), 0),
      performanceScore: this.calculatePerformanceScore(session, totalDuration)
    };

    const warnings = this.generateWarnings(session);
    const recommendations = this.generateRecommendations(session, summary);

    return {
      sessionId: session.id,
      tenantSlug: session.tenantSlug,
      startTime: session.startTime,
      endTime: session.endTime!,
      totalDuration,
      phases: session.phases,
      summary,
      thresholds: this.config.thresholds,
      warnings,
      recommendations
    };
  }

  /**
   * Calculate overall performance score (0-100)
   */
  private calculatePerformanceScore(session: PerformanceSession, totalDuration: number): number {
    let score = 100;
    
    // Deduct points for slow performance
    if (totalDuration > this.config.thresholds.maxTotalDuration) {
      score -= 20;
    }
    
    // Deduct points for slow validators
    const slowValidators = session.phases.flatMap(phase => 
      Array.from(phase.validatorMetrics.values())
    ).filter(v => v.duration > this.config.thresholds.maxValidatorDuration);
    
    score -= slowValidators.length * 5;
    
    // Deduct points for high memory usage
    const maxMemory = Math.max(...session.phases.map(p => p.memoryEnd));
    if (maxMemory > this.config.thresholds.maxMemoryUsage) {
      score -= 15;
    }
    
    // Deduct points for too many queries
    const totalQueries = session.phases.flatMap(phase => 
      Array.from(phase.validatorMetrics.values())
    ).reduce((total, v) => total + (v.queries?.length || 0), 0);
    
    if (totalQueries > this.config.thresholds.maxDatabaseQueries) {
      score -= 10;
    }
    
    return Math.max(0, Math.min(100, score));
  }

  /**
   * Generate performance warnings
   */
  private generateWarnings(session: PerformanceSession): PerformanceWarning[] {
    const warnings: PerformanceWarning[] = [];
    
    session.phases.forEach(phase => {
      phase.validatorMetrics.forEach(validator => {
        if (validator.duration > this.config.thresholds.maxValidatorDuration) {
          warnings.push({
            type: 'slow_validator',
            message: `Validator '${validator.validator}' took ${validator.duration}ms (threshold: ${this.config.thresholds.maxValidatorDuration}ms)`,
            severity: validator.duration > this.config.thresholds.maxValidatorDuration * 2 ? 'high' : 'medium',
            metric: {
              name: 'validator_duration',
              value: validator.duration,
              unit: 'ms',
              timestamp: validator.startTime,
              category: 'timing'
            },
            suggestion: 'Consider optimizing this validator or increasing the timeout threshold'
          });
        }
        
        if (validator.memoryDelta > this.config.thresholds.maxMemoryUsage * 0.1) {
          warnings.push({
            type: 'high_memory',
            message: `Validator '${validator.validator}' used ${Math.round(validator.memoryDelta / 1024 / 1024)}MB memory`,
            severity: 'medium',
            metric: {
              name: 'memory_usage',
              value: validator.memoryDelta,
              unit: 'bytes',
              timestamp: validator.startTime,
              category: 'memory'
            },
            suggestion: 'Monitor memory usage and consider memory optimization'
          });
        }
      });
    });
    
    return warnings;
  }

  /**
   * Generate performance recommendations
   */
  private generateRecommendations(session: PerformanceSession, summary: PerformanceSummary): string[] {
    const recommendations: string[] = [];
    
    if (summary.performanceScore < 70) {
      recommendations.push('Overall performance is below optimal. Consider reviewing slow validators and system resources.');
    }
    
    if (summary.totalDatabaseQueries > this.config.thresholds.maxDatabaseQueries) {
      recommendations.push('High number of database queries detected. Consider query optimization or caching.');
    }
    
    if (summary.slowestValidator.duration > this.config.thresholds.maxValidatorDuration) {
      recommendations.push(`The slowest validator (${summary.slowestValidator.name}) may need optimization.`);
    }
    
    if (summary.peakMemoryUsage > this.config.thresholds.maxMemoryUsage) {
      recommendations.push('Peak memory usage is high. Consider memory optimization strategies.');
    }
    
    return recommendations;
  }

  /**
   * Check validator performance against thresholds
   */
  private checkValidatorThresholds(validator: ValidatorPerformance): void {
    if (validator.duration > this.config.thresholds.maxValidatorDuration) {
      this.logger.warn(`⚠️ Slow validator detected: ${validator.validator} (${validator.duration}ms)`);
    }
    
    if (validator.memoryDelta > this.config.thresholds.maxMemoryUsage * 0.1) {
      this.logger.warn(`⚠️ High memory usage: ${validator.validator} (${Math.round(validator.memoryDelta / 1024 / 1024)}MB)`);
    }
  }

  /**
   * Output performance report
   */
  private outputReport(report: PerformanceReport): void {
    switch (this.config.reportFormat) {
      case 'console':
        this.outputConsoleReport(report);
        break;
      case 'json':
        this.outputJsonReport(report);
        break;
      case 'html':
        this.outputHtmlReport(report);
        break;
    }
  }

  /**
   * Output console report
   */
  private outputConsoleReport(report: PerformanceReport): void {
    this.logger.info('📊 Performance Report');
    this.logger.info(`Session: ${report.sessionId}`);
    this.logger.info(`Total Duration: ${report.totalDuration}ms`);
    this.logger.info(`Performance Score: ${report.summary.performanceScore}/100`);
    this.logger.info(`Validators: ${report.summary.totalValidators}`);
    this.logger.info(`Fastest: ${report.summary.fastestValidator.name} (${report.summary.fastestValidator.duration}ms)`);
    this.logger.info(`Slowest: ${report.summary.slowestValidator.name} (${report.summary.slowestValidator.duration}ms)`);
    
    if (report.warnings.length > 0) {
      this.logger.warn(`⚠️ ${report.warnings.length} performance warnings`);
    }
  }

  /**
   * Output JSON report
   */
  private outputJsonReport(report: PerformanceReport): void {
    if (this.config.outputPath) {
      // Would write to file system in real implementation
      this.logger.debug(`📄 JSON report would be written to: ${this.config.outputPath}`);
    }
  }

  /**
   * Output HTML report
   */
  private outputHtmlReport(report: PerformanceReport): void {
    if (this.config.outputPath) {
      // Would generate HTML report in real implementation
      this.logger.debug(`📄 HTML report would be written to: ${this.config.outputPath}`);
    }
  }

  /**
   * Get current memory usage
   */
  private getMemoryUsage(): number {
    if (!this.config.collectMemory) return 0;
    
    try {
      return process.memoryUsage().heapUsed;
    } catch {
      return 0;
    }
  }

  /**
   * Clean up old sessions
   */
  cleanupOldSessions(): void {
    const now = Date.now();
    const maxAge = 3600000; // 1 hour
    
    for (const [sessionId, session] of this.sessions) {
      const sessionAge = now - session.startTime.getTime();
      if (sessionAge > maxAge) {
        this.sessions.delete(sessionId);
        this.logger.debug(`🧹 Cleaned up old performance session: ${sessionId}`);
      }
    }
  }
}

interface PerformanceSession {
  id: string;
  tenantSlug?: string;
  startTime: Date;
  endTime?: Date;
  phases: PhasePerformance[];
  currentPhase: PhasePerformance | null;
  currentValidator: ValidatorPerformance | null;
  metrics: PerformanceMetric[];
}

/**
 * Factory function to create PerformanceCollector with default config
 */
export function createPerformanceCollector(
  logger: ValidationLogger,
  config?: Partial<PerformanceConfig>
): PerformanceCollector {
  const defaultConfig: PerformanceConfig = {
    enabled: true,
    collectMemory: true,
    collectDatabase: true,
    collectNetwork: true,
    thresholds: {
      maxValidatorDuration: 5000, // 5 seconds
      maxTotalDuration: 30000, // 30 seconds
      maxMemoryUsage: 100 * 1024 * 1024, // 100MB
      maxDatabaseQueries: 50,
      maxNetworkRequests: 20
    },
    reportFormat: 'console'
  };

  const finalConfig = config ? { ...defaultConfig, ...config } : defaultConfig;
  return new PerformanceCollector(logger, finalConfig);
}