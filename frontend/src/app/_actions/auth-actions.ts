'use server';

import { revalidatePath } from 'next/cache';
import { cookies } from 'next/headers';
import axiosInstance from '@/lib/axios';
import { UserRole } from '@/types/user';

interface AuthResponse {
  error?: string;
  data?: {
    user: {
      id: number;
      name: string;
      email: string;
      role: UserRole;
    };
    token: string;
  };
}

interface GoogleUrlResponse {
  url: string;
}

function getErrorMessage(error: unknown): string {
  if (error instanceof Error) return error.message;
  if (typeof error === 'object' && error && 'message' in error) {
    return String(error.message);
  }
  return 'An unexpected error occurred';
}

const setCookie = async(token: string) => {
  const cookieStore = await cookies();
  cookieStore.set('auth_token', token, {
    httpOnly: true,
    secure: process.env.NODE_ENV === 'production',
    sameSite: 'lax',
    path: '/',
  });
};

const deleteCookie = async () => {
  const cookieStore = await cookies();
  cookieStore.delete('auth_token');
};

export async function login(formData: FormData) {
  try {
    const response = await axiosInstance.post<AuthResponse>('/api/auth/login', {
      email: formData.get('email'),
      password: formData.get('password'),
    });

    if (response.data.data?.token) {
      await setCookie(response.data.data.token);
    }

    revalidatePath('/login', 'page');
    return response.data;
  } catch (error) {
    return { error: getErrorMessage(error) };
  }
}

export async function register(formData: FormData) {
  try {
    const response = await axiosInstance.post<AuthResponse>('/api/auth/register', {
      name: formData.get('name'),
      email: formData.get('email'),
      password: formData.get('password'),
      password_confirmation: formData.get('password_confirmation'),
    });

    if (response.data.data?.token) {
      await setCookie(response.data.data.token);
    }

    revalidatePath('/register', 'page');
    return response.data;
  } catch (error) {
    return { error: getErrorMessage(error) };
  }
}

export async function handleGoogleCallback(code: string): Promise<string> {
  try {
    const response = await axiosInstance.post<AuthResponse>('/auth/google/callback', { code });

    if (response.data.data?.token) {
      await setCookie(response.data.data.token);
    }

    revalidatePath('/', 'layout');
    return response.data.data?.user.role === UserRole.ADMIN ? '/admin' : '/learn';
  } catch (error) {
    throw new Error(getErrorMessage(error));
  }
}

export async function logout() {
  try {
    await axiosInstance.post('/api/auth/logout');
    await deleteCookie();
    revalidatePath('/', 'layout');
    return { success: true };
  } catch (error) {
    return { error: getErrorMessage(error) };
  }
}

export async function getCurrentUser() {
  try {
    const response = await axiosInstance.get<AuthResponse>('/api/auth/me');
    return response.data;
  } catch (error) {
    return { error: getErrorMessage(error) };
  }
}

export async function forgotPassword(formData: FormData) {
  try {
    const response = await axiosInstance.post('/api/auth/forgot-password', {
      email: formData.get('email'),
    });
    return response.data;
  } catch (error) {
    return { error: getErrorMessage(error) };
  }
}

export async function resetPassword(formData: FormData) {
  try {
    const response = await axiosInstance.post('/api/auth/reset-password', {
      token: formData.get('token'),
      email: formData.get('email'),
      password: formData.get('password'),
      password_confirmation: formData.get('password_confirmation'),
    });
    return response.data;
  } catch (error) {
    return { error: getErrorMessage(error) };
  }
}

export async function updateProfile(formData: FormData) {
  try {
    const response = await axiosInstance.patch('/api/auth/profile', formData);
    revalidatePath('/profile', 'page');
    return response.data;
  } catch (error) {
    return { error: getErrorMessage(error) };
  }
}

export async function getGoogleAuthUrl(): Promise<string> {
  try {
    const response = await axiosInstance.get<GoogleUrlResponse>('/auth/google/url');
    return response.data.url;
  } catch (error) {
    throw new Error(getErrorMessage(error));
  }
}
