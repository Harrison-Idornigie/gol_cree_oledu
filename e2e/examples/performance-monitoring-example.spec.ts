/**
 * Performance Monitoring Example
 * 
 * Demonstrates how to use the performance collection system
 * with the enhanced validation framework.
 */

import { test, expect } from '@playwright/test';
import { 
  createPerformanceCollector,
  PerformanceCollector,
  PerformanceReport 
} from '../validation/core/PerformanceCollector';
import { createValidationManager } from '../validation/core/ValidationManager';
import { ValidationLogger } from '../validation/logging/ValidationLogger';
import { DatabaseHelper } from '../utils/database-helpers';
import { ApiHelper } from '../utils/api-helpers';
import { TenantFixtures } from '../fixtures/tenant-fixtures';

let performanceCollector: PerformanceCollector;
let sessionId: string;

test.describe('Performance Monitoring Examples', () => {
  test.beforeAll(async () => {
    // Initialize performance collector
    const logger = new ValidationLogger({ level: 'info', outputs: ['console'], metricsEnabled: true });
    
    performanceCollector = createPerformanceCollector(logger, {
      enabled: true,
      collectMemory: true,
      collectDatabase: true,
      collectNetwork: true,
      reportFormat: 'console',
      thresholds: {
        maxValidatorDuration: 3000,     // 3 seconds
        maxTotalDuration: 15000,        // 15 seconds
        maxMemoryUsage: 50 * 1024 * 1024, // 50MB
        maxDatabaseQueries: 25,         // 25 queries max
        maxNetworkRequests: 10          // 10 network calls max
      }
    });
  });

  test.beforeEach(async () => {
    // Start a new performance session for each test
    sessionId = performanceCollector.startSession('perf-test-tenant');
  });

  test.afterEach(async () => {
    // End session and generate report
    const report = performanceCollector.endSession();
    
    if (report) {
      console.log(`\n📊 Performance Report for ${report.sessionId}`);
      console.log(`Total Duration: ${report.totalDuration}ms`);
      console.log(`Performance Score: ${report.summary.performanceScore}/100`);
      console.log(`Validators: ${report.summary.totalValidators}`);
      
      if (report.warnings.length > 0) {
        console.log(`⚠️  Warnings: ${report.warnings.length}`);
        report.warnings.forEach(warning => {
          console.log(`  - ${warning.message}`);
        });
      }
      
      if (report.recommendations.length > 0) {
        console.log(`💡 Recommendations:`);
        report.recommendations.forEach(rec => {
          console.log(`  - ${rec}`);
        });
      }
    }
  });

  test('basic performance monitoring', async () => {
    // Start tracking a validation phase
    performanceCollector.startPhase('tenant-validation');
    
    // Simulate validator performance tracking
    performanceCollector.startValidator('tenant-database');
    
    // Simulate some work
    await simulateValidationWork();
    
    // Record the validator completion
    performanceCollector.endValidator({
      validator: 'tenant-database',
      status: 'passed',
      message: 'Database validation completed',
      duration: 1500, // 1.5 seconds
      timestamp: new Date()
    });
    
    // End the phase
    performanceCollector.endPhase();
    
    // Record custom metrics
    performanceCollector.recordMetric('custom_operation', 800, 'ms', 'timing');
    performanceCollector.recordMetric('memory_allocations', 15, 'count', 'memory');
    
    // The performance report will be generated in afterEach
  });

  test('database query performance tracking', async () => {
    performanceCollector.startPhase('database-intensive');
    performanceCollector.startValidator('database-heavy-validator');
    
    // Simulate multiple database operations
    const queries = [
      { query: 'SELECT * FROM users', duration: 45 },
      { query: 'SELECT * FROM roles', duration: 32 },
      { query: 'SELECT * FROM permissions', duration: 28 },
      { query: 'INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)', duration: 18 },
      { query: 'UPDATE users SET last_login = ? WHERE id = ?', duration: 22 }
    ];
    
    for (const queryInfo of queries) {
      // Record each database query
      performanceCollector.recordDatabaseQuery(
        queryInfo.query,
        queryInfo.duration,
        1, // affected rows
        'tenant_db'
      );
      
      // Simulate query execution time
      await new Promise(resolve => setTimeout(resolve, queryInfo.duration));
    }
    
    performanceCollector.endValidator({
      validator: 'database-heavy-validator',
      status: 'passed',
      message: 'Database operations completed',
      duration: queries.reduce((sum, q) => sum + q.duration, 0),
      timestamp: new Date()
    });
    
    performanceCollector.endPhase();
  });

  test('network call performance tracking', async () => {
    performanceCollector.startPhase('network-operations');
    performanceCollector.startValidator('api-connectivity');
    
    // Simulate network calls
    const networkCalls = [
      { url: 'http://localhost:8000/api/health', method: 'GET', duration: 150, status: 200, size: 1024 },
      { url: 'http://localhost:8000/api/tenant/create', method: 'POST', duration: 450, status: 201, size: 2048 },
      { url: 'http://localhost:8000/api/tenant/seed', method: 'POST', duration: 1200, status: 200, size: 512 },
      { url: 'http://localhost:3000/health', method: 'GET', duration: 75, status: 200, size: 256 }
    ];
    
    for (const call of networkCalls) {
      // Record network call
      performanceCollector.recordNetworkCall(
        call.url,
        call.method,
        call.duration,
        call.status,
        call.size
      );
      
      // Simulate network call time
      await new Promise(resolve => setTimeout(resolve, call.duration));
    }
    
    performanceCollector.endValidator({
      validator: 'api-connectivity',
      status: 'passed',
      message: 'Network operations completed',
      duration: networkCalls.reduce((sum, call) => sum + call.duration, 0),
      timestamp: new Date()
    });
    
    performanceCollector.endPhase();
  });

  test('memory usage tracking', async () => {
    performanceCollector.startPhase('memory-intensive');
    performanceCollector.startValidator('memory-validator');
    
    // Record initial memory state
    const initialMemory = process.memoryUsage().heapUsed;
    performanceCollector.recordMetric('initial_memory', initialMemory, 'bytes', 'memory');
    
    // Simulate memory-intensive operations
    const largeArrays = [];
    for (let i = 0; i < 100; i++) {
      largeArrays.push(new Array(1000).fill(`test-data-${i}`));
      
      if (i % 25 === 0) {
        const currentMemory = process.memoryUsage().heapUsed;
        performanceCollector.recordMetric(
          `memory_checkpoint_${i}`,
          currentMemory,
          'bytes',
          'memory',
          { iteration: i, delta: currentMemory - initialMemory }
        );
      }
    }
    
    // Record peak memory
    const peakMemory = process.memoryUsage().heapUsed;
    performanceCollector.recordMetric('peak_memory', peakMemory, 'bytes', 'memory');
    
    // Clean up
    largeArrays.length = 0;
    
    // Force garbage collection if available
    if (global.gc) {
      global.gc();
    }
    
    const finalMemory = process.memoryUsage().heapUsed;
    performanceCollector.recordMetric('final_memory', finalMemory, 'bytes', 'memory');
    
    performanceCollector.endValidator({
      validator: 'memory-validator',
      status: 'passed',
      message: 'Memory operations completed',
      duration: 2000,
      timestamp: new Date()
    });
    
    performanceCollector.endPhase();
  });

  test('multi-phase validation performance', async () => {
    // Phase 1: Environment Setup
    performanceCollector.startPhase('environment-setup');
    
    performanceCollector.startValidator('backend-health');
    await simulateValidationWork(200);
    performanceCollector.endValidator({
      validator: 'backend-health',
      status: 'passed',
      message: 'Backend health check passed',
      duration: 200,
      timestamp: new Date()
    });
    
    performanceCollector.startValidator('frontend-health');
    await simulateValidationWork(150);
    performanceCollector.endValidator({
      validator: 'frontend-health',
      status: 'passed',
      message: 'Frontend health check passed',
      duration: 150,
      timestamp: new Date()
    });
    
    performanceCollector.endPhase();
    
    // Phase 2: Database Setup
    performanceCollector.startPhase('database-setup');
    
    performanceCollector.startValidator('database-connectivity');
    await simulateValidationWork(300);
    performanceCollector.recordDatabaseQuery('SELECT 1', 50);
    performanceCollector.endValidator({
      validator: 'database-connectivity',
      status: 'passed',
      message: 'Database connectivity verified',
      duration: 300,
      timestamp: new Date()
    });
    
    performanceCollector.endPhase();
    
    // Phase 3: Tenant Creation
    performanceCollector.startPhase('tenant-creation');
    
    performanceCollector.startValidator('tenant-database');
    await simulateValidationWork(800);
    performanceCollector.recordDatabaseQuery('CREATE DATABASE tenant_test', 120);
    performanceCollector.recordDatabaseQuery('CREATE TABLE users (...)', 85);
    performanceCollector.endValidator({
      validator: 'tenant-database',
      status: 'passed',
      message: 'Tenant database created',
      duration: 800,
      timestamp: new Date()
    });
    
    performanceCollector.startValidator('admin-user');
    await simulateValidationWork(400);
    performanceCollector.recordDatabaseQuery('INSERT INTO users (...)', 45);
    performanceCollector.endValidator({
      validator: 'admin-user',
      status: 'passed',
      message: 'Admin user created',
      duration: 400,
      timestamp: new Date()
    });
    
    performanceCollector.endPhase();
    
    // Record overall metrics
    performanceCollector.recordMetric('total_phases', 3, 'count', 'custom');
    performanceCollector.recordMetric('total_validators', 5, 'count', 'custom');
  });

  test('performance threshold violations', async () => {
    performanceCollector.startPhase('slow-operations');
    
    // Intentionally slow validator to trigger warnings
    performanceCollector.startValidator('intentionally-slow');
    
    // Simulate slow operation (exceeds 3000ms threshold)
    await simulateValidationWork(4000);
    
    // Record many database queries (exceeds 25 query threshold)
    for (let i = 0; i < 30; i++) {
      performanceCollector.recordDatabaseQuery(
        `SELECT * FROM table_${i}`,
        50 + Math.random() * 100
      );
    }
    
    // Use significant memory (close to 50MB threshold)
    const memoryHog = new Array(5000000).fill('memory-test-data');
    performanceCollector.recordMetric(
      'high_memory_usage',
      process.memoryUsage().heapUsed,
      'bytes',
      'memory'
    );
    
    performanceCollector.endValidator({
      validator: 'intentionally-slow',
      status: 'passed',
      message: 'Slow validation completed (for testing)',
      duration: 4000,
      timestamp: new Date()
    });
    
    performanceCollector.endPhase();
    
    // Clean up memory
    memoryHog.length = 0;
    
    // This test should generate performance warnings in the report
  });

  test('integrated validation with performance monitoring', async () => {
    // Use the actual validation system with performance monitoring
    const dbHelper = new DatabaseHelper();
    const apiHelper = new ApiHelper();
    const fixtures = new TenantFixtures();
    
    const validationManager = createValidationManager({
      performance: {
        enabled: true,
        collectMemory: true,
        collectDatabase: true,
        collectNetwork: true,
        reportFormat: 'console',
        thresholds: {
          maxValidatorDuration: 5000,
          maxTotalDuration: 30000,
          maxMemoryUsage: 100 * 1024 * 1024,
          maxDatabaseQueries: 50,
          maxNetworkRequests: 20
        }
      }
    });
    
    // Create a test tenant
    const tenantSlug = dbHelper.generateTestTenantSlug();
    const adminEmail = dbHelper.generateTestAdminEmail(tenantSlug);
    
    try {
      // Run validation with performance monitoring
      const results = await validationManager.validateTenant(
        {
          slug: tenantSlug,
          name: `Test Tenant ${tenantSlug}`,
          adminEmail
        },
        {
          name: 'test',
          backendUrl: 'http://localhost:8000',
          frontendUrl: 'http://localhost:3000',
          apiBaseUrl: 'http://localhost:8000/api',
          testMode: true
        },
        {
          dbHelper,
          apiHelper,
          fixtures
        }
      );
      
      // Validation should complete successfully
      expect(results.summary.success).toBe(true);
      
      // Performance data should be available
      const performanceData = results.getPerformanceData();
      expect(performanceData.totalDuration).toBeGreaterThan(0);
      expect(performanceData.slowestValidators).toBeDefined();
      
    } finally {
      // Clean up
      await dbHelper.cleanupTenant(tenantSlug);
    }
  });
});

/**
 * Simulate validation work by waiting
 */
async function simulateValidationWork(duration: number = 500): Promise<void> {
  return new Promise(resolve => setTimeout(resolve, duration));
}