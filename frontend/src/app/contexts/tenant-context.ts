/**
 * Tenant Context Utilities
 * 
 * Handles path-based tenant identification and context management
 * for the multi-tenant application.
 */

export interface TenantInfo {
  id: string;
  name: string;
  slug: string;
  status: 'active' | 'inactive' | 'suspended';
}

export interface TenantContext {
  tenant: TenantInfo | null;
  role: string | null;
  isValidPath: boolean;
}

/**
 * Extract tenant slug and role from URL path
 * Expected format: /{tenant-slug}/{role}/...
 */
export function extractTenantContext(pathname: string): TenantContext {
  // Remove leading slash and split path
  const pathSegments = pathname.replace(/^\//, '').split('/');
  
  // Check if we have at least tenant and role segments
  if (pathSegments.length < 2) {
    return {
      tenant: null,
      role: null,
      isValidPath: false
    };
  }
  
  const [tenantSlug, role] = pathSegments;
  
  // Validate role
  const validRoles = ['admin', 'team', 'student'];
  const isValidRole = validRoles.includes(role);
  
  // Validate tenant slug format (lowercase letters, numbers, hyphens)
  const isValidTenantSlug = /^[a-z0-9-]+$/.test(tenantSlug);
  
  return {
    tenant: isValidTenantSlug ? {
      id: '', // Will be populated from API
      name: '', // Will be populated from API  
      slug: tenantSlug,
      status: 'active'
    } : null,
    role: isValidRole ? role : null,
    isValidPath: isValidTenantSlug && isValidRole
  };
}

/**
 * Check if the current path is a tenant-specific path
 */
export function isTenantPath(pathname: string): boolean {
  const context = extractTenantContext(pathname);
  return context.isValidPath;
}

/**
 * Check if the current path is a central/landlord path
 */
export function isCentralPath(pathname: string): boolean {
  const centralPaths = [
    '/super',
    '/login', 
    '/register',
    '/forgot-password',
    '/reset-password',
    '/accept-invite',
    '/verification-notice',
    '/verify-email'
  ];
  
  return centralPaths.some(path => 
    pathname === path || pathname.startsWith(`${path}/`)
  );
}

/**
 * Build tenant-specific URL
 */
export function buildTenantUrl(tenantSlug: string, role: string, path: string = ''): string {
  const basePath = `/${tenantSlug}/${role}`;
  return path ? `${basePath}${path.startsWith('/') ? path : `/${path}`}` : basePath;
}

/**
 * Get redirect path based on user role and tenant context
 */
export function getTenantRedirectPath(userRole: string, tenantSlug: string): string {
  const roleMapping: Record<string, string> = {
    'super-admin': '/super', // Central path for super admins
    'tenant-admin': buildTenantUrl(tenantSlug, 'admin'),
    'admin': buildTenantUrl(tenantSlug, 'admin'), // Legacy support
    'team': buildTenantUrl(tenantSlug, 'team'),
    'student': buildTenantUrl(tenantSlug, 'student'),
    'user': buildTenantUrl(tenantSlug, 'student'), // Legacy support
  };
  
  return roleMapping[userRole] || buildTenantUrl(tenantSlug, 'student');
}

/**
 * Validate tenant slug format
 */
export function isValidTenantSlug(slug: string): boolean {
  // Only lowercase letters, numbers, and hyphens
  // Must start and end with alphanumeric character
  // Length between 3-50 characters
  return /^[a-z0-9][a-z0-9-]{1,48}[a-z0-9]$/.test(slug);
}

/**
 * Generate tenant slug from organization name
 */
export function generateTenantSlug(organizationName: string): string {
  return organizationName
    .toLowerCase()
    .replace(/[^a-z0-9\s-]/g, '') // Remove special characters except spaces and hyphens
    .replace(/\s+/g, '-') // Replace spaces with hyphens
    .replace(/-+/g, '-') // Replace multiple hyphens with single hyphen
    .replace(/^-|-$/g, '') // Remove leading/trailing hyphens
    .substring(0, 50); // Limit length
}

/**
 * Extract tenant slug from current URL
 */
export function getCurrentTenantSlug(pathname: string): string | null {
  const context = extractTenantContext(pathname);
  return context.tenant?.slug || null;
}

/**
 * Extract role from current URL
 */
export function getCurrentRole(pathname: string): string | null {
  const context = extractTenantContext(pathname);
  return context.role;
}

/**
 * Check if user has access to the requested tenant/role combination
 */
export function hasAccessToTenantRole(
  userRole: string, 
  userTenantSlug: string | null, 
  requestedTenantSlug: string, 
  requestedRole: string
): boolean {
  // Super admins can access any tenant
  if (userRole === 'super-admin') {
    return true;
  }
  
  // Users can only access their own tenant
  if (userTenantSlug !== requestedTenantSlug) {
    return false;
  }
  
  // Role-based access within tenant
  const roleHierarchy: Record<string, string[]> = {
    'tenant-admin': ['admin', 'team', 'student'],
    'admin': ['admin', 'team', 'student'], // Legacy support
    'team': ['team', 'student'],
    'student': ['student'],
    'user': ['student'], // Legacy support
  };
  
  const allowedRoles = roleHierarchy[userRole] || [];
  return allowedRoles.includes(requestedRole);
}
