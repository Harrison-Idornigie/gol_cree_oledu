# Enhanced Testing Guide

This guide covers the enhanced testing system that integrates comprehensive validation, performance monitoring, rollback protection, and role-based permission testing.

## Overview

The enhanced testing system provides:

1. **Enhanced Tenant Setup Validation** - Comprehensive pre-test validation with rollback protection
2. **Performance Monitoring** - Real-time performance metrics and reporting
3. **Job Monitoring** - Tracking of async tenant creation and seeding operations
4. **Permission Testing** - Role-based permission validation using backend systems
5. **Enhanced Error Handling** - Actionable error messages with diagnostic information
6. **Timeout Management** - Proper handling of long-running operations

## Files Created/Enhanced

### Core Test Files
- [`team-word-controller-enhanced.spec.ts`](tests/Tenant/backend/API/Word/team-word-controller-enhanced.spec.ts) - Enhanced version of the original test
- [`permission-test-helpers.ts`](utils/permission-test-helpers.ts) - Comprehensive permission testing utilities

### Configuration
- [`.env.test`](.env.test) - Updated with enhanced timeouts and validation configuration

### Key Features Demonstrated

## 1. Enhanced Validation Integration

```typescript
// Before each test, run enhanced validation
test.beforeEach(async ({ }, testInfo) => {
  await validateEnhancedTenantSetup(testInfo);
  performanceCollector.startPhase(`test-${testInfo.title}`);
});
```

## 2. Rollback Protection

```typescript
// Create rollback snapshots
rollbackManager.createSnapshot(tenant1Slug);
rollbackManager.createSnapshot(tenant2Slug);

// Add rollback steps
rollbackManager.addRollbackStep(tenant1Slug, 'custom', 
  `Delete test word ${wordId}`, 
  async () => {
    await apiHelper.tenantRequest(tenant1Slug, `/team/words/${wordId}`, {
      method: 'DELETE',
      token: tenant1Token
    });
  });

// Auto-rollback on failure
await rollbackManager.autoRollbackOnFailure(tenantSlug, error);
```

## 3. Performance Monitoring

```typescript
// Start performance session
const sessionId = performanceCollector.startSession('enhanced-word-controller-tests');
performanceCollector.startPhase('tenant-setup');

// Record metrics
performanceCollector.recordMetric('word_create_duration', createDuration, 'ms', 'timing');
performanceCollector.recordNetworkCall('/team/words', 'POST', duration, status, size);

// Generate report
const report = performanceCollector.endSession();
```

## 4. Job Monitoring

```typescript
// Monitor tenant creation jobs
const tenant1JobMonitoringPromise = jobMonitor.monitorTenantCreation(tenant1Slug);
const tenant1Registration = await apiHelper.registerTenantAdmin(tenant1Data);
await tenant1JobMonitoringPromise;
```

## 5. Role-Based Permission Testing

```typescript
import { 
  PermissionTestHelper, 
  WordControllerPermissionTests, 
  WordControllerIsolationTests 
} from '@/utils/permission-test-helpers';

// Test role-based permissions
const permissionHelper = new PermissionTestHelper(apiHelper, dbHelper, logger);
const permissionResults = await permissionHelper.testRoleBasedPermissions(
  tenant1Slug,
  { admin: adminToken, team: teamToken, student: studentToken },
  WordControllerPermissionTests
);

// Test tenant isolation
const isolationResults = await permissionHelper.testTenantIsolation(
  { slug: tenant1Slug, token: tenant1Token },
  { slug: tenant2Slug, token: tenant2Token },
  WordControllerIsolationTests
);
```

## Configuration Options

### Environment Variables

The `.env.test` file includes extensive configuration options:

#### Timeouts
```bash
# Enhanced Test Timeouts (in milliseconds)
DEFAULT_TIMEOUT=60000
API_TIMEOUT=60000
TENANT_CREATION_TIMEOUT=180000
BULK_OPERATION_TIMEOUT=120000
```

#### Validation Configuration
```bash
# Enhanced Validation Configuration
ENABLE_ENHANCED_VALIDATION=true
VALIDATION_ENVIRONMENT_ENABLED=true
VALIDATION_DATABASE_ENABLED=true
VALIDATION_TENANT_ENABLED=true
VALIDATION_SEEDING_ENABLED=true
```

#### Performance Monitoring
```bash
# Performance Monitoring Configuration
PERFORMANCE_MONITORING_ENABLED=true
PERFORMANCE_COLLECT_MEMORY=true
PERFORMANCE_COLLECT_DATABASE=true
PERFORMANCE_COLLECT_NETWORK=true
PERFORMANCE_MAX_VALIDATOR_DURATION=10000
PERFORMANCE_MAX_TOTAL_DURATION=180000
```

#### Rollback Protection
```bash
# Rollback Protection Configuration
ENABLE_ROLLBACK_PROTECTION=true
AUTO_ROLLBACK_ON_FAILURE=true
PRESERVE_SNAPSHOTS_ON_SUCCESS=false
```

#### Permission Testing
```bash
# Permission Testing Configuration
ENABLE_PERMISSION_TESTING=true
TEST_ROLE_ISOLATION=true
TEST_TENANT_ISOLATION=true
TEST_PERMISSION_INHERITANCE=true
```

## Running the Enhanced Tests

### Prerequisites

1. Ensure all validation components are properly installed
2. Backend should be running with proper tenant and permission setup
3. Database should be accessible and properly configured

### Running Individual Tests

