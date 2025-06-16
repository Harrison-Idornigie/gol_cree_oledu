/**
 * Validation Logger Implementation
 * 
 * Provides structured logging for the validation pipeline with
 * support for multiple output formats and metrics collection.
 */

import {
  ValidationLogger as IValidationLogger,
  ValidationContext,
  ValidationResult,
  ValidationResults as IValidationResults,
  LoggingConfig,
  PerformanceData,
  ValidationReport
} from '../core/ValidationTypes';
import { ValidationResults } from '../core/ValidationResults';

export class ValidationLogger implements IValidationLogger {
  private metrics: ValidationMetrics;
  private outputs: LogOutput[] = [];
  private logEntries: LogEntry[] = [];

  constructor(private config: LoggingConfig) {
    this.metrics = new ValidationMetrics();
    this.initializeOutputs();
  }

  debug(message: string, context?: any): void {
    if (this.config.level === 'debug') {
      this.writeLog('debug', message, context);
    }
  }

  info(message: string, context?: any): void {
    if (['debug', 'info'].includes(this.config.level)) {
      this.writeLog('info', message, context);
    }
  }

  warn(message: string, context?: any): void {
    if (['debug', 'info', 'warn'].includes(this.config.level)) {
      this.writeLog('warn', message, context);
    }
  }

  error(message: string, context?: any): void {
    this.writeLog('error', message, context);
  }

  logValidationStart(context: ValidationContext): void {
    const entry = {
      level: 'info' as const,
      message: '🚀 Starting pre-test validation pipeline',
      timestamp: new Date().toISOString(),
      context: {
        tenant: context.tenant?.slug,
        environment: context.environment.name,
        enabledPhases: Object.entries(context.config.phases)
          .filter(([_, phase]) => phase.enabled)
          .map(([name]) => name),
        failFast: context.config.failFast
      }
    };

    this.writeLogEntry(entry);
    
    if (this.config.metricsEnabled) {
      this.metrics.recordValidationStart(entry);
    }
  }

  logValidationResult(result: ValidationResult): void {
    const icon = this.getStatusIcon(result.status);
    const entry = {
      level: result.status === 'failed' ? 'error' as const : 'info' as const,
      message: `${icon} ${result.validator}: ${result.message}`,
      timestamp: result.timestamp.toISOString(),
      context: {
        validator: result.validator,
        status: result.status,
        duration: `${result.duration}ms`,
        phase: result.phase,
        details: result.details
      }
    };

    this.writeLogEntry(entry);
    
    if (this.config.metricsEnabled) {
      this.metrics.recordValidationResult(result);
    }
  }

  logValidationComplete(results: ValidationResults): void {
    const summary = results.summary;
    const icon = summary.success ? '✅' : '❌';
    
    const entry = {
      level: summary.success ? 'info' as const : 'error' as const,
      message: `${icon} Pre-test validation ${summary.success ? 'completed successfully' : 'failed'}`,
      timestamp: new Date().toISOString(),
      context: {
        summary: {
          total: summary.totalValidators,
          passed: summary.passed,
          failed: summary.failed,
          skipped: summary.skipped,
          duration: `${summary.duration}ms`,
          success: summary.success
        },
        phases: results.getPhaseResults().map(phase => ({
          name: phase.name,
          status: phase.status,
          duration: `${phase.duration}ms`,
          validators: phase.results.length
        }))
      }
    };

    this.writeLogEntry(entry);

    // Log failures with details
    if (!summary.success) {
      const failures = results.getFailures();
      this.error('Validation failures detected:', {
        failureCount: failures.length,
        failures: failures.map(f => ({
          validator: f.validator,
          message: f.message,
          phase: f.phase
        }))
      });
    }

    if (this.config.metricsEnabled) {
      this.metrics.recordValidationComplete(results);
    }
  }

  generateReport(results: ValidationResults): ValidationReport {
    return {
      summary: results.summary,
      phases: results.getPhaseResults(),
      failures: results.getFailures(),
      performance: this.metrics.getPerformanceData(),
      recommendations: this.generateRecommendations(results),
      timestamp: new Date()
    };
  }

  private writeLog(level: string, message: string, context?: any): void {
    const entry: LogEntry = {
      level: level as LogLevel,
      message,
      timestamp: new Date().toISOString(),
      context
    };
    
    this.writeLogEntry(entry);
  }

  private writeLogEntry(entry: LogEntry): void {
    this.logEntries.push(entry);
    
    for (const output of this.outputs) {
      output.write(entry);
    }
  }

