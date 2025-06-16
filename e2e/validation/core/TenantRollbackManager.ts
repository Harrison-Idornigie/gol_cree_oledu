/**
 * Tenant Rollback Manager
 * 
 * Manages advanced rollback mechanisms for tenant creation and validation processes.
 * Tracks tenant creation steps, handles rollback scenarios, and ensures clean state recovery.
 */

import { ValidationLogger } from '../logging/ValidationLogger';
import { DatabaseHelper } from '../../utils/database-helpers';

export interface RollbackStep {
  id: string;
  type: 'tenant_creation' | 'database_setup' | 'admin_user' | 'seeding' | 'custom';
  description: string;
  rollbackAction: () => Promise<void>;
  timestamp: Date;
  metadata?: any;
}

export interface TenantSnapshot {
  tenantSlug: string;
  creationSteps: RollbackStep[];
  databaseState?: any;
  adminUserState?: any;
  seedingState?: any;
  timestamp: Date;
}

export interface RollbackOptions {
  preserveDatabase?: boolean;
  preserveAdminUser?: boolean;
  customCleanupActions?: Array<() => Promise<void>>;
  skipStepTypes?: string[];
  dryRun?: boolean;
}

export class TenantRollbackManager {
  private snapshots: Map<string, TenantSnapshot> = new Map();
  private logger: ValidationLogger;
  private dbHelper: DatabaseHelper;

  constructor(logger: ValidationLogger, dbHelper: DatabaseHelper) {
    this.logger = logger;
    this.dbHelper = dbHelper;
  }

  /**
   * Create a new tenant snapshot to track rollback steps
   */
  createSnapshot(tenantSlug: string): TenantSnapshot {
    const snapshot: TenantSnapshot = {
      tenantSlug,
      creationSteps: [],
      timestamp: new Date()
    };

    this.snapshots.set(tenantSlug, snapshot);
    this.logger.debug(`📸 Created rollback snapshot for tenant: ${tenantSlug}`);
    
    return snapshot;
  }

  /**
   * Add a rollback step to the current snapshot
   */
  addRollbackStep(
    tenantSlug: string, 
    type: RollbackStep['type'], 
    description: string,
    rollbackAction: () => Promise<void>,
    metadata?: any
  ): void {
    const snapshot = this.snapshots.get(tenantSlug);
    if (!snapshot) {
      throw new Error(`No snapshot found for tenant: ${tenantSlug}`);
    }

    const step: RollbackStep = {
      id: `${type}_${Date.now()}_${Math.random().toString(36).substring(2, 8)}`,
      type,
      description,
      rollbackAction,
      timestamp: new Date(),
      metadata
    };

    snapshot.creationSteps.push(step);
    this.logger.debug(`📝 Added rollback step for ${tenantSlug}: ${description}`);
  }

  /**
   * Capture current database state for rollback purposes
   */
  async captureDatabaseState(tenantSlug: string): Promise<void> {
    const snapshot = this.snapshots.get(tenantSlug);
    if (!snapshot) {
      throw new Error(`No snapshot found for tenant: ${tenantSlug}`);
    }

    try {
      // Check if tenant exists and capture basic info
      const tenantExists = await this.dbHelper.tenantExists(tenantSlug);
      const tenantData = tenantExists ? await this.dbHelper.getTenant(tenantSlug) : null;

      snapshot.databaseState = {
        exists: tenantExists,
        data: tenantData,
        capturedAt: new Date()
      };

      this.logger.debug(`💾 Captured database state for tenant: ${tenantSlug}`);
    } catch (error) {
      this.logger.error(`Failed to capture database state for ${tenantSlug}:`, error);
      throw error;
    }
  }

  /**
   * Capture admin user state
   */
  async captureAdminUserState(tenantSlug: string, adminEmail: string): Promise<void> {
    const snapshot = this.snapshots.get(tenantSlug);
    if (!snapshot) {
      throw new Error(`No snapshot found for tenant: ${tenantSlug}`);
    }

    try {
      const adminExists = await this.dbHelper.verifyTenantAdmin(tenantSlug, adminEmail);
      
      snapshot.adminUserState = {
        email: adminEmail,
        exists: adminExists,
        capturedAt: new Date()
      };

      this.logger.debug(`👤 Captured admin user state for tenant: ${tenantSlug}`);
    } catch (error) {
      this.logger.error(`Failed to capture admin user state for ${tenantSlug}:`, error);
      throw error;
    }
  }

