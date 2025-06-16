/**
 * Job Monitor
 * 
 * Monitors Laravel job queues during tenant creation and validation processes.
 * Tracks async operations, handles job failures, and ensures completion before proceeding.
 */

import { ValidationLogger } from '../logging/ValidationLogger';
import { DatabaseHelper } from '../../utils/database-helpers';

export interface JobInfo {
  id: string;
  queue: string;
  connection: string;
  payload: any;
  attempts: number;
  reserved_at?: Date;
  available_at: Date;
  created_at: Date;
}

export interface JobStatus {
  id: string;
  status: 'pending' | 'processing' | 'completed' | 'failed' | 'timeout';
  attempts: number;
  maxAttempts: number;
  lastError?: string;
  startTime: Date;
  endTime?: Date;
  duration?: number;
}

export interface JobMonitorConfig {
  pollInterval: number;
  timeout: number;
  maxRetries: number;
  queues: string[];
  connections: string[];
  tenantJobTypes: string[];
}

export interface MonitoringSession {
  id: string;
  tenantSlug: string;
  startTime: Date;
  endTime?: Date;
  trackedJobs: Map<string, JobStatus>;
  completed: boolean;
  failed: boolean;
}

export class JobMonitor {
  private logger: ValidationLogger;
  private dbHelper: DatabaseHelper;
  private config: JobMonitorConfig;
  private sessions: Map<string, MonitoringSession> = new Map();

  constructor(logger: ValidationLogger, dbHelper: DatabaseHelper, config: JobMonitorConfig) {
    this.logger = logger;
    this.dbHelper = dbHelper;
    this.config = config;
  }

  /**
   * Start monitoring jobs for a tenant operation
   */
  startMonitoring(tenantSlug: string): string {
    const sessionId = `${tenantSlug}_${Date.now()}_${Math.random().toString(36).substring(2, 8)}`;
    
    const session: MonitoringSession = {
      id: sessionId,
      tenantSlug,
      startTime: new Date(),
      trackedJobs: new Map(),
      completed: false,
      failed: false
    };

    this.sessions.set(sessionId, session);
    this.logger.info(`🔍 Started job monitoring session: ${sessionId} for tenant: ${tenantSlug}`);
    
    return sessionId;
  }

  /**
   * Add a job to be monitored
   */
  trackJob(sessionId: string, jobId: string, maxAttempts: number = 3): void {
    const session = this.sessions.get(sessionId);
    if (!session) {
      throw new Error(`Monitoring session not found: ${sessionId}`);
    }

    const jobStatus: JobStatus = {
      id: jobId,
      status: 'pending',
      attempts: 0,
      maxAttempts,
      startTime: new Date()
    };

    session.trackedJobs.set(jobId, jobStatus);
    this.logger.debug(`📋 Added job to monitoring: ${jobId} in session: ${sessionId}`);
  }

  /**
   * Monitor tenant creation jobs automatically
   */
  async monitorTenantCreation(tenantSlug: string): Promise<boolean> {
    const sessionId = this.startMonitoring(tenantSlug);
    
    try {
      // Wait a moment for jobs to be queued
      await this.sleep(1000);
      
      // Discover jobs related to tenant creation
      const tenantJobs = await this.discoverTenantJobs(tenantSlug);
      
      if (tenantJobs.length === 0) {
        this.logger.info(`📭 No tenant creation jobs found for: ${tenantSlug}`);
        return true;
      }

      // Track discovered jobs
      tenantJobs.forEach(job => {
        this.trackJob(sessionId, job.id);
      });

      this.logger.info(`🔍 Monitoring ${tenantJobs.length} tenant creation jobs for: ${tenantSlug}`);
      
      // Wait for jobs to complete
      return await this.waitForCompletion(sessionId);
      
    } catch (error) {
      this.logger.error(`💥 Tenant creation monitoring failed for ${tenantSlug}:`, error);
      return false;
    } finally {
      this.endMonitoring(sessionId);
    }
  }

  /**
   * Monitor tenant seeding jobs
   */
  async monitorTenantSeeding(tenantSlug: string): Promise<boolean> {
    const sessionId = this.startMonitoring(tenantSlug);
    
    try {
      // Wait for seeding jobs to be queued
      await this.sleep(2000);
      
      const seedingJobs = await this.discoverSeedingJobs(tenantSlug);
      
      if (seedingJobs.length === 0) {
        this.logger.info(`📭 No seeding jobs found for: ${tenantSlug}`);
        return true;
      }

      seedingJobs.forEach(job => {
        this.trackJob(sessionId, job.id);
      });

      this.logger.info(`🌱 Monitoring ${seedingJobs.length} seeding jobs for: ${tenantSlug}`);
      
      return await this.waitForCompletion(sessionId);
      
    } catch (error) {
      this.logger.error(`💥 Seeding monitoring failed for ${tenantSlug}:`, error);
      return false;
    } finally {
      this.endMonitoring(sessionId);
    }
  }

