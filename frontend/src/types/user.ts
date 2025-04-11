export enum UserRole {
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
  return user?.role === UserRole.ADMIN;
};

export const getDefaultRedirectPath = (user: User | null): string => {
  return user?.role === UserRole.ADMIN ? "/admin" : "/learn";
};

export const isEmailVerified = (user: User | null): boolean => {
  return !!user?.email_verified_at;
};
