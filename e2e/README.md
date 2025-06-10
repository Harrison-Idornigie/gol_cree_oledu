# End-to-End Tests for Multi-Tenant Laravel Application

This directory contains comprehensive end-to-end tests for the tenant registration flow and multi-tenant functionality using Playwright.

## Overview

The E2E test suite covers:

- **Complete tenant registration flow** from frontend form to backend database
- **Frontend form validation** and user experience
- **API request/response validation** and error handling
- **Backend tenant creation** and database setup verification
- **Tenant isolation** and security testing
- **Path-based tenant identification** verification
- **Multi-browser and mobile testing**

## Test Structure

```
e2e/
├── tests/
│   ├── tenant-registration.spec.ts    # Main registration flow tests
│   └── tenant-isolation.spec.ts       # Tenant isolation and security tests
├── fixtures/
│   └── tenant-fixtures.ts             # Test data and utilities
├── utils/
│   ├── api-helpers.ts                 # API testing utilities
│   ├── database-helpers.ts            # Database management utilities
│   ├── global-setup.ts                # Test environment setup
│   └── global-teardown.ts             # Test environment cleanup
├── playwright.config.ts               # Playwright configuration
├── package.json                       # E2E test dependencies
├── .env.test                          # Test environment variables
└── README.md                          # This file
```

## Prerequisites

1. **Node.js 18+** installed
2. **Laravel backend** running on `http://localhost:8000`
3. **Next.js frontend** running on `http://localhost:3000`
4. **Database** configured for testing (SQLite recommended)

## Setup Instructions

### 1. Install Dependencies

```bash
cd e2e
npm install
```

### 2. Install Playwright Browsers

```bash
npm run install-browsers
```

### 3. Configure Environment

Copy and customize the test environment file:

```bash
cp .env.test .env.test.local
```

Edit `.env.test.local` with your specific configuration:

```env
# Frontend URL
FRONTEND_URL=http://localhost:3000

# Backend URL  
BACKEND_URL=http://localhost:8000

# Test configuration
CLEANUP_TEST_DATA=true
HEADLESS=true
```

### 4. Prepare Backend for Testing

Ensure your Laravel backend is configured for testing:

```bash
cd ../backend
cp .env.example .env.testing
php artisan config:clear --env=testing
php artisan migrate:fresh --env=testing --force
```

### 5. Start Development Servers

Start both frontend and backend servers:

```bash
# Terminal 1: Backend
cd backend
php artisan serve

# Terminal 2: Frontend  
cd frontend
npm run dev
```

## Running Tests

### Run All Tests

```bash
npm run test
```

### Run Tests with UI

```bash
npm run test:ui
```

### Run Tests in Debug Mode

```bash
npm run test:debug
```

### Run Tests in Headed Mode (See Browser)

```bash
npm run test:headed
```

### Run Specific Test File

```bash
npx playwright test tenant-registration.spec.ts
```

### Run Tests on Specific Browser

```bash
npx playwright test --project=chromium
```

## Test Scenarios

### Tenant Registration Flow (`tenant-registration.spec.ts`)

#### ✅ Successful Registration
- Complete registration with valid data
- Auto-generation of tenant slug
- Slug validation and availability checking
- Redirect to tenant dashboard
- Database verification

#### ✅ Form Validation
- Missing required fields
- Invalid email format
- Password confirmation mismatch
- Invalid slug format

#### ✅ Error Handling
- Duplicate slug prevention
- API error responses
- Network failures

#### ✅ API Integration
- Request/response structure validation
- Authentication token handling
- Path-based tenant identification

#### ✅ Edge Cases
- Special characters in names
- Maximum length inputs
- Performance testing

### Tenant Isolation (`tenant-isolation.spec.ts`)

#### ✅ Data Isolation
- Cross-tenant data access prevention
- Session isolation between tenants
- API access control

#### ✅ Security Testing
- SQL injection prevention
- XSS protection
- Invalid tenant slug handling