  /**
   * Wait for all jobs in a session to complete
   */
  async waitForCompletion(sessionId: string): Promise<boolean> {
    const session = this.sessions.get(sessionId);
    if (!session) {
      throw new Error(`Monitoring session not found: ${sessionId}`);
    }

    const startTime = Date.now();
    let pollCount = 0;

    while (Date.now() - startTime < this.config.timeout) {
      pollCount++;
      this.logger.debug(`🔄 Polling jobs (attempt ${pollCount}) for session: ${sessionId}`);
      
      await this.updateJobStatuses(sessionId);
      
      const allCompleted = this.checkAllJobsCompleted(sessionId);
      const anyFailed = this.checkAnyJobsFailed(sessionId);
      
      if (anyFailed) {
        session.failed = true;
        session.endTime = new Date();
        this.logger.error(`❌ Jobs failed in session: ${sessionId}`);
        this.logFailedJobs(session);
        return false;
      }
      
      if (allCompleted) {
        session.completed = true;
        session.endTime = new Date();
        this.logger.info(`✅ All jobs completed successfully in session: ${sessionId}`);
        return true;
      }
      
      await this.sleep(this.config.pollInterval);
    }

    // Timeout reached
    session.failed = true;
    session.endTime = new Date();
    this.logger.error(`⏰ Job monitoring timeout for session: ${sessionId}`);
    this.logIncompleteJobs(session);
    return false;
  }

  /**
   * Update status of all jobs in a session
   */
  private async updateJobStatuses(sessionId: string): Promise<void> {
    const session = this.sessions.get(sessionId);
    if (!session) return;

    for (const [jobId, jobStatus] of session.trackedJobs) {
      try {
        const currentStatus = await this.getJobStatus(jobId);
        
        if (currentStatus) {
          jobStatus.status = currentStatus.status;
          jobStatus.attempts = currentStatus.attempts;
          jobStatus.lastError = currentStatus.lastError;
          
          if (currentStatus.status === 'completed' || currentStatus.status === 'failed') {
            jobStatus.endTime = new Date();
            jobStatus.duration = jobStatus.endTime.getTime() - jobStatus.startTime.getTime();
          }
        }
      } catch (error) {
        this.logger.error(`Failed to update job status for ${jobId}:`, error);
        jobStatus.lastError = error instanceof Error ? error.message : 'Unknown error';
      }
    }
  }

  /**
   * Get current status of a specific job
   */
  private async getJobStatus(jobId: string): Promise<JobStatus | null> {
    try {
      // Query Laravel jobs table
      const result = this.dbHelper.executeArtisan(
        `tinker --execute="\\$job = DB::table('jobs')->where('id', '${jobId}')->first(); if (\\$job) { echo json_encode(['status' => 'pending', 'attempts' => \\$job->attempts, 'payload' => \\$job->payload, 'available_at' => \\$job->available_at]); } else { \\$failedJob = DB::table('failed_jobs')->where('uuid', '${jobId}')->first(); if (\\$failedJob) { echo json_encode(['status' => 'failed', 'attempts' => 0, 'error' => \\$failedJob->exception]); } else { echo json_encode(['status' => 'completed', 'attempts' => 0]); } }"`
      );

      const trimmed = result.trim();
      if (trimmed && trimmed !== 'null') {
        const data = JSON.parse(trimmed);
        return {
          id: jobId,
          status: data.status,
          attempts: data.attempts || 0,
          maxAttempts: 3, // Default
          lastError: data.error,
          startTime: new Date() // This should be tracked better
        };
      }
      
      return null;
    } catch (error) {
      this.logger.error(`Failed to get job status for ${jobId}:`, error);
      return null;
    }
  }

  /**
   * Discover jobs related to tenant creation
   */
  private async discoverTenantJobs(tenantSlug: string): Promise<JobInfo[]> {
    try {
      const result = this.dbHelper.executeArtisan(
        `tinker --execute="\\$jobs = DB::table('jobs')->where('payload', 'like', '%${tenantSlug}%')->get(); echo json_encode(\\$jobs->toArray());"`
      );

      const trimmed = result.trim();
      if (trimmed && trimmed !== '[]') {
        return JSON.parse(trimmed).map((job: any) => ({
          id: job.id,
          queue: job.queue,
          connection: 'database',
          payload: JSON.parse(job.payload),
          attempts: job.attempts,
          available_at: new Date(job.available_at * 1000),
          created_at: new Date(job.created_at * 1000)
        }));
      }
      
      return [];
    } catch (error) {
      this.logger.error(`Failed to discover tenant jobs for ${tenantSlug}:`, error);
      return [];
    }
  }

