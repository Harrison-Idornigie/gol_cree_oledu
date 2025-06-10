/**
 * API Helper for E2E Tests
 * 
 * Provides utilities for making API calls, handling authentication,
 * and validating API responses during E2E testing.
 */
export class ApiHelper {
  private baseUrl: string;
  private defaultTimeout: number;

  constructor() {
    this.baseUrl = process.env.API_BASE_URL || 'http://localhost:8000/api';
    this.defaultTimeout = parseInt(process.env.API_TIMEOUT || '10000');
  }

  /**
   * Make authenticated API request
   */
  async request(
    endpoint: string,
    options: {
      method?: string;
      body?: any;
      headers?: Record<string, string>;
      token?: string;
      timeout?: number;
    } = {}
  ): Promise<Response> {
    const {
      method = 'GET',
      body,
      headers = {},
      token,
      timeout = this.defaultTimeout
    } = options;

    const url = `${this.baseUrl}${endpoint}`;
    
    const requestHeaders: Record<string, string> = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...headers
    };

    if (token) {
      requestHeaders['Authorization'] = `Bearer ${token}`;
    }

    const requestOptions: RequestInit = {
      method,
      headers: requestHeaders,
      signal: AbortSignal.timeout(timeout)
    };

    if (body && method !== 'GET') {
      requestOptions.body = typeof body === 'string' ? body : JSON.stringify(body);
    }

    if (process.env.DEBUG_API_CALLS === 'true') {
      console.log(`🌐 API ${method} ${url}`, { headers: requestHeaders, body });
    }

    const response = await fetch(url, requestOptions);

    if (process.env.DEBUG_API_CALLS === 'true') {
      console.log(`📡 API Response ${response.status}`, await response.clone().text());
    }

    return response;
  }

  /**
   * Register tenant admin via API
   */
  async registerTenantAdmin(data: {
    organizationName: string;
    organizationSlug?: string;
    organizationDescription?: string;
    adminName: string;
    adminEmail: string;
    password: string;
    passwordConfirmation: string;
  }): Promise<{
    success: boolean;
    data?: any;
    error?: string;
    response: Response;
  }> {
    const response = await this.request('/auth/register-tenant-admin', {
      method: 'POST',
      body: {
        tenant: {
          name: data.organizationName,
          slug: data.organizationSlug || '',
          description: data.organizationDescription || '',
        },
        admin: {
          name: data.adminName,
          email: data.adminEmail,
          password: data.password,
          password_confirmation: data.passwordConfirmation,
        }
      }
    });

    let responseData;
    try {
      const responseText = await response.text();
      // Always log the response for debugging
      console.log(`📡 API Response ${response.status}:`, responseText.substring(0, 500) + (responseText.length > 500 ? '...' : ''));
      if (responseText.length > 12500) {
        console.log(`⚠️  Response is very long (${responseText.length} chars), showing end:`, responseText.substring(12500));
      }
      responseData = JSON.parse(responseText);
    } catch (error) {
      console.error('Failed to parse JSON response:', error);
      return {
        success: false,
        data: undefined,
        error: 'Invalid JSON response from server',
        response
      };
    }

    return {
      success: response.ok,
      data: response.ok ? responseData.data : undefined,
      error: !response.ok ? responseData.message || 'Registration failed' : undefined,
      response
    };
  }

  /**
   * Validate tenant slug via API
   */
  async validateTenantSlug(slug: string): Promise<{
    available: boolean;
    error?: string;
    response: Response;
  }> {
    const response = await this.request(`/auth/validate-tenant-slug/${slug}`);
    const data = await response.json();

    return {
      available: data.data?.available || false,
      error: data.data?.error || undefined,
      response
    };
  }

  /**
   * Check tenant creation progress
   */
  async checkTenantProgress(progressId: string): Promise<{
    status: string;
    message: string;
    progress: number;
    response: Response;
  }> {
    const response = await this.request(`/auth/tenant-creation-progress/${progressId}`);
    const data = await response.json();

    return {
      status: data.data?.status || 'unknown',
      message: data.data?.message || '',
      progress: data.data?.progress || 0,
      response
    };
  }

  /**
   * Login via API
   */
  async login(email: string, password: string): Promise<{
    success: boolean;
    token?: string;
    user?: any;
    error?: string;
    response: Response;
  }> {
    const response = await this.request('/auth/login', {
      method: 'POST',
      body: { email, password }
    });

    const data = await response.json();

    return {
      success: response.ok,
      token: response.ok ? data.data?.token : undefined,
      user: response.ok ? data.data?.user : undefined,
      error: !response.ok ? data.message || 'Login failed' : undefined,
      response
    };
  }

  /**
   * Make tenant-specific API request
   */
  async tenantRequest(
    tenantSlug: string,
    endpoint: string,
    options: {
      method?: string;
      body?: any;
      headers?: Record<string, string>;
      token?: string;
      timeout?: number;
    } = {}
  ): Promise<Response> {
    // Use path-based tenant identification: api/{tenant-slug}/endpoint
    const tenantEndpoint = `/${tenantSlug}${endpoint}`;
    return this.request(tenantEndpoint, options);
  }

  /**
   * Verify tenant access via path-based routing
   */
  async verifyTenantAccess(tenantSlug: string, token: string): Promise<{
    hasAccess: boolean;
    tenantInfo?: any;
    error?: string;
    response: Response;
  }> {
    try {
      // Try to access a tenant-specific endpoint
      const response = await this.tenantRequest(tenantSlug, '/auth/me', {
        token
      });

      const data = await response.json();

      return {
        hasAccess: response.ok,
        tenantInfo: response.ok ? data.data?.tenant : undefined,
        error: !response.ok ? data.message || 'Access denied' : undefined,
        response
      };
    } catch (error) {
      return {
        hasAccess: false,
        error: error instanceof Error ? error.message : 'Unknown error',
        response: new Response('', { status: 500 })
      };
    }
  }
}
