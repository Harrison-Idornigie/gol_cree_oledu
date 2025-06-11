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
  membership: UserType;
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
  membership: UserType;
  expires_at: string;
  created_at: string;
  updated_at: string;
  claimed_at?: string;
}

export interface AdminInviteFormData {
  email: string;
  membership?: UserType;
}

export interface UserData {
  id: number;
  name: string;
  email: string;
  avatar_url?: string;
  membership: UserType;
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
  return user?.membership === UserType.SUPER_ADMIN ||
         user?.membership === UserType.TENANT_ADMIN;
};

export const isSuperAdmin = (user: User | null): boolean => {
  return user?.membership === UserType.SUPER_ADMIN;
};

export const isTenantAdmin = (user: User | null): boolean => {
  return user?.membership === UserType.TENANT_ADMIN;
};

export const isTeam = (user: User | null): boolean => {
  return user?.membership === UserType.TEAM;
};

export const isStudent = (user: User | null): boolean => {
  return user?.membership === UserType.STUDENT || user?.membership === UserType.USER;
};

export const getDefaultRedirectPath = (user: User | null): string => {
  if (!user) return "/login";

  // Super admins use central routes
  if (user.membership === UserType.SUPER_ADMIN) {
    return "/super";
  }

  // All other users need tenant context
  const tenantSlug = user.tenant?.slug;
  if (!tenantSlug) {
    // If no tenant context, redirect to login
    return "/login";
  }

  // Build tenant-specific paths
  switch (user.membership) {
    case UserType.TENANT_ADMIN:
      return `/${tenantSlug}/admin/dashboard`;
    case UserType.TEAM:
      return `/${tenantSlug}/team/dashboard`;
    case UserType.STUDENT:
    case UserType.USER:
      return `/${tenantSlug}/student/dashboard`;
    default:
      return `/${tenantSlug}/student/dashboard`;
  }
};

// Utility functions for path-based tenant routing
export const extractTenantFromPath = (pathname: string): { tenantSlug: string | null; membership: string | null } => {
  const pathSegments = pathname.replace(/^\//, '').split('/');

  if (pathSegments.length < 2) {
    return { tenantSlug: null, membership: null };
  }

  const [tenantSlug, membership] = pathSegments;
  const validMemberships = ['admin', 'team', 'student'];

  return {
    tenantSlug: /^[a-z0-9-]+$/.test(tenantSlug) ? tenantSlug : null,
    membership: validMemberships.includes(membership) ? membership : null
  };
};

export const isTenantPath = (pathname: string): boolean => {
  const { tenantSlug, membership } = extractTenantFromPath(pathname);
  return !!(tenantSlug && membership);
};

export const isCentralPath = (pathname: string): boolean => {
  const centralPaths = ['/super', '/login', '/register', '/forgot-password', '/reset-password'];
  return centralPaths.some(path => pathname === path || pathname.startsWith(`${path}/`));
};

export const isEmailVerified = (user: User | null): boolean => {
  return !!user?.email_verified_at;
};