  /**
   * Discover jobs related to tenant seeding
   */
  private async discoverSeedingJobs(tenantSlug: string): Promise<JobInfo[]> {
    try {
      // Look for seeding-related jobs in the queue
      const seedingPatterns = ['SeedTenant', 'TenantSeeding', 'seed', tenantSlug];
      const patterns = seedingPatterns.map(p => `'%${p}%'`).join(' OR payload LIKE ');
      
      const result = this.dbHelper.executeArtisan(
        `tinker --execute="\\$jobs = DB::table('jobs')->where(function(\\$query) { \\$query->where('payload', 'LIKE', ${patterns}); })->get(); echo json_encode(\\$jobs->toArray());"`
      );

      const trimmed = result.trim();
      if (trimmed && trimmed !== '[]') {
        return JSON.parse(trimmed).map((job: any) => ({
          id: job.id,
          queue: job.queue,
          connection: 'database',
          payload: JSON.parse(job.payload),
          attempts: job.attempts,
          available_at: new Date(job.available_at * 1000),
          created_at: new Date(job.created_at * 1000)
        }));
      }
      
      return [];
    } catch (error) {
      this.logger.error(`Failed to discover seeding jobs for ${tenantSlug}:`, error);
      return [];
    }
  }

  /**
   * Check if all jobs in session are completed
   */
  private checkAllJobsCompleted(sessionId: string): boolean {
    const session = this.sessions.get(sessionId);
    if (!session) return false;

    return Array.from(session.trackedJobs.values()).every(
      job => job.status === 'completed'
    );
  }

  /**
   * Check if any jobs in session have failed
   */
  private checkAnyJobsFailed(sessionId: string): boolean {
    const session = this.sessions.get(sessionId);
    if (!session) return false;

    return Array.from(session.trackedJobs.values()).some(
      job => job.status === 'failed' || job.attempts >= job.maxAttempts
    );
  }

  /**
   * End a monitoring session
   */
  endMonitoring(sessionId: string): void {
    const session = this.sessions.get(sessionId);
    if (session) {
      session.endTime = new Date();
      this.logger.info(`🏁 Ended monitoring session: ${sessionId}`);
      // Keep session for a while for reporting purposes
      setTimeout(() => {
        this.sessions.delete(sessionId);
      }, 300000); // 5 minutes
    }
  }

  /**
   * Get monitoring statistics
   */
  getMonitoringStats(sessionId: string): {
    totalJobs: number;
    completedJobs: number;
    failedJobs: number;
    pendingJobs: number;
    duration?: number;
  } {
    const session = this.sessions.get(sessionId);
    if (!session) {
      return { totalJobs: 0, completedJobs: 0, failedJobs: 0, pendingJobs: 0 };
    }

    const jobs = Array.from(session.trackedJobs.values());
    const completedJobs = jobs.filter(job => job.status === 'completed').length;
    const failedJobs = jobs.filter(job => job.status === 'failed').length;
    const pendingJobs = jobs.filter(job => job.status === 'pending' || job.status === 'processing').length;
    
    const duration = session.endTime 
      ? session.endTime.getTime() - session.startTime.getTime()
      : Date.now() - session.startTime.getTime();

    return {
      totalJobs: jobs.length,
      completedJobs,
      failedJobs,
      pendingJobs,
      duration
    };
  }

  /**
   * Log failed jobs
   */
  private logFailedJobs(session: MonitoringSession): void {
    const failedJobs = Array.from(session.trackedJobs.values()).filter(
      job => job.status === 'failed'
    );

    this.logger.error(`❌ Failed jobs in session ${session.id}:`);
    failedJobs.forEach(job => {
      this.logger.error(`  - Job ${job.id}: ${job.lastError || 'Unknown error'}`);
    });
  }

  /**
   * Log incomplete jobs on timeout
   */
  private logIncompleteJobs(session: MonitoringSession): void {
    const incompleteJobs = Array.from(session.trackedJobs.values()).filter(
      job => job.status !== 'completed' && job.status !== 'failed'
    );

    this.logger.warn(`⏰ Incomplete jobs in session ${session.id}:`);
    incompleteJobs.forEach(job => {
      this.logger.warn(`  - Job ${job.id}: ${job.status} (${job.attempts}/${job.maxAttempts} attempts)`);
    });
  }

  /**
   * Sleep utility
   */
  private sleep(ms: number): Promise<void> {
    return new Promise(resolve => setTimeout(resolve, ms));
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
        this.logger.debug(`🧹 Cleaned up old monitoring session: ${sessionId}`);
      }
    }
  }
}

/**
 * Factory function to create JobMonitor with default config
 */
export function createJobMonitor(
  logger: ValidationLogger,
  dbHelper: DatabaseHelper,
  config?: Partial<JobMonitorConfig>
): JobMonitor {
  const defaultConfig: JobMonitorConfig = {
    pollInterval: 2000, // 2 seconds
    timeout: 120000, // 2 minutes
    maxRetries: 3,
    queues: ['default', 'tenant-creation', 'tenant-seeding'],
    connections: ['database'],
    tenantJobTypes: ['tenant-setup', 'tenant-seed', 'tenant-migrate']
  };

  const finalConfig = config ? { ...defaultConfig, ...config } : defaultConfig;
  return new JobMonitor(logger, dbHelper, finalConfig);
}