  /**
   * Execute rollback for a specific tenant
   */
  async rollbackTenant(tenantSlug: string, options: RollbackOptions = {}): Promise<void> {
    const snapshot = this.snapshots.get(tenantSlug);
    if (!snapshot) {
      this.logger.warn(`No snapshot found for tenant: ${tenantSlug}, performing basic cleanup`);
      await this.performBasicCleanup(tenantSlug);
      return;
    }

    this.logger.info(`🔄 Starting rollback for tenant: ${tenantSlug}`);

    if (options.dryRun) {
      this.logger.info(`🧪 DRY RUN: Would rollback ${snapshot.creationSteps.length} steps`);
      this.logRollbackPlan(snapshot, options);
      return;
    }

    try {
      // Execute custom cleanup actions first
      if (options.customCleanupActions) {
        for (const action of options.customCleanupActions) {
          try {
            await action();
          } catch (error) {
            this.logger.error('Custom cleanup action failed:', error);
          }
        }
      }

      // Execute rollback steps in reverse order
      const stepsToRollback = snapshot.creationSteps
        .filter(step => !options.skipStepTypes?.includes(step.type))
        .reverse();

      for (const step of stepsToRollback) {
        try {
          this.logger.debug(`🔄 Rolling back step: ${step.description}`);
          await step.rollbackAction();
          this.logger.debug(`✅ Successfully rolled back: ${step.description}`);
        } catch (error) {
          this.logger.error(`❌ Failed to rollback step '${step.description}':`, error);
          // Continue with other rollback steps even if one fails
        }
      }

      // Final cleanup using database helper
      if (!options.preserveDatabase) {
        await this.dbHelper.cleanupTenant(tenantSlug);
      }

      this.logger.info(`✅ Completed rollback for tenant: ${tenantSlug}`);
    } catch (error) {
      this.logger.error(`💥 Rollback failed for tenant ${tenantSlug}:`, error);
      throw error;
    } finally {
      // Clean up the snapshot
      this.snapshots.delete(tenantSlug);
    }
  }

  /**
   * Rollback multiple tenants
   */
  async rollbackMultipleTenants(tenantSlugs: string[], options: RollbackOptions = {}): Promise<void> {
    this.logger.info(`🔄 Starting batch rollback for ${tenantSlugs.length} tenants`);

    const results = await Promise.allSettled(
      tenantSlugs.map(slug => this.rollbackTenant(slug, options))
    );

    const failures = results.filter(result => result.status === 'rejected');
    
    if (failures.length > 0) {
      this.logger.error(`❌ ${failures.length} tenant rollbacks failed`);
      failures.forEach((failure, index) => {
        if (failure.status === 'rejected') {
          this.logger.error(`  - ${tenantSlugs[index]}: ${failure.reason}`);
        }
      });
    } else {
      this.logger.info(`✅ All ${tenantSlugs.length} tenants rolled back successfully`);
    }
  }

  /**
   * Auto-rollback on validation failure
   */
  async autoRollbackOnFailure(tenantSlug: string, error: Error): Promise<void> {
    this.logger.warn(`🔄 Auto-rollback triggered for tenant ${tenantSlug} due to validation failure`);
    this.logger.debug(`Failure reason: ${error.message}`);

    try {
      await this.rollbackTenant(tenantSlug, {
        preserveDatabase: false,
        preserveAdminUser: false
      });
    } catch (rollbackError) {
      this.logger.error(`💥 Auto-rollback failed for tenant ${tenantSlug}:`, rollbackError);
      // Try basic cleanup as fallback
      await this.performBasicCleanup(tenantSlug);
    }
  }

  /**
   * Get rollback status for a tenant
   */
  getRollbackStatus(tenantSlug: string): {
    hasSnapshot: boolean;
    stepCount: number;
    createdAt?: Date;
    lastStepAt?: Date;
  } {
    const snapshot = this.snapshots.get(tenantSlug);
    
    if (!snapshot) {
      return { hasSnapshot: false, stepCount: 0 };
    }

    const lastStep = snapshot.creationSteps[snapshot.creationSteps.length - 1];
    
    return {
      hasSnapshot: true,
      stepCount: snapshot.creationSteps.length,
      createdAt: snapshot.timestamp,
      lastStepAt: lastStep?.timestamp
    };
  }

  /**
   * Clean up all snapshots
   */
  clearAllSnapshots(): void {
    const count = this.snapshots.size;
    this.snapshots.clear();
    this.logger.debug(`🧹 Cleared ${count} rollback snapshots`);
  }

  /**
   * Get all tracked tenant slugs
   */
  getTrackedTenants(): string[] {
    return Array.from(this.snapshots.keys());
  }

  /**
   * Perform basic cleanup without snapshot
   */
  private async performBasicCleanup(tenantSlug: string): Promise<void> {
    try {
      this.logger.info(`🧹 Performing basic cleanup for tenant: ${tenantSlug}`);
      await this.dbHelper.cleanupTenant(tenantSlug);
    } catch (error) {
      this.logger.error(`Failed basic cleanup for ${tenantSlug}:`, error);
    }
  }

  /**
   * Log rollback plan for dry run
   */
  private logRollbackPlan(snapshot: TenantSnapshot, options: RollbackOptions): void {
    const stepsToRollback = snapshot.creationSteps
      .filter(step => !options.skipStepTypes?.includes(step.type))
      .reverse();

    this.logger.info(`📋 Rollback plan for ${snapshot.tenantSlug}:`);
    stepsToRollback.forEach((step, index) => {
      this.logger.info(`  ${index + 1}. ${step.description} (${step.type})`);
    });

    if (options.customCleanupActions?.length) {
      this.logger.info(`  + ${options.customCleanupActions.length} custom cleanup actions`);
    }

    if (!options.preserveDatabase) {
      this.logger.info(`  + Final database cleanup via DatabaseHelper`);
    }
  }
}

/**
 * Factory function to create TenantRollbackManager
 */
export function createTenantRollbackManager(
  logger: ValidationLogger, 
  dbHelper: DatabaseHelper
): TenantRollbackManager {
  return new TenantRollbackManager(logger, dbHelper);
}