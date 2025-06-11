'use client';

import { createContext, useContext, useEffect, useState } from 'react';
import { UserType, User, TenantInfo, extractTenantFromPath } from '@/types/tenant/user';

interface AuthContextType {
  user: User | null;
  setUser: (user: User | null) => void;
  isLoading: boolean;
  currentTenant: TenantInfo | null;
  currentMembership: string | null;
  isValidTenantPath: boolean;
}

const AuthContext = createContext<AuthContextType>({
  user: null,
  setUser: () => {},
  isLoading: true,
  currentTenant: null,
  currentMembership: null,
  isValidTenantPath: false,
});

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [currentTenant, setCurrentTenant] = useState<TenantInfo | null>(null);
  const [currentMembership, setCurrentMembership] = useState<string | null>(null);
  const [isValidTenantPath, setIsValidTenantPath] = useState(false);

  // Update tenant context when URL changes
  useEffect(() => {
    if (typeof window !== 'undefined') {
      const { tenantSlug, membership } = extractTenantFromPath(window.location.pathname);
      setCurrentMembership(membership);
      setIsValidTenantPath(!!(tenantSlug && membership));

      // Set current tenant from user data or URL
      if (user?.tenant) {
        setCurrentTenant(user.tenant);
      } else if (tenantSlug) {
        // If we have a tenant slug but no user tenant data, create minimal tenant info
        setCurrentTenant({
          id: '',
          name: '',
          slug: tenantSlug,
          status: 'active'
        });
      } else {
        setCurrentTenant(null);
      }
    }
  }, [user]);

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
      user,
      setUser,
      isLoading,
      currentTenant,
      currentMembership,
      isValidTenantPath
    }}>
      {children}
    </AuthContext.Provider>
  );
}

export const useAuth = () => useContext(AuthContext);