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
  membership: string | null;
  isValidPath: boolean;
}

/**
 * Extract tenant slug and membership from URL path
 * Expected format: /{tenant-slug}/{membership}/...
 */
export function extractTenantContext(pathname: string): TenantContext {
  // Remove leading slash and split path
  const pathSegments = pathname.replace(/^\//, '').split('/');
  
  // Check if we have at least tenant and membership segments
  if (pathSegments.length < 2) {
    return {
      tenant: null,
      membership: null,
      isValidPath: false
    };
  }
  
  const [tenantSlug, membership] = pathSegments;
  
  // Validate membership
  const validMemberships = ['admin', 'team', 'student'];
  const isValidMembership = validMemberships.includes(membership);
  
  // Validate tenant slug format (lowercase letters, numbers, hyphens)
  const isValidTenantSlug = /^[a-z0-9-]+$/.test(tenantSlug);
  
  return {
    tenant: isValidTenantSlug ? {
      id: '', // Will be populated from API
      name: '', // Will be populated from API  
      slug: tenantSlug,
      status: 'active'
    } : null,
    membership: isValidMembership ? membership : null,
    isValidPath: isValidTenantSlug && isValidMembership
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
export function buildTenantUrl(tenantSlug: string, membership: string, path: string = ''): string {
  const basePath = `/${tenantSlug}/${membership}`;
  return path ? `${basePath}${path.startsWith('/') ? path : `/${path}`}` : basePath;
}

/**
 * Get redirect path based on user membership and tenant context
 */
export function getTenantRedirectPath(userMembership: string, tenantSlug: string): string {
  const membershipMapping: Record<string, string> = {
    'super-admin': '/super', // Central path for super admins
    'tenant-admin': buildTenantUrl(tenantSlug, 'admin', '/dashboard'),
    'admin': buildTenantUrl(tenantSlug, 'admin', '/dashboard'), // Legacy support
    'team': buildTenantUrl(tenantSlug, 'team', '/dashboard'),
    'student': buildTenantUrl(tenantSlug, 'student', '/dashboard'),
    'user': buildTenantUrl(tenantSlug, 'student', '/dashboard'), // Legacy support
  };

  return membershipMapping[userMembership] || buildTenantUrl(tenantSlug, 'student', '/dashboard');
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
 * Extract membership from current URL
 */
export function getCurrentMembership(pathname: string): string | null {
  const context = extractTenantContext(pathname);
  return context.membership;
}

/**
 * Check if user has access to the requested tenant/membership combination
 */
export function hasAccessToTenantMembership(
  userMembership: string, 
  userTenantSlug: string | null, 
  requestedTenantSlug: string, 
  requestedMembership: string
): boolean {
  // Super admins can access any tenant
  if (userMembership === 'super-admin') {
    return true;
  }
  
  // Users can only access their own tenant
  if (userTenantSlug !== requestedTenantSlug) {
    return false;
  }
  
  // Membership-based access within tenant
  const membershipHierarchy: Record<string, string[]> = {
    'tenant-admin': ['admin', 'team', 'student'],
    'admin': ['admin', 'team', 'student'], // Legacy support
    'team': ['team', 'student'],
    'student': ['student'],
    'user': ['student'], // Legacy support
  };
  
  const allowedMemberships = membershipHierarchy[userMembership] || [];
  return allowedMemberships.includes(requestedMembership);
}
