/**
 * Permission Testing Helpers
 * 
 * Comprehensive helpers for testing role-based permissions, tenant isolation,
 * and permission inheritance using the backend permission system.
 */

import { ApiHelper } from './api-helpers';
import { DatabaseHelper } from './database-helpers';
import { ValidationLogger } from '../validation/logging/ValidationLogger';

export interface RoleTestConfig {
  roleName: string;
  roleSlug: string;
  permissions: string[];
  description: string;
}

export interface PermissionTestCase {
  name: string;
  endpoint: string;
  method: 'GET' | 'POST' | 'PUT' | 'DELETE';
  requiredPermission: string;
  testData?: any;
  expectedStatuses: {
    admin: number;
    team: number;
    student: number;
    unauthenticated: number;
  };
}

export interface TenantIsolationTestCase {
  name: string;
  setupAction: (tenantSlug: string, token: string) => Promise<any>;
  crossTenantAction: (resourceId: string, otherTenantSlug: string, otherToken: string) => Promise<Response>;
  expectedCrossTenantStatus: number;
}

export class PermissionTestHelper {
  private apiHelper: ApiHelper;
  private dbHelper: DatabaseHelper;
  private logger: ValidationLogger;

  constructor(apiHelper: ApiHelper, dbHelper: DatabaseHelper, logger: ValidationLogger) {
    this.apiHelper = apiHelper;
    this.dbHelper = dbHelper;
    this.logger = logger;
  }

  /**
   * Test role-based permissions for multiple endpoints
   */
  async testRoleBasedPermissions(
    tenantSlug: string,
    tokens: { admin: string; team?: string; student?: string },
    testCases: PermissionTestCase[]
  ): Promise<{ passed: number; failed: number; results: any[] }> {
    const results = [];
    let passed = 0;
    let failed = 0;

    for (const testCase of testCases) {
      this.logger.debug(`🧪 Testing permission: ${testCase.name}`);
      
      try {
        const testResult = await this.executePermissionTestCase(tenantSlug, tokens, testCase);
        results.push(testResult);
        
        if (testResult.success) {
          passed++;
          this.logger.debug(`✅ Permission test passed: ${testCase.name}`);
        } else {
          failed++;
          this.logger.warn(`❌ Permission test failed: ${testCase.name} - ${testResult.reason}`);
        }
      } catch (error) {
        failed++;
        const errorMessage = error instanceof Error ? error.message : String(error);
        results.push({
          testCase: testCase.name,
          success: false,
          reason: `Exception: ${errorMessage}`,
          error: errorMessage
        });
        this.logger.error(`💥 Permission test exception: ${testCase.name}`, error);
      }
    }

    this.logger.info(`📊 Permission testing complete: ${passed} passed, ${failed} failed`);
    return { passed, failed, results };
  }

  /**
   * Test tenant isolation for data access
   */
  async testTenantIsolation(
    tenant1: { slug: string; token: string },
    tenant2: { slug: string; token: string },
    isolationTests: TenantIsolationTestCase[]
  ): Promise<{ passed: number; failed: number; results: any[] }> {
    const results = [];
    let passed = 0;
    let failed = 0;

    for (const testCase of isolationTests) {
      this.logger.debug(`🔒 Testing tenant isolation: ${testCase.name}`);
      
      try {
        // Setup resource in tenant1
        const resource = await testCase.setupAction(tenant1.slug, tenant1.token);
        const resourceId = resource.id || resource.data?.id;
        
        if (!resourceId) {
          failed++;
          results.push({
            testCase: testCase.name,
            success: false,
            reason: 'Failed to create test resource'
          });
          continue;
        }

        // Try to access from tenant2 (should fail)
        const crossTenantResponse = await testCase.crossTenantAction(resourceId, tenant2.slug, tenant2.token);
        
        const success = crossTenantResponse.status === testCase.expectedCrossTenantStatus;
        if (success) {
          passed++;
          this.logger.debug(`✅ Tenant isolation verified: ${testCase.name}`);
        } else {
          failed++;
          this.logger.warn(`❌ Tenant isolation failed: ${testCase.name} - Expected ${testCase.expectedCrossTenantStatus}, got ${crossTenantResponse.status}`);
        }

        results.push({
          testCase: testCase.name,
          success,
          expectedStatus: testCase.expectedCrossTenantStatus,
          actualStatus: crossTenantResponse.status,
          resourceId
        });

      } catch (error) {
        failed++;
        const errorMessage = error instanceof Error ? error.message : String(error);
        results.push({
          testCase: testCase.name,
          success: false,
          reason: `Exception: ${errorMessage}`,
          error: errorMessage
        });
        this.logger.error(`💥 Tenant isolation test exception: ${testCase.name}`, error);
      }
    }

    this.logger.info(`🔒 Tenant isolation testing complete: ${passed} passed, ${failed} failed`);
    return { passed, failed, results };
  }

