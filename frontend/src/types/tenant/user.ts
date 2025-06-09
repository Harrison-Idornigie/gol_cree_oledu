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

  switch (user.role) {
    case UserType.SUPER_ADMIN:
      return "/super"; // Super Admin portal
    case UserType.TENANT_ADMIN:
      return "/admin"; // Tenant Admin portal
    case UserType.TEAM:
      return "/team"; // Teacher/Team portal
    case UserType.STUDENT:
      return "/student"; // Student portal
    case UserType.USER:
      return "/login"; // Regular user, redirect to login
    default:
      return "/student";
  }
};

export const isEmailVerified = (user: User | null): boolean => {
  return !!user?.email_verified_at;
};
