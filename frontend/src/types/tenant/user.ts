export enum UserRole {
  SUPER_ADMIN = "super-admin",
  TENANT_ADMIN = "tenant-admin",
  TEAM = "team",
  STUDENT = "student",
  // Legacy support
  ADMIN = "admin",
  USER = "user",
}

export interface User {
  id: number;
  name: string;
  email: string;
  avatar_url?: string;
  role: UserRole;
  is_active: boolean;
  created_at: string;
  updated_at: string;
  email_verified_at: string | null;
}

export interface AdminInvite {
  id: number;
  email: string;
  token: string;
  role: UserRole;
  expires_at: string;
  created_at: string;
  updated_at: string;
  claimed_at?: string;
}

export interface AdminInviteFormData {
  email: string;
  role?: UserRole;
}

export interface UserData {
  id: number;
  name: string;
  email: string;
  avatar_url?: string;
  role: UserRole;
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
  return user?.role === UserRole.ADMIN ||
         user?.role === UserRole.SUPER_ADMIN ||
         user?.role === UserRole.TENANT_ADMIN;
};

export const isSuperAdmin = (user: User | null): boolean => {
  return user?.role === UserRole.SUPER_ADMIN;
};

export const isTenantAdmin = (user: User | null): boolean => {
  return user?.role === UserRole.TENANT_ADMIN;
};

export const isTeam = (user: User | null): boolean => {
  return user?.role === UserRole.TEAM;
};

export const isStudent = (user: User | null): boolean => {
  return user?.role === UserRole.STUDENT || user?.role === UserRole.USER;
};

export const getDefaultRedirectPath = (user: User | null): string => {
  if (!user) return "/login";

  switch (user.role) {
    case UserRole.SUPER_ADMIN:
      return "/admin/super";
    case UserRole.TENANT_ADMIN:
      return "/admin/tenant";
    case UserRole.TEAM:
      return "/admin/team";
    case UserRole.STUDENT:
    case UserRole.USER:
      return "/student";
    case UserRole.ADMIN: // Legacy support
      return "/admin";
    default:
      return "/student";
  }
};

export const isEmailVerified = (user: User | null): boolean => {
  return !!user?.email_verified_at;
};