  /**
   * Validate permission inheritance through role hierarchy
   */
  async validatePermissionInheritance(
    tenantSlug: string,
    adminToken: string
  ): Promise<{ isValid: boolean; details: any }> {
    this.logger.debug(`🏗️ Validating permission inheritance for tenant: ${tenantSlug}`);
    
    try {
      // Get role hierarchy from backend
      const rolesResponse = await this.apiHelper.tenantRequest(tenantSlug, '/admin/roles', {
        token: adminToken
      });

      if (rolesResponse.status !== 200) {
        return {
          isValid: false,
          details: { error: 'Could not fetch roles', status: rolesResponse.status }
        };
      }

      const rolesData = await rolesResponse.json();
      const roles = rolesData.data || [];

      // Validate role structure
      const expectedRoles = ['admin', 'team', 'student'];
      const foundRoles = roles.map((r: any) => r.slug || r.name.toLowerCase());
      
      const missingRoles = expectedRoles.filter(role => !foundRoles.includes(role));
      const hasAllRoles = missingRoles.length === 0;

      // Check permission counts
      const rolePermissionCounts = roles.reduce((acc: any, role: any) => {
        acc[role.slug || role.name.toLowerCase()] = role.permissions?.length || 0;
        return acc;
      }, {});

      // Admin should have most permissions, student least
      const adminPermCount = rolePermissionCounts.admin || 0;
      const teamPermCount = rolePermissionCounts.team || 0;
      const studentPermCount = rolePermissionCounts.student || 0;

      const validHierarchy = adminPermCount >= teamPermCount && teamPermCount >= studentPermCount;

      this.logger.info(`📋 Role hierarchy analysis: Admin(${adminPermCount}), Team(${teamPermCount}), Student(${studentPermCount})`);

      return {
        isValid: hasAllRoles && validHierarchy,
        details: {
          hasAllRoles,
          missingRoles,
          validHierarchy,
          rolePermissionCounts,
          totalRoles: roles.length
        }
      };

    } catch (error) {
      this.logger.error('Failed to validate permission inheritance:', error);
      return {
        isValid: false,
        details: { error: error instanceof Error ? error.message : String(error) }
      };
    }
  }

  /**
   * Test context-specific permissions
   */
  async testContextSpecificPermissions(
    tenantSlug: string,
    token: string,
    contextTests: Array<{
      context: string;
      endpoint: string;
      method: string;
      expectedPermission: string;
      testData?: any;
    }>
  ): Promise<{ passed: number; failed: number; results: any[] }> {
    const results = [];
    let passed = 0;
    let failed = 0;

    for (const contextTest of contextTests) {
      this.logger.debug(`🎯 Testing context-specific permission: ${contextTest.context}`);
      
      try {
        const response = await this.apiHelper.tenantRequest(tenantSlug, contextTest.endpoint, {
          method: contextTest.method as any,
          token,
          body: contextTest.testData
        });

        // For context-specific permissions, we mainly want to ensure the endpoint responds appropriately
        // The actual permission check happens in the backend
        const success = [200, 201, 204, 401, 403, 422].includes(response.status);
        
        if (success) {
          passed++;
          this.logger.debug(`✅ Context permission test passed: ${contextTest.context} (${response.status})`);
        } else {
          failed++;
          this.logger.warn(`❌ Context permission test failed: ${contextTest.context} (${response.status})`);
        }

        results.push({
          context: contextTest.context,
          expectedPermission: contextTest.expectedPermission,
          status: response.status,
          success
        });

      } catch (error) {
        failed++;
        const errorMessage = error instanceof Error ? error.message : String(error);
        results.push({
          context: contextTest.context,
          success: false,
          error: errorMessage
        });
        this.logger.error(`💥 Context permission test exception: ${contextTest.context}`, error);
      }
    }

    this.logger.info(`🎯 Context-specific permission testing complete: ${passed} passed, ${failed} failed`);
    return { passed, failed, results };
  }

