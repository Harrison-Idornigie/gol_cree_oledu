# Pre-Test Validation System

This comprehensive validation system ensures that your e2e tenant testing infrastructure is properly set up before tests run, preventing failures due to incomplete tenant seeding and environment issues.

## Overview

The validation system addresses the primary pain point of **tests running against tenants that aren't fully seeded with required data** by implementing:

- **Fail-fast validation** that stops test execution immediately on critical failures
- **Basic data completeness checks** for essential tables (languages, roles, permissions)
- **Modular validator architecture** with pluggable validators
- **Integration with existing Playwright configuration** and test utilities

## Quick Start

### 1. Update Playwright Configuration

Replace your global setup in `playwright.config.ts`:

```typescript
// Before
globalSetup: require.resolve('./utils/global-setup.ts'),

// After  
globalSetup: require.resolve('./utils/enhanced-global-setup.ts'),
```

### 2. Add Validation to Tests

```typescript
import { validateTenantSetup } from '../utils/validation-helpers';

test.beforeEach(async ({ page }, testInfo) => {
  // Run pre-test validation
  await validateTenantSetup(testInfo);
  
  // Your existing test setup
  await page.goto('/register-org');
});
```

### 3. Environment Configuration

Set environment variables to control validation:

```bash
# Skip validation entirely (useful for debugging)
export SKIP_PRE_TEST_VALIDATION=true

# Enable debug logging
export NODE_ENV=test
```

## Architecture

### Validation Phases

The system runs validation in four phases:

1. **Environment**: Backend/frontend health, database connectivity
2. **Database**: Schema validation, migration status, cleanup
3. **Tenant**: Tenant database setup, admin user creation, isolation
4. **Seeding**: Data completeness validation (languages, roles, permissions)

### Key Components

```
e2e/validation/
├── core/
│   ├── ValidationTypes.ts      # Type definitions
│   ├── ValidationResults.ts    # Results aggregation
│   ├── ValidationPipeline.ts   # Execution orchestration
│   └── ValidationManager.ts    # Main coordinator
├── validators/
│   ├── SeedingValidator.ts     # Data completeness validation
│   ├── EnvironmentValidators.ts # Health checks
│   └── TenantValidators.ts     # Tenant-specific validation
├── logging/
│   └── ValidationLogger.ts    # Structured logging
├── integration/
│   └── PlaywrightIntegrator.ts # Playwright integration
└── config/
    └── validation.config.ts   # Configuration
```

## Configuration

### Default Configuration

```typescript
{
  phases: {
    seeding: {
      enabled: true,
      requiredTables: ['languages', 'roles', 'permissions'],
      minRecordCounts: {
        languages: 1,
        roles: 3,      // admin, student, team
        permissions: 10 // basic permissions
      }
    }
  },
  failFast: true,
  logging: {
    level: 'info',
    outputs: ['console', 'json']
  }
}
```

### Customizing Validation

```typescript
import { createValidationManager } from './validation/core/ValidationManager';

const customConfig = {
  phases: {
    seeding: {
      enabled: true,
      requiredTables: ['languages', 'roles'],
      minRecordCounts: {
        languages: 2,
        roles: 5
      }
    }
  }
};

const validator = createValidationManager(customConfig);
```

## Usage Examples

### Basic Usage

```typescript
import { validateTenantSetup } from '../utils/validation-helpers';

test.beforeEach(async ({ }, testInfo) => {
  await validateTenantSetup(testInfo);
});
```

### Validate Specific Tenant

```typescript
import { validateTenantBySlug } from '../utils/validation-helpers';

test('should work with validated tenant', async ({ page }) => {
  await validateTenantBySlug('my-test-tenant');
  
  // Now safe to test tenant-specific features
  await page.goto('/my-test-tenant/admin/languages');
});
```

### Environment-Only Validation

```typescript
import { validateEnvironment } from '../utils/validation-helpers';

test.beforeAll(async () => {
  await validateEnvironment();
});
```

### Skip Validation Conditionally

```typescript
import { shouldSkipValidation } from '../utils/validation-helpers';

test.beforeEach(async ({ }, testInfo) => {
  if (!shouldSkipValidation()) {
    await validateTenantSetup(testInfo);
  }
});
```

## Validation Results

### Success Output

```
🚀 Starting pre-test validation pipeline
✅ backend-health: Backend service is accessible
✅ database-connectivity: Database connectivity verified  
✅ basic-seeding-validation: All 3 required tables have sufficient seed data
✅ Pre-test validation completed successfully
```

