# Team Word Controller E2E Tests

This document describes the comprehensive end-to-end test suite for the TeamWordController API endpoints. The tests follow the existing multitenant test structure and provide thorough coverage of all word management functionality.

## Test Overview

### Test File Location
- **Main Test File**: `e2e/tests/team-word-controller.spec.ts`
- **Word Fixtures**: `e2e/fixtures/word-fixtures.ts`
- **Supporting Files**: Uses existing `tenant-fixtures.ts`, `api-helpers.ts`, and `database-helpers.ts`

### Test Coverage

The test suite covers **21 API endpoints** with comprehensive testing across multiple categories:

#### 1. Authentication & Authorization Tests
- ✅ Unauthenticated requests return 401
- ✅ Non-team users cannot access team endpoints
- ✅ Valid team authentication allows access
- ✅ Different membership levels access validation

#### 2. Tenant Isolation Tests
- ✅ Teams can only access words from their own tenant
- ✅ Cross-tenant word access blocked (404 responses)
- ✅ Tenant context properly validated in all endpoints
- ✅ Multiple tenant isolation verification

#### 3. Data Validation Tests
- ✅ Invalid parameters return 422 with proper error messages
- ✅ Required fields validation
- ✅ Parameter type validation (integers, strings, booleans)
- ✅ Pagination parameter limits (per_page max 100)
- ✅ File upload validation for audio files

#### 4. Functional Testing - CRUD Operations
- ✅ `GET /api/{tenant}/team/words` - list words with filtering
- ✅ `GET /api/{tenant}/team/words/{id}` - get single word
- ✅ `POST /api/{tenant}/team/words` - create word
- ✅ `PUT /api/{tenant}/team/words/{id}` - update word
- ✅ `DELETE /api/{tenant}/team/words/{id}` - delete word

#### 5. Bulk Operations Testing
- ✅ `POST /api/{tenant}/team/words/bulk` - bulk import
- ✅ `DELETE /api/{tenant}/team/words/bulk-delete` - bulk delete
- ✅ `PUT /api/{tenant}/team/words/bulk` - bulk update

#### 6. Translation Management
- ✅ `POST /api/{tenant}/team/words/{id}/translations` - add translation
- ✅ `PUT /api/{tenant}/team/words/{id}/translations/{translation}` - update translation
- ✅ `DELETE /api/{tenant}/team/words/{id}/translations/{translation}` - delete translation

#### 7. Audio Upload Testing
- ✅ `POST /api/{tenant}/team/words/{id}/audio` - audio upload
- ✅ `POST /api/{tenant}/team/words/{id}/translations/{translation}/audio` - translation audio upload

#### 8. Filter & Search Testing
- ✅ Filtering by language_id, search, part_of_speech
- ✅ Sorting by text, created_at, updated_at
- ✅ Pagination with proper metadata
- ✅ Audio URL inclusion when pronunciation files exist

#### 9. Word Constraint System
- ✅ `GET /api/{tenant}/team/words/available/{exerciseType?}` - get available words
- ✅ `POST /api/{tenant}/team/words/validate-constraints` - validate constraints

#### 10. Edge Cases & Error Handling
- ✅ Empty result sets return proper empty arrays
- ✅ Non-existent word IDs return 404
- ✅ Batch requests with invalid word IDs
- ✅ Large batch request limits
- ✅ Audio file upload edge cases

#### 11. Response Format Validation
- ✅ All responses follow BaseAPIController format
- ✅ Proper HTTP status codes (200, 404, 422, 401, 403)
- ✅ Data structure matches expected interfaces
- ✅ Audio URLs properly formatted when present

## API Endpoints Tested

### Core CRUD Operations
| Method | Endpoint | Description | Status Codes |
|--------|----------|-------------|--------------|
| GET | `/api/{tenant}/team/words` | List words with filtering/pagination | 200, 401, 403 |
| GET | `/api/{tenant}/team/words/{id}` | Get single word details | 200, 404, 401 |
| POST | `/api/{tenant}/team/words` | Create new word | 201, 422, 401 |
| PUT | `/api/{tenant}/team/words/{id}` | Update existing word | 200, 404, 422, 401 |
| DELETE | `/api/{tenant}/team/words/{id}` | Delete word | 204, 404, 401 |

### Bulk Operations
| Method | Endpoint | Description | Status Codes |
|--------|----------|-------------|--------------|
| POST | `/api/{tenant}/team/words/bulk` | Bulk create words | 200, 422, 401 |
| PUT | `/api/{tenant}/team/words/bulk` | Bulk update words | 200, 422, 401 |
| POST | `/api/{tenant}/team/words/bulk-delete` | Bulk delete words | 200, 422, 401 |

### Translation Management
| Method | Endpoint | Description | Status Codes |
|--------|----------|-------------|--------------|
| POST | `/api/{tenant}/team/words/{id}/translations` | Add translation | 201, 422, 404, 401 |
| PUT | `/api/{tenant}/team/words/{id}/translations/{translation}` | Update translation | 200, 404, 422, 401 |
| DELETE | `/api/{tenant}/team/words/{id}/translations/{translation}` | Delete translation | 204, 404, 401 |

