'use client';

import { createContext, useContext, useEffect, useState } from 'react';
import { UserType, User, TenantInfo, extractTenantFromPath } from '@/types/tenant/user';

interface AuthContextType {
  user: User | null;
  setUser: (user: User | null) => void;
  isLoading: boolean;
  currentTenant: TenantInfo | null;
  currentRole: string | null;
  isValidTenantPath: boolean;
}

const AuthContext = createContext<AuthContextType>({
  user: null,
  setUser: () => {},
  isLoading: true,
  currentTenant: null,
  currentRole: null,
  isValidTenantPath: false,
});

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [currentTenant, setCurrentTenant] = useState<TenantInfo | null>(null);
  const [currentRole, setCurrentRole] = useState<string | null>(null);
  const [isValidTenantPath, setIsValidTenantPath] = useState(false);

  // Update tenant context when URL changes
  useEffect(() => {
    if (typeof window !== 'undefined') {
      const { tenantSlug, role } = extractTenantFromPath(window.location.pathname);
      setCurrentRole(role);
      setIsValidTenantPath(!!(tenantSlug && role));

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
    const initializeAuth = () => {
      try {
        const userDataString = document.cookie
          .split('; ')
          .find(row => row.startsWith('user_data='))
          ?.split('=')[1];

        if (userDataString) {
          const userData = JSON.parse(decodeURIComponent(userDataString));
          setUser(userData);
        }
      } catch (error) {
        console.error('Error initializing auth:', error);
      } finally {
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
      currentRole,
      isValidTenantPath
    }}>
      {children}
    </AuthContext.Provider>
  );
}

export const useAuth = () => useContext(AuthContext);