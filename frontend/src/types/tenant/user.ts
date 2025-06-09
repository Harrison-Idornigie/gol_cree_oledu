export enum UserType {
  SUPER_ADMIN = "super-admin",
  TENANT_ADMIN = "tenant-admin",
  TEAM = "team",
  STUDENT = "student",
  USER = "user",
}

export interface User {
  id: number;
  name: string;
  email: string;
  avatar_url?: string;
  role: UserType;
  is_active: boolean;
  created_at: string;
  updated_at: string;
  email_verified_at: string | null;
  tenant_id?: string;
  tenant?: TenantInfo;
}

export interface TenantInfo {
  id: string;
  name: string;
  slug: string;
  status: 'active' | 'inactive' | 'suspended';
}

export interface AdminInvite {
  id: number;
  email: string;
  token: string;
  role: UserType;
  expires_at: string;
  created_at: string;
  updated_at: string;
  claimed_at?: string;
}

export interface AdminInviteFormData {
  email: string;
  role?: UserType;
}

export interface UserData {
  id: number;
  name: string;
  email: string;
  avatar_url?: string;
  role: UserType;
  email_verified_at: string | null;
}

export interface AdminUserListResponse {
  users: User[];
  invites: AdminInvite[];
  total_users: number;
  total_invites: number;
}

export interface AdminUserResponse {
  error?: string;
  data?: {
    user: User;
    invites: AdminInvite[];
  };
}

export interface AdminInviteResponse {
  error?: string;
  data?: {
    invite: AdminInvite;
  };
}

export interface UserProgressStats {
  total_xp: number;
  completed_lessons: number;
  current_streak: number;
  longest_streak: number;
  achievements: Achievement[];
}

export interface Achievement {
  id: number;
  title: string;
  description: string;
  icon: string;
  earned_at: string;
}

export interface UserSession {
  user: User | null;
  isAuthenticated: boolean;
  isLoading: boolean;
}

export const isAdmin = (user: User | null): boolean => {
  return user?.role === UserType.SUPER_ADMIN ||
         user?.role === UserType.TENANT_ADMIN;
};

export const isSuperAdmin = (user: User | null): boolean => {
  return user?.role === UserType.SUPER_ADMIN;
};

export const isTenantAdmin = (user: User | null): boolean => {
  return user?.role === UserType.TENANT_ADMIN;
};

export const isTeam = (user: User | null): boolean => {
  return user?.role === UserType.TEAM;
};

export const isStudent = (user: User | null): boolean => {
  return user?.role === UserType.STUDENT || user?.role === UserType.USER;
};

export const getDefaultRedirectPath = (user: User | null): string => {
  if (!user) return "/login";

  // Super admins use central routes
  if (user.role === UserType.SUPER_ADMIN) {
    return "/super";
  }

  // All other users need tenant context
  const tenantSlug = user.tenant?.slug;
  if (!tenantSlug) {
    // If no tenant context, redirect to login
    return "/login";
  }

  // Build tenant-specific paths
  switch (user.role) {
    case UserType.TENANT_ADMIN:
      return `/${tenantSlug}/admin`;
    case UserType.TEAM:
      return `/${tenantSlug}/team`;
    case UserType.STUDENT:
    case UserType.USER:
      return `/${tenantSlug}/student`;
    default:
      return `/${tenantSlug}/student`;
  }
};

// Utility functions for path-based tenant routing
export const extractTenantFromPath = (pathname: string): { tenantSlug: string | null; role: string | null } => {
  const pathSegments = pathname.replace(/^\//, '').split('/');

  if (pathSegments.length < 2) {
    return { tenantSlug: null, role: null };
  }

  const [tenantSlug, role] = pathSegments;
  const validRoles = ['admin', 'team', 'student'];

  return {
    tenantSlug: /^[a-z0-9-]+$/.test(tenantSlug) ? tenantSlug : null,
    role: validRoles.includes(role) ? role : null
  };
};

export const isTenantPath = (pathname: string): boolean => {
  const { tenantSlug, role } = extractTenantFromPath(pathname);
  return !!(tenantSlug && role);
};

export const isCentralPath = (pathname: string): boolean => {
  const centralPaths = ['/super', '/login', '/register', '/forgot-password', '/reset-password'];
  return centralPaths.some(path => pathname === path || pathname.startsWith(`${path}/`));
};

export const isEmailVerified = (user: User | null): boolean => {
  return !!user?.email_verified_at;
};
