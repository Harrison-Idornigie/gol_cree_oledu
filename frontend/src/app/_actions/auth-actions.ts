"use server";

import { revalidatePath } from "next/cache";
import { cookies } from "next/headers";
import axiosInstance from "@/lib/axios";
import { UserRole } from "@/types/tenant/user";

interface AuthResponse {
  error?: string;
  success?: boolean;
  message?: string;
  user?: {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    email_verified_at?: string | null;
    avatar_url?: string | null;
    avatar?: string | null;
    points?: number;
    created_at?: string;
    updated_at?: string;
  };
  token?: string;
}

interface GoogleUrlResponse {
  url: string;
}

function getErrorMessage(error: unknown): string {
  if (error instanceof Error) return error.message;
  if (typeof error === "object" && error && "message" in error) {
    return String(error.message);
  }
  return "An unexpected error occurred";
}

const setCookie = async (token: string) => {
  const cookieStore = await cookies();
  cookieStore.set("auth_token", token, {
    httpOnly: true,
    secure: process.env.NODE_ENV === "production",
    sameSite: "lax",
    path: "/",
  });
};

const deleteCookie = async () => {
  const cookieStore = await cookies();
  cookieStore.delete("auth_token");
};

export async function login(formData: FormData) {
  try {
    const response = await axiosInstance.post<AuthResponse>("/auth/login", {
      email: formData.get("email"),
      password: formData.get("password"),
    });

    console.log("Login response:", response);

    if (response.data?.token) {
      await setCookie(response.data.token);
    }

    revalidatePath("/login", "page");

    // Add redirect path based on user role
    const redirectPath =
      response.data?.user?.role === "admin" ? "/admin" : "/student";
    return { success: true, redirect: redirectPath };
  } catch (error) {
    return { error: getErrorMessage(error) };
  }
}

export async function register(formData: FormData) {
  try {
    const response = await axiosInstance.post<AuthResponse>("/auth/register", {
      name: formData.get("name"),
      email: formData.get("email"),
      password: formData.get("password"),
      password_confirmation: formData.get("password_confirmation"),
    });

    if (response.data?.token) {
      await setCookie(response.data.token);
    }

    revalidatePath("/register", "page");

    // Add redirect path based on user role, similar to login function
    const redirectPath =
      response.data?.user?.role === "admin" ? "/admin" : "/student";
    return { ...response.data, redirect: redirectPath };
  } catch (error) {
    return { error: getErrorMessage(error) };
  }
}

export async function handleGoogleCallback(code: string, state?: string) {
  try {
    const response = await axiosInstance.post<AuthResponse>(
      "/auth/google/callback",
      { code, state }
    );

    if (response.data?.token) {
      await setCookie(response.data.token);
    }

    revalidatePath("/", "layout");
    return response.data;
  } catch (error) {
    throw new Error(getErrorMessage(error));
  }
}

export async function logout() {
  try {
    await axiosInstance.post("/auth/logout");
    await deleteCookie();
    revalidatePath("/", "layout");
    return { success: true };
  } catch (error) {
    return { error: getErrorMessage(error) };
  }
}

export async function getCurrentUser() {
  try {
    const response = await axiosInstance.get<AuthResponse>("/auth/me");
    return response.data;
  } catch (error) {
    return { error: getErrorMessage(error) };
  }
}

export async function forgotPassword(email: string) {
  try {
    const response = await axiosInstance.post<AuthResponse>(
      "/auth/password/email",
      {
        email: email,
      }
    );
    return {
      success: true,
      message: response.data?.message || "Password reset link sent",
    };
  } catch (error) {
    return { error: getErrorMessage(error), success: false };
  }
}

export async function resetPassword(data: {
  token: string;
  email: string;
  password: string;
  password_confirmation: string;
}) {
  try {
    const response = await axiosInstance.post<AuthResponse>(
      "/auth/password/reset",
      data
    );
    return {
      success: true,
      message: response.data?.message || "Password has been reset",
    };
  } catch (error) {
    return { error: getErrorMessage(error), success: false };
  }
}

export async function resendVerificationEmail() {
  try {
    const response = await axiosInstance.post<AuthResponse>(
      "/auth/email/verification-notification"
    );
    return {
      success: true,
      message: response.data?.message || "Verification email sent",
    };
  } catch (error) {
    return { error: getErrorMessage(error), success: false };
  }
}

export async function updateProfile(formData: FormData) {
  try {
    const response = await axiosInstance.patch("/auth/profile", formData);
    revalidatePath("/profile", "page");
    return response.data;
  } catch (error) {
    return { error: getErrorMessage(error) };
  }
}

export async function getGoogleAuthUrl(): Promise<string> {
  try {
    const response = await axiosInstance.get<GoogleUrlResponse>(
      "/auth/google/url"
    );
    return response.data.url;
  } catch (error) {
    throw new Error(getErrorMessage(error));
  }
}