  private initializeOutputs(): void {
    this.outputs = [];

    if (this.config.outputs.includes('console')) {
      this.outputs.push(new ConsoleLogOutput());
    }

    if (this.config.outputs.includes('file')) {
      this.outputs.push(new FileLogOutput('validation.log'));
    }

    if (this.config.outputs.includes('json')) {
      this.outputs.push(new JsonLogOutput('validation-results.json'));
    }

    if (this.config.outputs.includes('html')) {
      this.outputs.push(new HtmlLogOutput('validation-report.html'));
    }
  }

  private getStatusIcon(status: string): string {
    switch (status) {
      case 'passed': return '✅';
      case 'failed': return '❌';
      case 'skipped': return '⏭️';
      default: return '❓';
    }
  }

  private generateRecommendations(results: ValidationResults): string[] {
    const recommendations: string[] = [];
    const failures = results.getFailures();
    const performance = this.metrics.getPerformanceData();

    // Analyze failures and suggest fixes
    failures.forEach(failure => {
      if (failure.validator === 'basic-seeding-validation') {
        recommendations.push('Run database seeding: php artisan db:seed --env=testing');
      }
      if (failure.validator === 'backend-health') {
        recommendations.push('Start Laravel server: cd backend && php artisan serve');
      }
      if (failure.validator === 'frontend-health') {
        recommendations.push('Start frontend server: cd frontend && npm run dev');
      }
      if (failure.validator === 'database-connectivity') {
        recommendations.push('Check database configuration in .env.testing');
      }
    });

    // Performance recommendations
    if (performance.totalDuration > 30000) {
      recommendations.push('Validation is taking longer than expected. Consider optimizing database queries or increasing timeouts.');
    }

    if (performance.slowestValidators.length > 0) {
      const slowest = performance.slowestValidators[0];
      if (slowest.duration > 10000) {
        recommendations.push(`${slowest.validator} is particularly slow (${slowest.duration}ms). Consider optimizing this validator.`);
      }
    }

    return recommendations;
  }
}

class ValidationMetrics {
  private startTime?: Date;
  private validationResults: ValidationResult[] = [];
  private performanceData?: PerformanceData;

  recordValidationStart(entry: LogEntry): void {
    this.startTime = new Date();
  }

  recordValidationResult(result: ValidationResult): void {
    this.validationResults.push(result);
  }

  recordValidationComplete(results: ValidationResults): void {
    this.performanceData = results.getPerformanceData();
  }

  getPerformanceData(): PerformanceData {
    if (this.performanceData) {
      return this.performanceData;
    }

    // Calculate basic performance data
    const totalDuration = this.validationResults.reduce((sum, r) => sum + r.duration, 0);
    const averageValidatorDuration = this.validationResults.length > 0 
      ? totalDuration / this.validationResults.length 
      : 0;

    const slowestValidators = this.validationResults
      .sort((a, b) => b.duration - a.duration)
      .slice(0, 5)
      .map(r => ({
        validator: r.validator,
        duration: r.duration
      }));

    return {
      totalDuration,
      averageValidatorDuration,
      slowestValidators,
      phaseBreakdown: []
    };
  }
}

// Log output implementations
interface LogOutput {
  write(entry: LogEntry): void;
}

interface LogEntry {
  level: LogLevel;
  message: string;
  timestamp: string;
  context?: any;
}

type LogLevel = 'debug' | 'info' | 'warn' | 'error';

class ConsoleLogOutput implements LogOutput {
  write(entry: LogEntry): void {
    const color = this.getColor(entry.level);
    const timestamp = new Date(entry.timestamp).toLocaleTimeString();
    
    console.log(`${color}[${timestamp}] ${entry.level.toUpperCase()}: ${entry.message}\x1b[0m`);
    
    if (entry.context && Object.keys(entry.context).length > 0) {
      console.log('  Context:', entry.context);
    }
  }

  private getColor(level: LogLevel): string {
    switch (level) {
      case 'debug': return '\x1b[36m'; // Cyan
      case 'info': return '\x1b[32m';  // Green
      case 'warn': return '\x1b[33m';  // Yellow
      case 'error': return '\x1b[31m'; // Red
      default: return '\x1b[0m';       // Reset
    }
  }
}

class FileLogOutput implements LogOutput {
  constructor(private filename: string) {}

  write(entry: LogEntry): void {
    // In a real implementation, this would write to a file
    // For now, we'll just store in memory or skip
  }
}

class JsonLogOutput implements LogOutput {
  private entries: LogEntry[] = [];

  constructor(private filename: string) {}

  write(entry: LogEntry): void {
    this.entries.push(entry);
    // In a real implementation, this would write to a JSON file
  }
}

class HtmlLogOutput implements LogOutput {
  private entries: LogEntry[] = [];

  constructor(private filename: string) {}

  write(entry: LogEntry): void {
    this.entries.push(entry);
    // In a real implementation, this would generate an HTML report
  }
}