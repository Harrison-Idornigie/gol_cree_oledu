import { createContext, useContext, useEffect, useState } from 'react';
import { UserType } from '@/types/tenant/user';

interface User {
  id: number;
  name: string;
  email: string;
  role: UserType;
}

interface AuthContextType {
  user: User | null;
  setUser: (user: User | null) => void;
  isLoading: boolean;
}

const AuthContext = createContext<AuthContextType>({
  user: null,
  setUser: () => {},
  isLoading: true,
});

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);

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
    <AuthContext.Provider value={{ user, setUser, isLoading }}>
      {children}
    </AuthContext.Provider>
  );
}

export const useAuth = () => useContext(AuthContext);