### Failure Output

```
🚀 Starting pre-test validation pipeline
❌ basic-seeding-validation: Insufficient seed data in 2 table(s): roles, permissions
❌ Pre-test validation failed

Found 1 validation failures:
  1. basic-seeding-validation: Insufficient seed data in 2 table(s): roles, permissions
     💡 Suggestion: Run database seeding: php artisan db:seed --env=testing
```

## Integration with Existing Tests

The validation system integrates seamlessly with your existing test structure:

### Tenant Registration Tests

```typescript
// Your existing test
test('should complete tenant registration', async ({ page }) => {
  const testData = fixtures.generateValidTenantData();
  
  await fillRegistrationForm(page, testData);
  await page.click(submitButton);
  
  // With validation, you can be confident that:
  // - Backend is healthy
  // - Database is ready  
  // - Required seed data exists
  await expect(page).toHaveURL(/\/admin$/);
});
```

### Word Controller Tests

```typescript
// Your existing word tests
test('should create word with proper validation', async ({ page }) => {
  // Validation ensures languages table has data
  const wordData = {
    language_id: 1, // Safe to use because validation confirmed languages exist
    text: 'test-word'
  };
  
  const response = await apiHelper.tenantRequest(slug, '/team/words', {
    method: 'POST',
    body: wordData,
    token
  });
  
  expect(response.status).toBe(201);
});
```

## Troubleshooting

### Common Issues

1. **Backend not accessible**
   ```
   ❌ backend-health: Backend health check failed
   💡 Suggestion: Start Laravel server: cd backend && php artisan serve
   ```

2. **Database connectivity failed**
   ```
   ❌ database-connectivity: Database connectivity test failed
   💡 Suggestion: Check database configuration in .env.testing
   ```

3. **Insufficient seed data**
   ```
   ❌ basic-seeding-validation: Insufficient seed data in roles table
   💡 Suggestion: Run database seeding: php artisan db:seed --env=testing
   ```

### Debug Mode

Enable detailed logging:

```bash
export NODE_ENV=test
```

This will show debug information for each validation step.

### Skip Validation

For debugging or CI issues:

```bash
export SKIP_PRE_TEST_VALIDATION=true
```

## Performance

- **Environment validation**: ~2-5 seconds
- **Tenant validation**: ~3-8 seconds  
- **Seeding validation**: ~1-3 seconds per tenant

The system tracks performance metrics and will warn about slow validators.

## Extending the System

### Custom Validators

```typescript
import { IValidator, ValidationContext, ValidationResult } from './core/ValidationTypes';

export class CustomValidator implements IValidator {
  name = 'custom-validator';
  isCritical = true;

  async execute(context: ValidationContext): Promise<ValidationResult> {
    // Your validation logic
    return {
      validator: this.name,
      status: 'passed',
      message: 'Custom validation passed',
      duration: 100,
      timestamp: new Date()
    };
  }

  getDependencies(): string[] {
    return ['database-connectivity'];
  }
}
```

### Register Custom Validator

```typescript
const validationManager = createValidationManager();
validationManager.pipeline.registerValidator(new CustomValidator());
```

## Migration Guide

### From Existing Tests

1. **Update global setup**:
   ```typescript
   // playwright.config.ts
   globalSetup: require.resolve('./utils/enhanced-global-setup.ts'),
   ```

2. **Add validation to test setup**:
   ```typescript
   test.beforeEach(async ({ }, testInfo) => {
     await validateTenantSetup(testInfo);
     // Your existing setup...
   });
   ```

3. **Update environment variables**:
   ```bash
   # .env.test
   SKIP_PRE_TEST_VALIDATION=false
   ```

### Backward Compatibility

The system is designed to be backward compatible:
- Existing tests continue to work without modification
- Validation can be disabled via environment variable
- Original global setup is enhanced, not replaced

## Benefits

### Immediate Benefits
- ✅ Eliminate test failures due to incomplete tenant seeding
- ✅ Faster debugging with detailed validation reports  
- ✅ Consistent test environment setup across runs
- ✅ Clear failure attribution between setup vs test logic

### Long-term Benefits
- ✅ Improved test reliability and developer confidence
- ✅ Reduced debugging time for intermittent test failures
- ✅ Better CI/CD pipeline stability
- ✅ Foundation for more sophisticated validation rules