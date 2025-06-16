# Integration Guide - Enhanced Pre-Test Validation System

This guide provides comprehensive instructions for integrating and using the enhanced pre-test validation system with advanced rollback mechanisms, job monitoring, performance collection, and tenant state validation.

## Table of Contents

- [Overview](#overview)
- [Migration from Current Setup](#migration-from-current-setup)
- [Configuration](#configuration)
- [New Components](#new-components)
- [Usage Examples](#usage-examples)
- [Troubleshooting](#troubleshooting)
- [Performance Tuning](#performance-tuning)
- [Advanced Features](#advanced-features)
- [Best Practices](#best-practices)

## Overview

The enhanced validation system provides enterprise-grade reliability for tenant testing with:

- **Advanced Rollback Mechanisms**: Automatic cleanup and state recovery on validation failures
- **Job Monitoring**: Real-time tracking of Laravel queue jobs during tenant operations
- **Performance Collection**: Comprehensive metrics and benchmarking
- **Advanced Tenant State Validation**: Deep validation of tenant configuration and data integrity

## Migration from Current Setup

### Step 1: Update Dependencies

Ensure your test environment has the latest validation components:

```bash
# Navigate to e2e directory
cd e2e

# Verify all new files are present
ls -la validation/core/
ls -la validation/validators/
```

### Step 2: Update Configuration

Update your [`validation.config.ts`](config/validation.config.ts:1) to include new features:

```typescript
// Add to your existing validation config
export const enhancedValidationConfig: ValidationConfig = {
  ...defaultValidationConfig,
  
  // Enable new features
  rollback: {
    enabled: true,
    autoRollbackOnFailure: true,
    preserveOnSuccess: false
  },
  
  jobMonitoring: {
    enabled: true,
    timeout: 120000, // 2 minutes
    pollInterval: 2000, // 2 seconds
    trackTenantJobs: true,
    trackSeedingJobs: true
  },
  
  performance: {
    enabled: true,
    collectMemory: true,
    collectDatabase: true,
    collectNetwork: true,
    reportFormat: 'console', // 'console' | 'json' | 'html'
    thresholds: {
      maxValidatorDuration: 5000,
      maxTotalDuration: 30000,
      maxMemoryUsage: 100 * 1024 * 1024, // 100MB
      maxDatabaseQueries: 50
    }
  },
  
  tenantState: {
    enabled: true,
    validationLevel: 'complete', // 'basic' | 'complete' | 'custom'
    checkFilePermissions: true,
    customValidations: []
  }
};
```

### Step 3: Update Test Imports

Update your test files to use the enhanced validation system:

```typescript
// Before
import { validateTenantSetup } from '../utils/validation-helpers';

// After - now includes all enhanced features
import { 
  validateTenantSetup,
  withRollbackProtection,
  withPerformanceMonitoring,
  withJobMonitoring
} from '../utils/validation-helpers';
```

### Step 4: Update ValidationManager Registration

The [`ValidationManager`](validation/core/ValidationManager.ts:32) automatically includes new validators. Update your validation manager initialization:

```typescript
// Enhanced validation manager with new components
const validationManager = createValidationManager({
  ...enhancedValidationConfig,
  // Additional components are automatically registered
});
```

## Configuration

### Environment Variables

Add these environment variables to your [`.env.test`](.env.test:1) file:

```bash
# Rollback Configuration
VALIDATION_ROLLBACK_ENABLED=true
VALIDATION_AUTO_ROLLBACK=true

# Job Monitoring
VALIDATION_JOB_MONITORING=true
VALIDATION_JOB_TIMEOUT=120000
VALIDATION_JOB_POLL_INTERVAL=2000

# Performance Collection
VALIDATION_PERFORMANCE_ENABLED=true
VALIDATION_PERFORMANCE_MEMORY=true
VALIDATION_PERFORMANCE_DATABASE=true
VALIDATION_PERFORMANCE_REPORT_FORMAT=console

# Tenant State Validation
VALIDATION_TENANT_STATE_ENABLED=true
VALIDATION_TENANT_STATE_LEVEL=complete
VALIDATION_CHECK_FILE_PERMISSIONS=true

# Performance Thresholds
VALIDATION_MAX_VALIDATOR_DURATION=5000
VALIDATION_MAX_TOTAL_DURATION=30000
VALIDATION_MAX_MEMORY_USAGE=104857600
VALIDATION_MAX_DATABASE_QUERIES=50
```

### Configuration Options

#### Rollback Configuration

```typescript
interface RollbackConfig {
  enabled: boolean;                    // Enable rollback functionality
  autoRollbackOnFailure: boolean;      // Auto-rollback on validation failure
  preserveOnSuccess: boolean;          // Keep snapshots on successful validation
  customCleanupActions: Function[];    // Additional cleanup functions
  skipStepTypes: string[];            // Step types to skip during rollback
}
```

#### Job Monitoring Configuration

```typescript
interface JobMonitoringConfig {
  enabled: boolean;           // Enable job monitoring
  timeout: number;           // Max time to wait for jobs (ms)
  pollInterval: number;      // How often to check job status (ms)
  trackTenantJobs: boolean;  // Monitor tenant creation jobs
  trackSeedingJobs: boolean; // Monitor seeding jobs
  queues: string[];          // Queues to monitor
}
```

#### Performance Configuration

```typescript
interface PerformanceConfig {
  enabled: boolean;              // Enable performance collection
  collectMemory: boolean;        // Track memory usage
  collectDatabase: boolean;      // Track database queries
  collectNetwork: boolean;       // Track network calls
  reportFormat: string;          // Output format
  outputPath?: string;           // Custom output path
  thresholds: {
    maxValidatorDuration: number;
    maxTotalDuration: number;
    maxMemoryUsage: number;
    maxDatabaseQueries: number;
  };
}
```

## New Components

### TenantRollbackManager

Manages rollback operations and state recovery:

```typescript
import { createTenantRollbackManager } from '../validation/core/TenantRollbackManager';

// Create rollback manager
const rollbackManager = createTenantRollbackManager(logger, dbHelper);

// Start tracking tenant creation
const snapshot = rollbackManager.createSnapshot('my-tenant');

// Add rollback steps during tenant creation
rollbackManager.addRollbackStep(
  'my-tenant',
  'tenant_creation',
  'Create tenant database',
  async () => {
    // Cleanup action
    await dbHelper.cleanupTenant('my-tenant');
  }
);

// Rollback on failure
await rollbackManager.rollbackTenant('my-tenant');
```

### JobMonitor

Monitors Laravel queue jobs:

```typescript
import { createJobMonitor } from '../validation/core/JobMonitor';

// Create job monitor
const jobMonitor = createJobMonitor(logger, dbHelper);

// Monitor tenant creation jobs
const success = await jobMonitor.monitorTenantCreation('my-tenant');

// Monitor seeding jobs
const seedingSuccess = await jobMonitor.monitorTenantSeeding('my-tenant');
```

### PerformanceCollector

Collects performance metrics:

```typescript
import { createPerformanceCollector } from '../validation/core/PerformanceCollector';

// Create performance collector
const performanceCollector = createPerformanceCollector(logger);

// Start performance session
const sessionId = performanceCollector.startSession('my-tenant');

// Track validation phases
performanceCollector.startPhase('tenant-validation');
performanceCollector.startValidator('tenant-database');
// ... validation logic ...
performanceCollector.endValidator(result);
performanceCollector.endPhase();

// Generate report
const report = performanceCollector.endSession();
```

### TenantStateValidator

Advanced tenant state validation:

```typescript
import { TenantStateValidators } from '../validation/validators/TenantStateValidator';

// Use pre-configured validators
const basicValidator = TenantStateValidators.basic();
const completeValidator = TenantStateValidators.complete();
const languageAppValidator = TenantStateValidators.languageLearning();

// Or create custom validator
const customValidator = createTenantStateValidator({
  requiredConfigs: [
    {
      configKey: 'app.custom_setting',
      required: true,
      description: 'Custom app setting must be configured'
    }
  ],
  customValidations: [
    {
      name: 'custom-check',
      description: 'Custom validation logic',
      validator: async (context) => {
        // Custom validation logic
        return true;
      }
    }
  ]
});
```

## Usage Examples

### Basic Enhanced Validation

```typescript
// enhanced-tenant-test.spec.ts
import { test, expect } from '@playwright/test';
import { 
  validateTenantSetup,
  withRollbackProtection,
  withPerformanceMonitoring
} from '../utils/validation-helpers';

test.describe('Enhanced Tenant Tests', () => {
  test.beforeEach(async ({}, testInfo) => {
    // Enhanced validation with all new features
    await validateTenantSetup(testInfo);
  });

  test('tenant operations with rollback protection', 
    withRollbackProtection(
      withPerformanceMonitoring(async ({ page }) => {
        // Your test code here
        // Automatic rollback on failure
        // Performance metrics collected
      })
    )
  );
});
```

### Advanced Rollback Usage

```typescript
// Advanced rollback with custom cleanup
import { createTenantRollbackManager } from '../validation/core/TenantRollbackManager';

const rollbackManager = createTenantRollbackManager(logger, dbHelper);

// Create snapshot with custom actions
const snapshot = rollbackManager.createSnapshot('test-tenant');

// Add multiple rollback steps
rollbackManager.addRollbackStep(
  'test-tenant',
  'database_setup',
  'Create tenant database',
  async () => await dbHelper.dropTenantDatabase('test-tenant')
);

rollbackManager.addRollbackStep(
  'test-tenant',
  'admin_user',
  'Create admin user',
  async () => await dbHelper.deleteAdminUser('test-tenant')
);

// Rollback with options
await rollbackManager.rollbackTenant('test-tenant', {
  preserveDatabase: false,
  customCleanupActions: [
    async () => {
      // Custom cleanup logic
      await customCleanup();
    }
  ]
});
```

### Performance Monitoring Example

```typescript
// Performance monitoring with custom metrics
import { createPerformanceCollector } from '../validation/core/PerformanceCollector';

const collector = createPerformanceCollector(logger, {
  enabled: true,
  thresholds: {
    maxValidatorDuration: 3000, // 3 seconds
    maxTotalDuration: 20000,    // 20 seconds
    maxMemoryUsage: 50 * 1024 * 1024, // 50MB
    maxDatabaseQueries: 30
  }
});

// In your test
const sessionId = collector.startSession('performance-test-tenant');

// Record custom metrics
collector.recordMetric('custom_operation', 1500, 'ms', 'timing');
collector.recordDatabaseQuery('SELECT * FROM users', 250, 10);
collector.recordNetworkCall('http://api.example.com/data', 'GET', 800, 200, 1024);

const report = collector.endSession();
console.log(`Performance Score: ${report.summary.performanceScore}/100`);
```

### Job Monitoring Example

```typescript
// Monitor async operations
import { createJobMonitor } from '../validation/core/JobMonitor';

const jobMonitor = createJobMonitor(logger, dbHelper, {
  timeout: 180000, // 3 minutes
  pollInterval: 1000 // 1 second
});

// Monitor tenant creation
test('tenant creation with job monitoring', async () => {
  const tenantSlug = 'monitored-tenant';
  
  // Start tenant creation
  await createTenant(tenantSlug);
  
  // Monitor the creation jobs
  const success = await jobMonitor.monitorTenantCreation(tenantSlug);
  expect(success).toBe(true);
  
  // Monitor seeding jobs
  const seedingSuccess = await jobMonitor.monitorTenantSeeding(tenantSlug);
  expect(seedingSuccess).toBe(true);
});
```

## Troubleshooting

### Common Issues

#### 1. Rollback Manager Not Cleaning Up

**Problem**: Tenant data remains after test failure

**Solution**:
```typescript
// Ensure auto-rollback is enabled
const config = {
  rollback: {
    enabled: true,
    autoRollbackOnFailure: true
  }
};

// Or manually trigger rollback
await rollbackManager.autoRollbackOnFailure('tenant-slug', error);
```

#### 2. Job Monitoring Timeouts

**Problem**: Job monitoring times out before completion

**Solution**:
```typescript
// Increase timeout in configuration
const jobMonitor = createJobMonitor(logger, dbHelper, {
  timeout: 300000, // 5 minutes
  pollInterval: 3000 // 3 seconds
});

// Or check Laravel queue workers are running
// php artisan queue:work --daemon
```

#### 3. Performance Collection High Overhead

**Problem**: Performance collection impacts test speed

**Solution**:
```typescript
// Disable expensive collection in fast tests
const collector = createPerformanceCollector(logger, {
  enabled: true,
  collectMemory: false,    // Disable memory tracking
  collectDatabase: false,  // Disable query tracking
  collectNetwork: false    // Disable network tracking
});
```

#### 4. Tenant State Validation Failures

**Problem**: Tenant state validation fails unexpectedly

**Solution**:
```typescript
// Use basic validation for simpler tests
const validator = TenantStateValidators.basic();

// Or customize validation requirements
const validator = createTenantStateValidator({
  requiredConfigs: [
    // Only essential configs
    {
      configKey: 'app.env',
      expectedValue: 'testing',
      required: true,
      description: 'Environment must be testing'
    }
  ],
  schemaValidations: [
    // Only essential tables
    {
      table: 'users',
      columns: ['id', 'email'],
      requiredData: [{ table: 'users', minCount: 1 }]
    }
  ]
});
```

### Debug Mode

Enable detailed logging for troubleshooting:

```bash
# Set debug level logging
VALIDATION_LOG_LEVEL=debug

# Enable all logging outputs
VALIDATION_LOG_OUTPUTS=console,json,file
```

```typescript
// In your test setup
const validationManager = createValidationManager({
  logging: {
    level: 'debug',
    outputs: ['console'],
    metricsEnabled: true
  }
});
```

## Performance Tuning

### Optimization Strategies

#### 1. Selective Component Usage

```typescript
// Only enable needed components
const config = {
  rollback: { enabled: true },
  jobMonitoring: { enabled: false },      // Disable if not using queues
  performance: { enabled: false },        // Disable for fast tests
  tenantState: { enabled: true }
};
```

#### 2. Adjust Polling Intervals

```typescript
// Faster polling for quick tests
const jobMonitor = createJobMonitor(logger, dbHelper, {
  pollInterval: 500,    // 500ms for quick response
  timeout: 30000        // 30 seconds for fast tests
});
```

#### 3. Performance Thresholds

```typescript
// Adjust thresholds based on your environment
const performanceConfig = {
  thresholds: {
    maxValidatorDuration: 10000,  // 10 seconds for slower environments
    maxTotalDuration: 60000,      // 1 minute total
    maxMemoryUsage: 200 * 1024 * 1024, // 200MB for memory-intensive tests
    maxDatabaseQueries: 100       // Allow more queries if needed
  }
};
```

#### 4. Conditional Validation

```typescript
// Skip validation in CI environments for speed
if (process.env.CI === 'true') {
  process.env.SKIP_PRE_TEST_VALIDATION = 'true';
}

// Or use lighter validation in CI
const config = process.env.CI === 'true' 
  ? lightValidationConfig 
  : fullValidationConfig;
```

### Performance Monitoring

Monitor your validation performance:

```typescript
// Track validation performance over time
const collector = createPerformanceCollector(logger, {
  enabled: true,
  reportFormat: 'json',
  outputPath: './performance-reports/'
});

// Analyze trends
// - Which validators are getting slower?
// - Is memory usage increasing?
// - Are database queries multiplying?
```

## Advanced Features

### Custom Rollback Actions

```typescript
// Add complex rollback logic
rollbackManager.addRollbackStep(
  tenantSlug,
  'custom',
  'Complex cleanup operation',
  async () => {
    // Clean up external resources
    await cleanupS3Buckets(tenantSlug);
    await removeRedisKeys(tenantSlug);
    await notifyExternalSystems(tenantSlug);
  },
  { 
    priority: 'high',
    retries: 3,
    timeout: 30000
  }
);
```

### Custom Performance Metrics

```typescript
// Track business-specific metrics
collector.recordMetric('user_creation_time', 1200, 'ms', 'custom');
collector.recordMetric('language_setup_queries', 15, 'count', 'database');
collector.recordMetric('api_calls_per_tenant', 8, 'count', 'network');

// Create performance alerts
if (report.summary.performanceScore < 80) {
  await notifyDevTeam('Performance degradation detected', report);
}
```

### Custom Tenant State Validations

```typescript
// Add domain-specific validations
const customValidator = createTenantStateValidator({
  customValidations: [
    {
      name: 'language-pair-consistency',
      description: 'Ensure language pairs are consistent',
      validator: async (context) => {
        const result = context.helpers.dbHelper.executeArtisan(
          `tinker --execute="\\$tenant = App\\\\Models\\\\Landlord\\\\Tenant::where('slug', '${context.tenant!.slug}')->first(); if (\\$tenant) { \\$tenant->run(function() { \\$pairs = DB::table('language_pairs')->count(); \\$languages = DB::table('languages')->count(); echo (\\$pairs > 0 && \\$languages >= 2) ? 'true' : 'false'; }); } else { echo 'false'; }"`
        );
        return result.trim() === 'true';
      }
    },
    {
      name: 'admin-permissions-complete',
      description: 'Verify admin has all required permissions',
      validator: async (context) => {
        // Complex permission validation logic
        return await validateAdminPermissions(context);
      }
    }
  ]
});
```

### Integration with CI/CD

```yaml
# .github/workflows/e2e-tests.yml
name: E2E Tests with Enhanced Validation

on: [push, pull_request]

jobs:
  e2e-tests:
    runs-on: ubuntu-latest
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup validation environment
        run: |
          echo "VALIDATION_PERFORMANCE_ENABLED=true" >> .env.test
          echo "VALIDATION_PERFORMANCE_REPORT_FORMAT=json" >> .env.test
          echo "VALIDATION_ROLLBACK_ENABLED=true" >> .env.test
      
      - name: Run E2E tests
        run: npm run test:e2e
        
      - name: Upload performance reports
        uses: actions/upload-artifact@v3
        with:
          name: performance-reports
          path: e2e/performance-reports/
          
      - name: Analyze performance trends
        run: npm run analyze:performance
```

## Best Practices

### 1. Validation Strategy

- **Development**: Use complete validation with all features enabled
- **CI/CD**: Use selective validation focusing on critical components
- **Production-like testing**: Enable all monitoring and performance collection

### 2. Rollback Management

- Always create snapshots before major tenant operations
- Use descriptive rollback step descriptions
- Test rollback procedures regularly
- Keep rollback actions idempotent

### 3. Performance Monitoring

- Set realistic thresholds based on your environment
- Monitor trends over time, not just individual runs
- Use performance data to optimize slow validators
- Archive performance reports for historical analysis

### 4. Job Monitoring

- Ensure Laravel queue workers are running
- Monitor job failure rates
- Set appropriate timeouts for different job types
- Use job tags or naming conventions for better tracking

### 5. Tenant State Validation

- Start with basic validation and add complexity gradually
- Use pre-configured validators when possible
- Document custom validation requirements
- Keep validation logic separate from business logic

### 6. Error Handling

```typescript
// Comprehensive error handling
try {
  await validateTenantSetup(testInfo);
} catch (error) {
  if (isValidationError(error)) {
    // Handle validation-specific errors
    logger.error('Validation failed:', error.details);
    
    // Attempt recovery
    if (error.validator === 'tenant-database') {
      await recreateTenantDatabase();
      // Retry validation
    }
  } else {
    // Handle unexpected errors
    logger.error('Unexpected error:', error);
    throw error;
  }
}
```

### 7. Resource Management

```typescript
// Clean up resources after tests
test.afterAll(async () => {
  // Clean up performance sessions
  performanceCollector.cleanupOldSessions();
  
  // Clean up job monitoring sessions
  jobMonitor.cleanupOldSessions();
  
  // Clear rollback snapshots
  rollbackManager.clearAllSnapshots();
});
```

---

For additional support and advanced configuration options, refer to the individual component documentation in the [`validation/`](validation/) directory.