```bash
# Run the enhanced word controller test
npx playwright test team-word-controller-enhanced.spec.ts

# Run with specific configuration
ENABLE_ENHANCED_VALIDATION=true npx playwright test team-word-controller-enhanced.spec.ts

# Run with performance monitoring
PERFORMANCE_MONITORING_ENABLED=true npx playwright test team-word-controller-enhanced.spec.ts
```

### Running with Different Validation Levels

```bash
# Skip validation for rapid testing
SKIP_PRE_TEST_VALIDATION=true npx playwright test

# Run only tenant validation
VALIDATION_ENVIRONMENT_ENABLED=false VALIDATION_DATABASE_ENABLED=false npx playwright test

# Enable detailed logging
VERBOSE_LOGGING=true DEBUG_API_CALLS=true npx playwright test
```

## Understanding Test Output

### Performance Reports

```
📊 Performance Report
Session: perf_1671789123456_abc123
Total Duration: 15432ms
Performance Score: 85/100
Validators: 12
Fastest: environment-health (123ms)
Slowest: tenant-seeding (4567ms)
⚠️ 2 performance warnings
```

### Validation Results

```
🔍 Starting enhanced tenant state validation for: test-tenant-slug
⚙️ Validating tenant configuration for: test-tenant-slug
✅ Config validated: app.env = testing
📊 Validating tenant database schema for: test-tenant-slug
✅ Schema validated for table: users
🌱 Validating seeding for tenant: test-tenant-slug
✅ All 4 required tables have sufficient seed data
```

### Permission Test Results

```
🧪 Testing permission: list_words
✅ Permission test passed: list_words
🧪 Testing permission: create_word
✅ Permission test passed: create_word
📊 Permission testing complete: 6 passed, 0 failed
```

### Rollback Operations

```
📸 Created rollback snapshot for tenant: test-tenant-slug
📝 Added rollback step for test-tenant-slug: Delete test word 123
🔄 Starting rollback for tenant: test-tenant-slug
🔄 Rolling back step: Delete test word 123
✅ Successfully rolled back: Delete test word 123
✅ Completed rollback for tenant: test-tenant-slug
```

## Best Practices

### 1. Use Enhanced Validation Selectively

```typescript
// For critical tests, use full validation
test.beforeEach(async ({ }, testInfo) => {
  await validateEnhancedTenantSetup(testInfo);
});

// For rapid iteration, skip validation
test.beforeEach(async ({ }, testInfo) => {
  if (!process.env.SKIP_PRE_TEST_VALIDATION) {
    await validateEnhancedTenantSetup(testInfo);
  }
});
```

### 2. Monitor Performance Appropriately

```typescript
// Monitor performance for complex operations
test('complex bulk operation', async ({ page }) => {
  performanceCollector.startValidator('bulk-operation-performance');
  
  // ... test logic ...
  
  performanceCollector.endValidator({
    validator: 'bulk-operation-performance',
    status: 'passed',
    message: 'Bulk operation completed',
    duration: Date.now() - startTime,
    timestamp: new Date()
  });
});
```

### 3. Use Rollback Protection for Data Safety

```typescript
// Create snapshots for tests that modify data
rollbackManager.createSnapshot(tenantSlug);

// Add specific cleanup steps
rollbackManager.addRollbackStep(tenantSlug, 'custom', 
  'Clean up test data', 
  async () => { /* cleanup logic */ });
```

### 4. Comprehensive Permission Testing

```typescript
// Test all relevant permission combinations
const permissionResults = await permissionHelper.testRoleBasedPermissions(
  tenantSlug,
  tokens,
  [
    ...WordControllerPermissionTests,
    ...CustomPermissionTests
  ]
);
```

## Troubleshooting

### Common Issues

1. **Validation Timeouts**
   - Increase validation timeouts in `.env.test`
   - Check backend connectivity
   - Verify database accessibility

2. **Permission Test Failures**
   - Ensure roles are properly seeded
   - Check permission assignments
   - Verify token validity

3. **Performance Warnings**
   - Review slow validators
   - Check memory usage
   - Optimize database queries

4. **Rollback Failures**
   - Check database permissions
   - Verify tenant isolation
   - Review cleanup logic

### Debug Mode

Enable comprehensive debugging:

```bash
VERBOSE_LOGGING=true \
DEBUG_API_CALLS=true \
ENABLE_DETAILED_ERROR_REPORTING=true \
npx playwright test team-word-controller-enhanced.spec.ts
```

## Integration with CI/CD

### GitHub Actions Example

```yaml
- name: Run Enhanced E2E Tests
  env:
    ENABLE_ENHANCED_VALIDATION: true
    PERFORMANCE_MONITORING_ENABLED: true
    AUTO_ROLLBACK_ON_FAILURE: true
  run: npx playwright test team-word-controller-enhanced.spec.ts
```

### Performance Thresholds

Set performance gates in CI:

```bash
# Fail build if performance score is below threshold
PERFORMANCE_MIN_SCORE=80 npx playwright test
```

## Future Enhancements

1. **HTML Performance Reports** - Generate detailed HTML reports
2. **Database Query Analysis** - Detailed query performance monitoring  
3. **Advanced Role Creation** - Dynamic test user creation with specific roles
4. **Parallel Test Optimization** - Enhanced tenant isolation for parallel execution
5. **Custom Validation Rules** - Framework for domain-specific validations

## Contributing

When adding new enhanced tests:

1. Follow the established patterns for validation integration
2. Add appropriate performance monitoring
3. Include rollback protection for data modifications
4. Test permission scenarios comprehensively
5. Update this documentation with new features

## Support

For issues with the enhanced testing system:

1. Check the validation logs for detailed error information
2. Review performance reports for bottlenecks
3. Verify configuration in `.env.test`
4. Check backend logs for permission-related issues