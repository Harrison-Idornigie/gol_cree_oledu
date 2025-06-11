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
        // First try to get user data from cookie
        const userDataString = document.cookie
          .split('; ')
          .find(row => row.startsWith('user_data='))
          ?.split('=')[1];

        if (userDataString) {
          const userData = JSON.parse(decodeURIComponent(userDataString));
          setUser(userData);
          setIsLoading(false);
          return;
        }

        // If no user data cookie, check if we have an auth token
        const authToken = document.cookie
          .split('; ')
          .find(row => row.startsWith('auth_token='))
          ?.split('=')[1];

        if (authToken) {
          // Fetch user data using server action (proper Next.js pattern)
          try {
            const { getCurrentUser } = await import('@/app/_actions/auth-actions');
            const result = await getCurrentUser();

            if (result.user) {
              setUser(result.user as User);
            } else if (result.error) {
              // Token is invalid, clear it
              document.cookie = 'auth_token=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
              setUser(null);
            }
          } catch (error) {
            console.error('Error fetching user data via server action:', error);
            setUser(null);
          }
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
      currentMembership,
      isValidTenantPath
    }}>
      {children}
    </AuthContext.Provider>
  );
}

export const useAuth = () => useContext(AuthContext);