#### ✅ Concurrent Operations
- Multiple tenant registrations
- Race condition handling

## Test Data Management

### Automatic Cleanup

Tests automatically clean up created data:

- **Test tenants** are prefixed with `e2e-test-{timestamp}-{random}`
- **Cleanup runs** after each test suite
- **Failed test data** can be preserved for debugging

### Manual Cleanup

If needed, manually clean up test data:

```bash
cd ../backend
php artisan tinker --execute="App\Models\Landlord\Tenant::where('slug', 'like', 'e2e-test%')->delete();"
```

## Configuration Options

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `FRONTEND_URL` | `http://localhost:3000` | Frontend application URL |
| `BACKEND_URL` | `http://localhost:8000` | Backend API URL |
| `CLEANUP_TEST_DATA` | `true` | Clean up test data after tests |
| `PRESERVE_FAILED_TEST_DATA` | `true` | Keep data from failed tests |
| `HEADLESS` | `true` | Run browsers in headless mode |
| `DEBUG_API_CALLS` | `false` | Log API requests/responses |

### Browser Configuration

Tests run on multiple browsers by default:
- **Desktop**: Chrome, Firefox, Safari
- **Mobile**: Chrome Mobile, Safari Mobile
- **Branded**: Edge, Chrome

Customize in `playwright.config.ts` as needed.

## Debugging

### View Test Reports

```bash
npm run test:report
```

### Debug Failed Tests

1. **Screenshots** are captured on failure
2. **Videos** are recorded for failed tests
3. **Traces** are available for debugging
4. **Console logs** are captured

### Common Issues

#### Backend Not Accessible
```bash
# Check if Laravel server is running
curl http://localhost:8000/up

# Start Laravel server
cd ../backend && php artisan serve
```

#### Frontend Not Accessible
```bash
# Check if Next.js server is running
curl http://localhost:3000

# Start Next.js server
cd ../frontend && npm run dev
```

#### Database Issues
```bash
# Reset test database
cd ../backend
php artisan migrate:fresh --env=testing --force
```

#### Permission Issues
```bash
# Fix storage permissions
cd ../backend
chmod -R 775 storage bootstrap/cache
```

## Integration with CI/CD

### GitHub Actions Example

```yaml
name: E2E Tests

on: [push, pull_request]

jobs:
  e2e:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup Node.js
        uses: actions/setup-node@v3
        with:
          node-version: '18'
          
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          
      - name: Install dependencies
        run: |
          cd backend && composer install
          cd ../frontend && npm install
          cd ../e2e && npm install
          
      - name: Setup database
        run: |
          cd backend
          php artisan migrate:fresh --env=testing --force
          
      - name: Install Playwright
        run: cd e2e && npx playwright install
        
      - name: Run E2E tests
        run: |
          cd backend && php artisan serve &
          cd frontend && npm run build && npm start &
          cd e2e && npm run test
```

## Best Practices

### Writing Tests

1. **Use descriptive test names** that explain the scenario
2. **Group related tests** in describe blocks
3. **Clean up test data** after each test
4. **Use fixtures** for consistent test data
5. **Mock external services** when appropriate

### Test Data

1. **Use unique identifiers** to avoid conflicts
2. **Generate realistic data** for better testing
3. **Test edge cases** and boundary conditions
4. **Verify both UI and database state**

### Performance

1. **Run tests in parallel** when possible
2. **Use efficient selectors** for better performance
3. **Minimize network requests** in setup
4. **Cache browser contexts** when appropriate

## Contributing

When adding new tests:

1. **Follow existing patterns** in test structure
2. **Add appropriate cleanup** for new test data
3. **Update documentation** for new test scenarios
4. **Test on multiple browsers** before submitting

## Support

For issues with E2E tests:

1. **Check test reports** for detailed failure information
2. **Review console logs** for API errors
3. **Verify environment setup** matches requirements
4. **Run tests individually** to isolate issues
