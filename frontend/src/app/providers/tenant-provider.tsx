'use client';

import { createContext, useContext, useEffect, useState } from 'react';
import { usePathname } from 'next/navigation';
import { TenantInfo, extractTenantFromPath } from '@/types/tenant/user';
import { useAuth } from './auth-provider';

interface TenantContextType {
  currentTenant: TenantInfo | null;
  currentRole: string | null;
  isValidTenantPath: boolean;
  tenantSlug: string | null;
  isLoading: boolean;
  refreshTenant: () => Promise<void>;
}

const TenantContext = createContext<TenantContextType>({
  currentTenant: null,
  currentRole: null,
  isValidTenantPath: false,
  tenantSlug: null,
  isLoading: true,
  refreshTenant: async () => {},
});

export function TenantProvider({ children }: { children: React.ReactNode }) {
  const [currentTenant, setCurrentTenant] = useState<TenantInfo | null>(null);
  const [currentRole, setCurrentRole] = useState<string | null>(null);
  const [isValidTenantPath, setIsValidTenantPath] = useState(false);
  const [tenantSlug, setTenantSlug] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  
  const pathname = usePathname();
  const { user } = useAuth();

  // Extract tenant context from URL path
  useEffect(() => {
    const { tenantSlug: extractedSlug, role } = extractTenantFromPath(pathname);
    
    setTenantSlug(extractedSlug);
    setCurrentRole(role);
    setIsValidTenantPath(!!(extractedSlug && role));
    
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
  }, [pathname, user]);

  const fetchTenantInfo = async (slug: string) => {
    try {
      setIsLoading(true);
      
      // Make API call to get tenant info
      const response = await fetch(`/api/tenants/${slug}/info`);
      
      if (response.ok) {
        const data = await response.json();
        setCurrentTenant(data.tenant);
      } else {
        setCurrentTenant(null);
      }
    } catch (error) {
      console.error('Failed to fetch tenant info:', error);
      setCurrentTenant(null);
    } finally {
      setIsLoading(false);
    }
  };

  const refreshTenant = async () => {
    if (tenantSlug) {
      await fetchTenantInfo(tenantSlug);
    }
  };

  return (
    <TenantContext.Provider value={{
      currentTenant,
      currentRole,
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

export function useCurrentRole(): string | null {
  const { currentRole } = useTenant();
  return currentRole;
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
  
  return (role: string, path: string = '') => {
    if (!tenantSlug) return path;
    const basePath = `/${tenantSlug}/${role}`;
    return path ? `${basePath}${path.startsWith('/') ? path : `/${path}`}` : basePath;
  };
}

// Helper function to check if current user has access to current tenant/role
export function useTenantAccess() {
  const { user } = useAuth();
  const { tenantSlug, currentRole } = useTenant();
  
  return {
    hasAccess: () => {
      if (!user || !tenantSlug || !currentRole) return false;
      
      // Super admins can access any tenant
      if (user.role === 'super-admin') return true;
      
      // Users can only access their own tenant
      if (user.tenant?.slug !== tenantSlug) return false;
      
      // Role-based access within tenant
      const roleHierarchy: Record<string, string[]> = {
        'tenant-admin': ['admin', 'team', 'student'],
        'admin': ['admin', 'team', 'student'], // Legacy support
        'team': ['team', 'student'],
        'student': ['student'],
        'user': ['student'], // Legacy support
      };
      
      const allowedRoles = roleHierarchy[user.role] || [];
      return allowedRoles.includes(currentRole);
    },
    
    canAccessRole: (role: string) => {
      if (!user) return false;
      
      // Super admins can access any role
      if (user.role === 'super-admin') return true;
      
      const roleHierarchy: Record<string, string[]> = {
        'tenant-admin': ['admin', 'team', 'student'],
        'admin': ['admin', 'team', 'student'],
        'team': ['team', 'student'],
        'student': ['student'],
        'user': ['student'],
      };
      
      const allowedRoles = roleHierarchy[user.role] || [];
      return allowedRoles.includes(role);
    },
    
    isOwner: () => {
      return user?.role === 'tenant-admin' || user?.role === 'admin';
    },
    
    isTeacher: () => {
      return user?.role === 'team';
    },
    
    isStudent: () => {
      return user?.role === 'student' || user?.role === 'user';
    },
    
    isSuperAdmin: () => {
      return user?.role === 'super-admin';
    }
  };
}