### Audio Management
| Method | Endpoint | Description | Status Codes |
|--------|----------|-------------|--------------|
| POST | `/api/{tenant}/team/words/{id}/audio` | Upload word audio | 200, 422, 404, 401 |
| POST | `/api/{tenant}/team/words/{id}/translations/{translation}/audio` | Upload translation audio | 200, 422, 404, 401 |

### Constraint System
| Method | Endpoint | Description | Status Codes |
|--------|----------|-------------|--------------|
| GET | `/api/{tenant}/team/words/available/{exerciseType?}` | Get available words for exercises | 200, 401 |
| POST | `/api/{tenant}/team/words/validate-constraints` | Validate word constraints | 200, 422, 401 |

## Test Data Structure

### Word Data Structure
```typescript
interface WordData {
  language_id: number;
  text: string;
  pronunciation_key?: string;
  part_of_speech: string;
  metadata?: {
    difficulty?: string;
    tags?: string[];
    frequency?: string;
    has_audio?: boolean;
  };
}
```

### Translation Data Structure
```typescript
interface TranslationData {
  language_id: number;
  text: string;
  pronunciation_key?: string;
  context_notes?: string;
  usage_examples?: string[];
  translation_order?: number;
}
```

## Running the Tests

### Prerequisites
1. Ensure backend Laravel application is running
2. Database is properly configured for testing
3. Tenant system is functional
4. Node.js and npm/yarn installed for e2e tests

### Environment Setup
Create or verify `.env.test` file in e2e directory:
```env
API_BASE_URL=http://localhost:8000/api
TEST_TENANT_PREFIX=e2e-test
TEST_ADMIN_EMAIL_DOMAIN=e2e-test.local
DEBUG_API_CALLS=false
```

### Running All Tests
```bash
cd e2e
npm test -- team-word-controller.spec.ts
```

### Running Specific Test Categories
```bash
# Authentication tests only
npm test -- team-word-controller.spec.ts -g "Authentication & Authorization"

# Tenant isolation tests
npm test -- team-word-controller.spec.ts -g "Tenant Isolation"

# CRUD operations
npm test -- team-word-controller.spec.ts -g "Functional Testing"

# Bulk operations
npm test -- team-word-controller.spec.ts -g "Bulk Operations"
```

### Running with Debug Output
```bash
DEBUG_API_CALLS=true npm test -- team-word-controller.spec.ts
```

## Test Architecture

### Fixtures and Helpers
- **TenantFixtures**: Manages tenant creation and authentication
- **WordFixtures**: Generates test word data and validation scenarios
- **ApiHelper**: Handles API requests with proper tenant routing
- **DatabaseHelper**: Manages test database cleanup and verification

### Test Data Management
- Automatic cleanup of test data after each test suite
- Unique test data generation to avoid conflicts
- Proper tenant isolation during testing
- Mock audio file creation for upload testing

### Error Handling
- Comprehensive validation error testing
- HTTP status code verification
- Response format validation
- Edge case coverage

## Performance Considerations

### Pagination Testing
- Tests pagination limits (max 100 per page)
- Verifies pagination metadata accuracy
- Tests performance with large datasets

### Bulk Operations Testing
- Tests batch size limits
- Verifies transaction handling
- Tests rollback scenarios for failed operations

### Audio Upload Testing
- File size validation (max 10MB)
- Supported format validation (MP3, WAV)
- Upload performance testing

## Security Testing

### Authentication
- Token validation across all endpoints
- Role-based access control testing
- Session isolation between tenants

### Data Isolation
- Cross-tenant access prevention
- SQL injection prevention in tenant identification
- Input sanitization validation

### File Upload Security
- MIME type validation
- File size restrictions
- Malicious file upload prevention

## Maintenance and Updates

### Adding New Tests
1. Use existing fixtures for consistent test data
2. Follow the established test naming conventions
3. Include proper cleanup in test teardown
4. Document new test scenarios in this README

### Updating Fixtures
1. Modify `WordFixtures` class for new data scenarios
2. Update type definitions as needed
3. Ensure backward compatibility with existing tests

### Performance Monitoring
- Monitor test execution time
- Update timeout configurations as needed
- Optimize database cleanup procedures

## Troubleshooting

### Common Issues
1. **Database Connection Errors**: Verify backend database configuration
2. **Tenant Creation Failures**: Check tenant seeding and migration status
3. **Audio Upload Failures**: Verify file permissions and storage configuration
4. **Authentication Errors**: Ensure proper token generation and validation

### Debug Tools
- Enable `DEBUG_API_CALLS` for request/response logging
- Use Playwright's built-in debugging tools
- Check Laravel logs for backend errors
- Verify database state between tests

## Integration with CI/CD

### Automated Testing
The test suite is designed to run in CI/CD environments:
- No external dependencies beyond database
- Automatic cleanup prevents test pollution
- Deterministic test data generation
- Comprehensive error reporting

### Test Reporting
- JUnit XML output for CI integration
- Coverage reporting for test completeness
- Performance metrics for regression detection
- Detailed error logs for debugging