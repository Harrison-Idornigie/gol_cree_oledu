import { DatabaseHelper } from '../utils/database-helpers';

/**
 * Test Fixtures for Tenant Registration E2E Tests
 * 
 * Provides reusable test data and utilities for consistent testing
 * across different test scenarios.
 */
export class TenantFixtures {
  private dbHelper: DatabaseHelper;

  constructor() {
    this.dbHelper = new DatabaseHelper();
  }

  /**
   * Generate valid tenant registration data
   */
  generateValidTenantData(overrides: Partial<TenantRegistrationData> = {}): TenantRegistrationData {
    const tenantSlug = this.dbHelper.generateTestTenantSlug();
    const adminEmail = this.dbHelper.generateTestAdminEmail(tenantSlug);

    return {
      organizationName: 'Test School District',
      organizationSlug: tenantSlug,
      organizationDescription: 'A test school district for language learning',
      adminName: 'John Admin',
      adminEmail: adminEmail,
      password: 'TestPassword123!',
      passwordConfirmation: 'TestPassword123!',
      ...overrides
    };
  }

  /**
   * Generate invalid tenant registration data for testing validation
   */
  generateInvalidTenantData(type: 'missing_required' | 'invalid_email' | 'password_mismatch' | 'invalid_slug'): TenantRegistrationData {
    const base = this.generateValidTenantData();

    switch (type) {
      case 'missing_required':
        return {
          ...base,
          organizationName: '',
          adminName: '',
          adminEmail: ''
        };

      case 'invalid_email':
        return {
          ...base,
          adminEmail: 'invalid-email-format'
        };

      case 'password_mismatch':
        return {
          ...base,
          password: 'TestPassword123!',
          passwordConfirmation: 'DifferentPassword456!'
        };

      case 'invalid_slug':
        return {
          ...base,
          organizationSlug: 'Invalid Slug With Spaces!'
        };

      default:
        return base;
    }
  }

  /**
   * Generate data for duplicate slug testing
   */
  async generateDuplicateSlugData(): Promise<{
    existingTenant: TenantRegistrationData;
    duplicateTenant: TenantRegistrationData;
  }> {
    const existingTenant = this.generateValidTenantData({
      organizationName: 'Existing School District',
      organizationSlug: 'existing-school'
    });

    const duplicateTenant = this.generateValidTenantData({
      organizationName: 'Another School District',
      organizationSlug: 'existing-school', // Same slug
      adminEmail: this.dbHelper.generateTestAdminEmail('duplicate-test')
    });

    return { existingTenant, duplicateTenant };
  }

  /**
   * Generate test data for edge cases
   */
  generateEdgeCaseData(): {
    minimalData: TenantRegistrationData;
    maximalData: TenantRegistrationData;
    specialCharacters: TenantRegistrationData;
  } {
    return {
      // Minimal valid data
      minimalData: this.generateValidTenantData({
        organizationName: 'A',
        adminName: 'A',
        organizationDescription: ''
      }),

      // Maximal data (testing length limits)
      maximalData: this.generateValidTenantData({
        organizationName: 'A'.repeat(255),
        adminName: 'B'.repeat(255),
        organizationDescription: 'C'.repeat(1000)
      }),

      // Special characters in names
      specialCharacters: this.generateValidTenantData({
        organizationName: 'École Française & International School',
        adminName: 'José María García-López',
        organizationDescription: 'A school with special characters: àáâãäåæçèéêë'
      })
    };
  }

  /**
   * Get expected validation error messages
   */
  getExpectedValidationErrors(): Record<string, Record<string, string>> {
    return {
      missing_required: {
        'tenant.name': 'The tenant.name field is required.',
        'admin.name': 'The admin.name field is required.',
        'admin.email': 'The admin.email field is required.'
      },
      invalid_email: {
        'admin.email': 'The admin.email field must be a valid email address.'
      },
      password_mismatch: {
        'admin.password_confirmation': 'The admin.password_confirmation field must match admin.password.'
      },
      duplicate_slug: {
        'tenant.slug': 'This organization slug is already taken. Please choose a different one.'
      },
      invalid_slug: {
        'tenant.slug': 'Organization slug must be 3-50 characters, contain only lowercase letters, numbers, and hyphens, and start/end with alphanumeric characters'
      }
    };
  }

  /**
   * Get expected success response structure
   */
  getExpectedSuccessResponse(): any {
    return {
      success: true,
      message: expect.any(String),
      data: {
        token: expect.any(String),
        user: {
          id: expect.any(Number),
          name: expect.any(String),
          email: expect.any(String),
          role: expect.any(String),
          email_verified_at: expect.any(String),
          tenant_id: expect.any(Number),
          tenant: {
            id: expect.any(Number),
            name: expect.any(String),
            slug: expect.any(String),
            status: 'active'
          }
        }
      }
    };
  }

  /**
   * Clean up test data
   */
  async cleanup(): Promise<void> {
    await this.dbHelper.cleanupAllTestData();
  }
}

/**
 * Type definition for tenant registration data
 */
export interface TenantRegistrationData {
  organizationName: string;
  organizationSlug?: string;
  organizationDescription?: string;
  adminName: string;
  adminEmail: string;
  password: string;
  passwordConfirmation: string;
}

/**
 * Type definition for form field selectors
 */
export interface FormSelectors {
  organizationName: string;
  organizationSlug: string;
  organizationDescription: string;
  adminName: string;
  adminEmail: string;
  password: string;
  passwordConfirmation: string;
  submitButton: string;
  errorMessage: string;
  successMessage: string;
}

/**
 * Form selectors for the tenant registration page
 */
export const FORM_SELECTORS: FormSelectors = {
  organizationName: '#organizationName',
  organizationSlug: '#organizationSlug',
  organizationDescription: '#organizationDescription',
  adminName: '#adminName',
  adminEmail: '#adminEmail',
  password: '#password',
  passwordConfirmation: '#passwordConfirmation',
  submitButton: 'button[type="submit"]',
  errorMessage: '[class*="text-red"]',
  successMessage: '[class*="text-green"]'
};