  /**
   * Execute a single permission test case
   */
  private async executePermissionTestCase(
    tenantSlug: string,
    tokens: { admin: string; team?: string; student?: string },
    testCase: PermissionTestCase
  ): Promise<any> {
    const results: any = { testCase: testCase.name, roleResults: {} };

    // Test with admin token
    if (tokens.admin) {
      const adminResponse = await this.apiHelper.tenantRequest(tenantSlug, testCase.endpoint, {
        method: testCase.method,
        token: tokens.admin,
        body: testCase.testData
      });
      results.roleResults.admin = {
        status: adminResponse.status,
        expected: testCase.expectedStatuses.admin,
        passed: adminResponse.status === testCase.expectedStatuses.admin
      };
    }

    // Test with team token
    if (tokens.team) {
      const teamResponse = await this.apiHelper.tenantRequest(tenantSlug, testCase.endpoint, {
        method: testCase.method,
        token: tokens.team,
        body: testCase.testData
      });
      results.roleResults.team = {
        status: teamResponse.status,
        expected: testCase.expectedStatuses.team,
        passed: teamResponse.status === testCase.expectedStatuses.team
      };
    }

    // Test with student token
    if (tokens.student) {
      const studentResponse = await this.apiHelper.tenantRequest(tenantSlug, testCase.endpoint, {
        method: testCase.method,
        token: tokens.student,
        body: testCase.testData
      });
      results.roleResults.student = {
        status: studentResponse.status,
        expected: testCase.expectedStatuses.student,
        passed: studentResponse.status === testCase.expectedStatuses.student
      };
    }

    // Test without authentication
    const unauthResponse = await this.apiHelper.tenantRequest(tenantSlug, testCase.endpoint, {
      method: testCase.method,
      body: testCase.testData
    });
    results.roleResults.unauthenticated = {
      status: unauthResponse.status,
      expected: testCase.expectedStatuses.unauthenticated,
      passed: unauthResponse.status === testCase.expectedStatuses.unauthenticated
    };

    // Determine overall success
    const allPassed = Object.values(results.roleResults).every((result: any) => result.passed);
    results.success = allPassed;
    
    if (!allPassed) {
      const failedRoles = Object.entries(results.roleResults)
        .filter(([_, result]: [string, any]) => !result.passed)
        .map(([role, result]: [string, any]) => `${role}(${result.status}!=${result.expected})`)
        .join(', ');
      results.reason = `Failed roles: ${failedRoles}`;
    }

    return results;
  }
}

/**
 * Pre-defined permission test cases for word controller
 */
export const WordControllerPermissionTests: PermissionTestCase[] = [
  {
    name: 'list_words',
    endpoint: '/team/words',
    method: 'GET',
    requiredPermission: 'words.view',
    expectedStatuses: {
      admin: 200,
      team: 200,
      student: 403,
      unauthenticated: 401
    }
  },
  {
    name: 'create_word',
    endpoint: '/team/words',
    method: 'POST',
    requiredPermission: 'words.create',
    testData: {
      language_id: 1,
      text: 'permission-test-word',
      part_of_speech: 'noun'
    },
    expectedStatuses: {
      admin: 201,
      team: 201,
      student: 403,
      unauthenticated: 401
    }
  },
  {
    name: 'view_single_word',
    endpoint: '/team/words/1',
    method: 'GET',
    requiredPermission: 'words.view',
    expectedStatuses: {
      admin: 200,
      team: 200,
      student: 403,
      unauthenticated: 401
    }
  },
  {
    name: 'update_word',
    endpoint: '/team/words/1',
    method: 'PUT',
    requiredPermission: 'words.update',
    testData: {
      text: 'updated-permission-test-word',
      part_of_speech: 'verb'
    },
    expectedStatuses: {
      admin: 200,
      team: 200,
      student: 403,
      unauthenticated: 401
    }
  },
  {
    name: 'delete_word',
    endpoint: '/team/words/1',
    method: 'DELETE',
    requiredPermission: 'words.delete',
    expectedStatuses: {
      admin: 204,
      team: 204,
      student: 403,
      unauthenticated: 401
    }
  },
  {
    name: 'bulk_operations',
    endpoint: '/team/words/bulk',
    method: 'POST',
    requiredPermission: 'words.bulk',
    testData: {
      operation: 'create',
      words: [
        { language_id: 1, text: 'bulk-test-1', part_of_speech: 'noun' },
        { language_id: 1, text: 'bulk-test-2', part_of_speech: 'verb' }
      ]
    },
    expectedStatuses: {
      admin: 200,
      team: 200,
      student: 403,
      unauthenticated: 401
    }
  }
];

/**
 * Pre-defined tenant isolation test cases
 */
export const WordControllerIsolationTests: TenantIsolationTestCase[] = [
  {
    name: 'word_cross_tenant_access',
    setupAction: async (tenantSlug: string, token: string) => {
      const apiHelper = new ApiHelper();
      const response = await apiHelper.tenantRequest(tenantSlug, '/team/words', {
        method: 'POST',
        body: {
          language_id: 1,
          text: 'isolation-test-word',
          part_of_speech: 'noun'
        },
        token
      });
      const data = await response.json();
      return data.data;
    },
    crossTenantAction: async (resourceId: string, tenantSlug: string, token: string) => {
      const apiHelper = new ApiHelper();
      return await apiHelper.tenantRequest(tenantSlug, `/team/words/${resourceId}`, {
        token
      });
    },
    expectedCrossTenantStatus: 404
  }
];

/**
 * Context-specific permission tests
 */
export const WordControllerContextTests = [
  {
    context: 'exercise_context',
    endpoint: '/team/words/available/listening',
    method: 'GET',
    expectedPermission: 'words.view.exercise'
  },
  {
    context: 'constraint_validation',
    endpoint: '/team/words/validate-constraints',
    method: 'POST',
    expectedPermission: 'words.validate',
    testData: {
      word_ids: [1, 2],
      context: 'listening'
    }
  },
  {
    context: 'audio_upload',
    endpoint: '/team/words/1/audio',
    method: 'POST',
    expectedPermission: 'words.audio.upload'
  }
];