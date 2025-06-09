"use server";

import { revalidatePath } from "next/cache";
import { cookies } from "next/headers";
import axiosInstance from "@/lib/axios";
import { UserType, getDefaultRedirectPath } from "@/types/tenant/user";

interface AuthResponse {
  error?: string;
  success?: boolean;
  message?: string;
  user?: {
    id: number;
    name: string;
    email: string;
    role: UserType;
    email_verified_at?: string | null;
    avatar_url?: string | null;
    avatar?: string | null;
    points?: number;
    created_at?: string;
    updated_at?: string;
    tenant_id?: string;
    tenant?: {
      id: string;
      name: string;
      slug: string;
      status: string;
    };
  };
  token?: string;
}

interface TenantRegistrationData {
  organizationName: string;
  organizationSlug?: string;
  organizationDescription?: string;
  adminName: string;
  adminEmail: string;
  password: string;
  passwordConfirmation: string;
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

    // Use proper role-based redirection
    const redirectPath = getDefaultRedirectPath(response.data?.user || null);
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

    // Use proper role-based redirection
    const redirectPath = getDefaultRedirectPath(response.data?.user || null);
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

// Tenant-specific auth actions
export async function registerTenantAdmin(data: TenantRegistrationData) {
  try {
    // Generate slug if not provided
    const tenantSlug = data.organizationSlug ||
      data.organizationName
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '')
        .substring(0, 50);

    const response = await axiosInstance.post<AuthResponse>("/auth/register-tenant-admin", {
      tenant: {
        name: data.organizationName,
        slug: tenantSlug,
        description: data.organizationDescription || '',
      },
      admin: {
        name: data.adminName,
        email: data.adminEmail,
        password: data.password,
        password_confirmation: data.passwordConfirmation,
      }
    });

    if (response.data?.token) {
      await setCookie(response.data.token);
    }

    revalidatePath("/register", "page");

    // Build tenant-specific redirect path
    const redirectPath = response.data?.user?.tenant?.slug
      ? `/${response.data.user.tenant.slug}/admin`
      : "/admin";

    return {
      success: true,
      redirect: redirectPath,
      data: response.data
    };
  } catch (error) {
    return { error: getErrorMessage(error) };
  }
}

export async function handleTenantGoogleCallback(code: string, state?: string, tenantSlug?: string) {
  try {
    const response = await axiosInstance.post<AuthResponse>(
      "/auth/google/tenant-callback",
      { code, state, tenant_slug: tenantSlug }
    );

    if (response.data?.token) {
      await setCookie(response.data.token);
    }

    revalidatePath("/", "layout");

    // Build tenant-specific redirect path
    if (response.data?.user?.tenant?.slug) {
      const userTenantSlug = response.data.user.tenant.slug;
      const userRole = response.data.user.role;

      let redirectPath = `/${userTenantSlug}/student`; // Default

      switch (userRole) {
        case UserType.TENANT_ADMIN:
          redirectPath = `/${userTenantSlug}/admin`;
          break;
        case UserType.TEAM:
          redirectPath = `/${userTenantSlug}/team`;
          break;
        case UserType.STUDENT:
        case UserType.USER:
          redirectPath = `/${userTenantSlug}/student`;
          break;
      }

      return { ...response.data, redirect: redirectPath };
    }

    return response.data;
  } catch (error) {
    throw new Error(getErrorMessage(error));
  }
}

// Tenant slug validation
export async function validateTenantSlug(slug: string) {
  try {
    const response = await axiosInstance.get<{available: boolean; error?: string}>(`/auth/validate-tenant-slug/${slug}`);
    return {
      available: response.data.available || false,
      error: response.data.error || null
    };
  } catch {
    return {
      available: false,
      error: "Unable to validate slug availability"
    };
  }
}
