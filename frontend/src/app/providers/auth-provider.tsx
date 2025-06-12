'use client';

import { createContext, useContext, useEffect, useState, useCallback } from 'react';
import { usePathname } from 'next/navigation';
import { UserType, User, TenantInfo, extractTenantFromPath } from '@/types/tenant/user';

interface AuthContextType {
  // Authentication
  user: User | null;
  setUser: (user: User | null) => void;
  isLoading: boolean;

  // Tenant Context
  currentTenant: TenantInfo | null;
  currentMembership: string | null;
  tenantSlug: string | null;
  isValidTenantPath: boolean;

  // Tenant Access Control
  hasAccess: () => boolean;
  canAccessMembership: (membership: string) => boolean;
  isOwner: () => boolean;
  isTeacher: () => boolean;
  isStudent: () => boolean;
  isSuperAdmin: () => boolean;

  // Utilities
  buildTenantUrl: (membership: string, path?: string) => string;
  refreshTenant: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType>({
  // Authentication
  user: null,
  setUser: () => {},
  isLoading: true,

  // Tenant Context
  currentTenant: null,
  currentMembership: null,
  tenantSlug: null,
  isValidTenantPath: false,

  // Tenant Access Control
  hasAccess: () => false,
  canAccessMembership: () => false,
  isOwner: () => false,
  isTeacher: () => false,
  isStudent: () => false,
  isSuperAdmin: () => false,

  // Utilities
  buildTenantUrl: () => '',
  refreshTenant: async () => {},
});

export function AuthProvider({ children }: { children: React.ReactNode }) {
  // Authentication State
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  // Tenant Context State
  const [currentTenant, setCurrentTenant] = useState<TenantInfo | null>(null);
  const [currentMembership, setCurrentMembership] = useState<string | null>(null);
  const [tenantSlug, setTenantSlug] = useState<string | null>(null);
  const [isValidTenantPath, setIsValidTenantPath] = useState(false);

  const pathname = usePathname();

  // Tenant context management
  const updateTenantContext = useCallback(() => {
    console.log('🔄 AuthProvider: Updating tenant context:', {
      pathname,
      user: user ? {
        id: user.id,
        email: user.email,
        membership: user.membership,
        tenantSlug: user.tenant?.slug
      } : null
    });

    const { tenantSlug: extractedSlug, membership } = extractTenantFromPath(pathname);

    console.log('📍 AuthProvider: Extracted from path:', {
      extractedSlug,
      membership,
      isValidPath: !!(extractedSlug && membership)
    });

    setTenantSlug(extractedSlug);
    setCurrentMembership(membership);
    setIsValidTenantPath(!!(extractedSlug && membership));

    // Set current tenant from user data
    if (user?.tenant && extractedSlug === user.tenant.slug) {
      console.log('✅ AuthProvider: Using tenant from user data');
      setCurrentTenant(user.tenant);
    } else if (extractedSlug && !user?.tenant) {
      console.log('⚠️ AuthProvider: Tenant slug in URL but no user tenant data');
      setCurrentTenant(null);
    } else {
      console.log('❌ AuthProvider: No tenant context');
      setCurrentTenant(null);
    }
  }, [pathname, user]);

  // Update tenant context when URL or user changes
  useEffect(() => {
    updateTenantContext();
  }, [updateTenantContext]);

  // Access control functions
  const hasAccess = useCallback(() => {
    if (!user || !tenantSlug || !currentMembership) {
      console.log('🚫 AuthProvider: Missing required data for access check');
      return false;
    }

    // Super admins can access any tenant
    if (user.membership === UserType.SUPER_ADMIN) {
      console.log('✅ AuthProvider: Super admin access granted');
      return true;
    }

    // Users can only access their own tenant
    if (user.tenant?.slug !== tenantSlug) {
      console.log('🚫 AuthProvider: Tenant mismatch');
      return false;
    }

    // Membership-based access within tenant
    const membershipHierarchy: Record<string, string[]> = {
      [UserType.TENANT_ADMIN]: ['admin', 'team', 'student'],
      [UserType.TEAM]: ['team', 'student'],
      [UserType.STUDENT]: ['student'],
      [UserType.USER]: ['student'], // Legacy support
    };

    const allowedMemberships = membershipHierarchy[user.membership] || [];
    const hasAccess = allowedMemberships.includes(currentMembership);

    console.log('🔐 AuthProvider: Access check:', {
      userMembership: user.membership,
      requestedMembership: currentMembership,
      allowedMemberships,
      hasAccess
    });

    return hasAccess;
  }, [user, tenantSlug, currentMembership]);

  const canAccessMembership = useCallback((membership: string) => {
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
  }, [user]);

  const isOwner = useCallback(() => user?.membership === UserType.TENANT_ADMIN, [user]);
  const isTeacher = useCallback(() => user?.membership === UserType.TEAM, [user]);
  const isStudent = useCallback(() => user?.membership === UserType.STUDENT || user?.membership === UserType.USER, [user]);
  const isSuperAdmin = useCallback(() => user?.membership === UserType.SUPER_ADMIN, [user]);

  const buildTenantUrl = useCallback((membership: string, path: string = '') => {
    if (!tenantSlug) return path;
    const basePath = `/${tenantSlug}/${membership}`;
    return path ? `${basePath}${path.startsWith('/') ? path : `/${path}`}` : basePath;
  }, [tenantSlug]);

  const refreshTenant = useCallback(async () => {
    updateTenantContext();
  }, [updateTenantContext]);

  // Initialize authentication
  useEffect(() => {
    const initializeAuth = async () => {
      try {
        console.log('🔄 AuthProvider: Initializing authentication...');

        // Always try to fetch user data using server action
        // This handles httpOnly cookies properly on the server side
        const { getCurrentUser } = await import('@/app/_actions/auth-actions');
        const result = await getCurrentUser();

        console.log('🔍 AuthProvider: getCurrentUser result:', {
          hasUser: !!result.user,
          hasError: !!result.error,
          error: result.error
        });

        if (result.user) {
          console.log('✅ AuthProvider: User authenticated:', {
            id: result.user.id,
            email: result.user.email,
            membership: result.user.membership,
            tenantSlug: result.user.tenant?.slug
          });
          setUser(result.user as User);
        } else if (result.error) {
          console.log('❌ AuthProvider: Authentication failed:', result.error);
          setUser(null);
        } else {
          console.log('⚠️ AuthProvider: No user and no error - user not authenticated');
          setUser(null);
        }
      } catch (error) {
        console.error('💥 AuthProvider: Error fetching user data:', error);
        setUser(null);
      } finally {
        console.log('🏁 AuthProvider: Initialization complete');
        setIsLoading(false);
      }
    };

    initializeAuth();
  }, []);

  return (
    <AuthContext.Provider value={{
      // Authentication
      user,
      setUser,
      isLoading,

      // Tenant Context
      currentTenant,
      currentMembership,
      tenantSlug,
      isValidTenantPath,

      // Tenant Access Control
      hasAccess,
      canAccessMembership,
      isOwner,
      isTeacher,
      isStudent,
      isSuperAdmin,

      // Utilities
      buildTenantUrl,
      refreshTenant,
    }}>
      {children}
    </AuthContext.Provider>
  );
}

export const useAuth = () => useContext(AuthContext);

// Convenience hooks for backward compatibility and ease of use
export const useTenant = () => {
  const { currentTenant, currentMembership, tenantSlug, isValidTenantPath, refreshTenant } = useAuth();
  return {
    currentTenant,
    currentMembership,
    tenantSlug,
    isValidTenantPath,
    isLoading: false, // No separate loading state needed
    refreshTenant,
  };
};

export const useTenantAccess = () => {
  const { hasAccess, canAccessMembership, isOwner, isTeacher, isStudent, isSuperAdmin } = useAuth();
  return {
    hasAccess,
    canAccessMembership,
    isOwner,
    isTeacher,
    isStudent,
    isSuperAdmin,
  };
};

export const useTenantUrl = () => {
  const { buildTenantUrl } = useAuth();
  return buildTenantUrl;
};