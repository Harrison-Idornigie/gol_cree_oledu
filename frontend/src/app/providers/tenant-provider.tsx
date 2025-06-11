'use client';

import { createContext, useContext, useEffect, useState, useCallback } from 'react';
import { usePathname } from 'next/navigation';
import { TenantInfo, extractTenantFromPath, UserType } from '@/types/tenant/user';
import { useAuth } from './auth-provider';

interface TenantContextType {
  currentTenant: TenantInfo | null;
  currentMembership: string | null;
  isValidTenantPath: boolean;
  tenantSlug: string | null;
  isLoading: boolean;
  refreshTenant: () => Promise<void>;
}

const TenantContext = createContext<TenantContextType>({
  currentTenant: null,
  currentMembership: null,
  isValidTenantPath: false,
  tenantSlug: null,
  isLoading: true,
  refreshTenant: async () => {},
});

export function TenantProvider({ children }: { children: React.ReactNode }) {
  const [currentTenant, setCurrentTenant] = useState<TenantInfo | null>(null);
  const [currentMembership, setCurrentMembership] = useState<string | null>(null);
  const [isValidTenantPath, setIsValidTenantPath] = useState(false);
  const [tenantSlug, setTenantSlug] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  
  const pathname = usePathname();
  const { user } = useAuth();

  const fetchTenantInfo = useCallback(async (slug: string) => {
    try {
      setIsLoading(true);

      // The tenant information should come from the user's authentication data
      // If we have a tenant slug but no user tenant data, it means the user
      // doesn't have access to this tenant or the tenant doesn't exist
      // We'll rely on the user's tenant data from authentication
      if (user?.tenant && user.tenant.slug === slug) {
        setCurrentTenant(user.tenant);
      } else {
        setCurrentTenant(null);
      }
    } catch (error) {
      console.error('Failed to resolve tenant info:', error);
      setCurrentTenant(null);
    } finally {
      setIsLoading(false);
    }
  }, [user]);

  // Extract tenant context from URL path
  useEffect(() => {
    const { tenantSlug: extractedSlug, membership } = extractTenantFromPath(pathname);

    setTenantSlug(extractedSlug);
    setCurrentMembership(membership);
    setIsValidTenantPath(!!(extractedSlug && membership));

    // If we have tenant info from user data, use it
    if (user?.tenant && extractedSlug === user.tenant.slug) {
      setCurrentTenant(user.tenant);
      setIsLoading(false);
    } else if (extractedSlug) {
      // If we have a tenant slug but no user tenant data, fetch tenant info
      fetchTenantInfo(extractedSlug);
    } else {
      setCurrentTenant(null);
      setIsLoading(false);
    }
  }, [pathname, user, fetchTenantInfo]);

  const refreshTenant = async () => {
    if (tenantSlug) {
      await fetchTenantInfo(tenantSlug);
    }
  };

  return (
    <TenantContext.Provider value={{
      currentTenant,
      currentMembership,
      isValidTenantPath,
      tenantSlug,
      isLoading,
      refreshTenant,
    }}>
      {children}
    </TenantContext.Provider>
  );
}

export function useTenant() {
  const context = useContext(TenantContext);
  if (!context) {
    throw new Error('useTenant must be used within a TenantProvider');
  }
  return context;
}

// Helper hooks for common tenant operations
export function useTenantSlug(): string | null {
  const { tenantSlug } = useTenant();
  return tenantSlug;
}

export function useCurrentMembership(): string | null {
  const { currentMembership } = useTenant();
  return currentMembership;
}

export function useIsValidTenantPath(): boolean {
  const { isValidTenantPath } = useTenant();
  return isValidTenantPath;
}

export function useTenantInfo(): TenantInfo | null {
  const { currentTenant } = useTenant();
  return currentTenant;
}

// Helper function to build tenant-specific URLs
export function useTenantUrl() {
  const { tenantSlug } = useTenant();

  return (membership: string, path: string = '') => {
    if (!tenantSlug) return path;
    const basePath = `/${tenantSlug}/${membership}`;
    return path ? `${basePath}${path.startsWith('/') ? path : `/${path}`}` : basePath;
  };
}

// Helper function to check if current user has access to current tenant/membership
export function useTenantAccess() {
  const { user } = useAuth();
  const { tenantSlug, currentMembership } = useTenant();
  
  return {
    hasAccess: () => {
      if (!user || !tenantSlug || !currentMembership) return false;
      
      // Super admins can access any tenant
      if (user.membership === UserType.SUPER_ADMIN) return true;

      // Users can only access their own tenant
      if (user.tenant?.slug !== tenantSlug) return false;

      // Membership-based access within tenant
      const membershipHierarchy: Record<string, string[]> = {
        [UserType.TENANT_ADMIN]: ['admin', 'team', 'student'],
        [UserType.TEAM]: ['team', 'student'],
        [UserType.STUDENT]: ['student'],
        [UserType.USER]: ['student'], // Legacy support
      };

      const allowedMemberships = membershipHierarchy[user.membership] || [];
      return allowedMemberships.includes(currentMembership);
    },
    
    canAccessMembership: (membership: string) => {
      if (!user) return false;

      // Super admins can access any membership
      if (user.membership === UserType.SUPER_ADMIN) return true;

      const membershipHierarchy: Record<string, string[]> = {
        [UserType.TENANT_ADMIN]: ['admin', 'team', 'student'],
        [UserType.TEAM]: ['team', 'student'],
        [UserType.STUDENT]: ['student'],
        [UserType.USER]: ['student'],
      };

      const allowedMemberships = membershipHierarchy[user.membership] || [];
      return allowedMemberships.includes(membership);
    },
    
    isOwner: () => {
      return user?.membership === UserType.TENANT_ADMIN;
    },

    isTeacher: () => {
      return user?.membership === UserType.TEAM;
    },

    isStudent: () => {
      return user?.membership === UserType.STUDENT || user?.membership === UserType.USER;
    },

    isSuperAdmin: () => {
      return user?.membership === UserType.SUPER_ADMIN;
    }
  };
}
