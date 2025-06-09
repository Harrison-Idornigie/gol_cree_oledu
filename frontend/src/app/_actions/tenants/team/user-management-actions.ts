'use server';

import { revalidatePath } from 'next/cache';
import axiosInstance from '@/lib/axios';
import { AdminInvite, AdminInviteFormData, AdminUserListResponse, User, UserRole } from '@/types/user';

function getErrorMessage(error: unknown): string {
  if (error instanceof Error) return error.message;
  if (typeof error === 'object' && error && 'message' in error) {
    return String(error.message);
  }
  return 'An unexpected error occurred';
}

export async function getAdminUsers() {
  try {
    const response = await axiosInstance.get<AdminUserListResponse>('/api/admin/users');
    return { data: response.data };
  } catch (error) {
    return { error: getErrorMessage(error) };
  }
}

export async function inviteAdmin(formData: AdminInviteFormData) {
  try {
    const response = await axiosInstance.post<{ invite: AdminInvite }>('/api/admin/invites', {
      ...formData,
      role: UserRole.ADMIN
    });
    revalidatePath('/admin/users', 'page');
    return { data: response.data };
  } catch (error) {
    return { error: getErrorMessage(error) };
  }
}

export async function revokeInvite(inviteId: number) {
  try {
    await axiosInstance.delete(`/api/admin/invites/${inviteId}`);
    revalidatePath('/admin/users', 'page');
    return { success: true };
  } catch (error) {
    return { error: getErrorMessage(error) };
  }
}

export async function removeAdmin(userId: number) {
  try {
    await axiosInstance.delete(`/api/admin/users/${userId}`);
    revalidatePath('/admin/users', 'page');
    return { success: true };
  } catch (error) {
    return { error: getErrorMessage(error) };
  }
}

export async function resendInvite(inviteId: number) {
  try {
    const response = await axiosInstance.post<{ invite: AdminInvite }>(
      `/api/admin/invites/${inviteId}/resend`
    );
    revalidatePath('/admin/users', 'page');
    return { data: response.data };
  } catch (error) {
    return { error: getErrorMessage(error) };
  }
}

export async function updateUserStatus(userId: number, isActive: boolean) {
  try {
    const response = await axiosInstance.patch<{ user: User }>(
      `/api/admin/users/${userId}/status`,
      { is_active: isActive }
    );
    revalidatePath('/admin/users', 'page');
    return { data: response.data };
  } catch (error) {
    return { error: getErrorMessage(error) };
  }
}

export async function acceptInvite(token: string) {
  try {
    const response = await axiosInstance.post<{ user: User }>('/api/admin/invites/accept', { token });
    revalidatePath('/admin/users', 'page');
    return { data: response.data };
  } catch (error) {
    return { error: getErrorMessage(error) };
  }
}

// Helper function to check if a user is an admin
export function checkIsAdmin(user?: User | null): boolean {
  return user?.role === UserRole.ADMIN;
}