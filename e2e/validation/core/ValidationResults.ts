/**
 * ValidationResults Implementation
 *
 * Manages and aggregates validation results across all phases
 * and provides methods for analysis and reporting.
 */

import {
  ValidationResults as IValidationResults,
  ValidationResult,
  PhaseResult as IPhaseResult,
  ValidationSummary,
  PerformanceData
} from './ValidationTypes';

export class ValidationResults implements IValidationResults {
  public phases: Map<string, IPhaseResult> = new Map();
  public allResults: ValidationResult[] = [];
  public startTime: Date;
  public endTime?: Date;

  constructor() {
    this.startTime = new Date();
  }

  addPhaseResult(phaseName: string, phaseResult: IPhaseResult): void {
    this.phases.set(phaseName, phaseResult);
    this.allResults.push(...phaseResult.results);
  }

  addResult(result: ValidationResult): void {
    this.allResults.push(result);
  }

  complete(): void {
    this.endTime = new Date();
  }

  get summary(): ValidationSummary {
    const passed = this.allResults.filter(r => r.status === 'passed').length;
    const failed = this.allResults.filter(r => r.status === 'failed').length;
    const skipped = this.allResults.filter(r => r.status === 'skipped').length;
    const duration = this.getTotalDuration();

    return {
      totalValidators: this.allResults.length,
      passed,
      failed,
      skipped,
      duration,
      success: failed === 0
    };
  }

  hasCriticalFailures(): boolean {
    return this.allResults.some(r => r.status === 'failed');
  }

  getFailures(): ValidationResult[] {
    return this.allResults.filter(r => r.status === 'failed');
  }

  getCriticalFailures(): ValidationResult[] {
    return Array.from(this.phases.values())
      .flatMap(phase => phase.criticalFailures);
  }

  getTotalDuration(): number {
    if (!this.endTime) return 0;
    return this.endTime.getTime() - this.startTime.getTime();
  }

  getPassedCount(): number {
    return this.allResults.filter(r => r.status === 'passed').length;
  }

  getFailedCount(): number {
    return this.allResults.filter(r => r.status === 'failed').length;
  }

  getSkippedCount(): number {
    return this.allResults.filter(r => r.status === 'skipped').length;
  }

  getTotalCount(): number {
    return this.allResults.length;
  }

  getPhaseResults(): IPhaseResult[] {
    return Array.from(this.phases.values());
  }

  getStartTime(): Date {
    return this.startTime;
  }

  getEndTime(): Date | undefined {
    return this.endTime;
  }

  getPerformanceData(): PerformanceData {
    const totalDuration = this.getTotalDuration();
    const validatorDurations = this.allResults.map(r => r.duration);
    const averageValidatorDuration = validatorDurations.length > 0 
      ? validatorDurations.reduce((a, b) => a + b, 0) / validatorDurations.length 
      : 0;

    // Get slowest validators
    const slowestValidators = this.allResults
      .sort((a, b) => b.duration - a.duration)
      .slice(0, 5)
      .map(r => ({
        validator: r.validator,
        duration: r.duration
      }));

    // Calculate phase breakdown
    const phaseBreakdown = Array.from(this.phases.entries()).map(([name, phase]) => ({
      phase: name,
      duration: phase.duration,
      percentage: totalDuration > 0 ? (phase.duration / totalDuration) * 100 : 0
    }));

    return {
      totalDuration,
      averageValidatorDuration,
      slowestValidators,
      phaseBreakdown
    };
  }

  setFailed(reason: string): void {
    // Add a synthetic failure result
    this.addResult({
      validator: 'system',
      status: 'failed',
      message: reason,
      duration: 0,
      timestamp: new Date()
    });
  }
}

export class PhaseResult implements IPhaseResult {
  public results: ValidationResult[] = [];
  public criticalFailures: ValidationResult[] = [];
  public duration: number = 0;
  private startTime: Date;

  constructor(public name: string) {
    this.startTime = new Date();
  }

  addResult(result: ValidationResult): void {
    this.results.push(result);
    
    if (result.status === 'failed') {
      this.criticalFailures.push(result);
    }
  }

  complete(): void {
    this.duration = Date.now() - this.startTime.getTime();
  }

  get status(): 'passed' | 'failed' | 'partial' {
    const failed = this.results.filter(r => r.status === 'failed').length;
    const passed = this.results.filter(r => r.status === 'passed').length;
    
    if (failed > 0) {
      return passed > 0 ? 'partial' : 'failed';
    }
    
    return 'passed';
  }

  hasCriticalFailures(): boolean {
    return this.criticalFailures.length > 0;
  }
}