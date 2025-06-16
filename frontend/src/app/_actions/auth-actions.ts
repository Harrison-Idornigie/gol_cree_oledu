"use server";

import { revalidatePath } from "next/cache";
import { cookies } from "next/headers";
import axiosInstance from "@/lib/axios";
import { UserType, getDefaultRedirectPath, User } from "@/types/tenant/user";

interface AuthResponse {
  error?: string;
  success?: boolean;
  message?: string;
  user?: {
    id: number;
    name: string;
    email: string;
    membership: UserType;
    email_verified_at?: string | null;
    avatar_url?: string | null;
    avatar?: string | null;
    points?: number;
    created_at?: string;
    updated_at?: string;
    is_active?: boolean;
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

/**
 * Set tenant slug cookie for server actions
 */
export async function setTenantSlugCookie(tenantSlug: string) {
  try {
    const cookieStore = await cookies();
    cookieStore.set("tenant_slug", tenantSlug, {
      httpOnly: true,
      secure: process.env.NODE_ENV === "production",
      sameSite: "lax",
      path: "/",
      maxAge: 60 * 60 * 24 * 7, // 7 days
    });
    console.log("[Server] Set tenant slug cookie:", tenantSlug);
  } catch (error) {
    console.error("Error setting tenant slug cookie:", error);
  }
}

/**
 * Clear tenant slug cookie
 */
export async function clearTenantSlugCookie() {
  try {
    const cookieStore = await cookies();
    cookieStore.delete("tenant_slug");
    console.log("[Server] Cleared tenant slug cookie");
  } catch (error) {
    console.error("Error clearing tenant slug cookie:", error);
  }
}

const deleteCookie = async () => {
  const cookieStore = await cookies();
  cookieStore.delete("auth_token");
};

export async function login(formData: FormData, tenantSlug?: string) {
  try {
    // Use tenant-specific login endpoint if tenant slug is provided
    const loginUrl = tenantSlug ? `/${tenantSlug}/auth/login` : "/auth/login";

    const response = await axiosInstance.post<AuthResponse>(loginUrl, {
      email: formData.get("email"),
      password: formData.get("password"),
    });

    console.log("Login response:", response);

    if (response.data?.token) {
      await setCookie(response.data.token);
    }

    revalidatePath("/login", "page");

    // Use proper membership-based redirection with tenant context
    const redirectPath = getDefaultRedirectPath(response.data?.user as User | null);
    return {
      success: true,
      redirect: redirectPath,
      user: response.data?.user,
      tenant: response.data?.user?.tenant
    };
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

    // Use proper membership-based redirection
    const redirectPath = getDefaultRedirectPath(response.data?.user as User | null);
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

/**
 * Get current user information based on context
 * Manually determines the correct endpoint since server actions can't use axios interceptor tenant detection
 *
 * Security: Uses URL-based tenant identification with backend validation.
 * The backend middleware validates tenant access regardless of URL manipulation.
 */
export async function getCurrentUser(tenantSlug?: string) {
  try {
    const cookieStore = await cookies();
    const token = cookieStore.get('auth_token')?.value;

    if (!token) {
      return { error: 'No authentication token' };
    }

    console.log("********************* Tenant slug in getCurrentuser method before api call: " +  tenantSlug)
    // Manually determine endpoint since server actions can't use axios interceptor tenant detection
    const endpoint = tenantSlug
      ? `/${tenantSlug}/auth/me`
      : '/auth/central-me';

    console.log('🔍 getCurrentUser: Using endpoint:', endpoint, tenantSlug ? `(tenant: ${tenantSlug})` : '(central)');

    // Make the API call with the determined endpoint
    const response = await axiosInstance.get<AuthResponse>(endpoint, {
      headers: {
        'Authorization': `Bearer ${token}`
      }
    });

    console.log("********************* Tenant slug in getCurrentuser method after api call: " +  tenantSlug)

    // Security validation: Ensure returned user data matches expected tenant context
    if (tenantSlug && response.data.user?.tenant?.slug !== tenantSlug) {
      console.error('🚨 Security: Tenant mismatch detected', {
        urlTenant: tenantSlug,
        userTenant: response.data.user?.tenant?.slug
      });
      return { error: 'Tenant access validation failed' };
    }

    return {
      user: response.data.user,
      success: true
    };
  } catch (error) {
    console.error('❌ getCurrentUser error:', error);
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
    console.log('📤 Sending registration request to API...');

    // Let backend handle slug generation and validation
    const response = await axiosInstance.post<AuthResponse>("/auth/register-tenant-admin", {
      tenant: {
        name: data.organizationName,
        slug: data.organizationSlug || '', // Send empty string if not provided, backend will generate
        description: data.organizationDescription || '',
      },
      admin: {
        name: data.adminName,
        email: data.adminEmail,
        password: data.password,
        password_confirmation: data.passwordConfirmation,
      }
    });

    console.log('📥 API response received:', {
      status: response.status,
      hasData: !!response.data,
      hasToken: !!response.data?.token,
      hasUser: !!response.data?.user,
      hasTenant: !!response.data?.user?.tenant,
      tenantSlug: response.data?.user?.tenant?.slug
    });

    if (response.data?.token) {
      console.log('🍪 Setting authentication cookie...');
      await setCookie(response.data.token);
    }

    revalidatePath("/register", "page");

    // Build tenant-specific redirect path
    const redirectPath = response.data?.user?.tenant?.slug
      ? `/${response.data.user.tenant.slug}/admin/dashboard`
      : "/admin/dashboard";

    console.log('🎯 Built redirect path:', redirectPath);

    return {
      success: true,
      redirect: redirectPath,
      data: response.data
    };
  } catch (error: unknown) {
    console.error('💥 Registration error:', error);
    if (error && typeof error === 'object' && 'response' in error) {
      const axiosError = error as { response?: { status?: number; data?: unknown; headers?: unknown } };
      console.error('📋 Error response:', {
        status: axiosError.response?.status,
        data: axiosError.response?.data,
        headers: axiosError.response?.headers
      });
    }
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
      const userMembership = response.data.user.membership;

      let redirectPath = `/${userTenantSlug}/student`; // Default

      switch (userMembership) {
        case UserType.TENANT_ADMIN:
          redirectPath = `/${userTenantSlug}/admin/dashboard`;
          break;
        case UserType.TEAM:
          redirectPath = `/${userTenantSlug}/team/dashboard`;
          break;
        case UserType.STUDENT:
        case UserType.USER:
          redirectPath = `/${userTenantSlug}/student/dashboard`;
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

// Check if tenant exists (for routing validation)
export async function checkTenantExists(slug: string) {
  try {
    const response = await axiosInstance.get<{available: boolean; error?: string}>(`/auth/validate-tenant-slug/${slug}`);
    // If available is false, it means the tenant exists
    return {
      exists: !response.data.available,
      error: null
    };
  } catch {
    return {
      exists: false,
      error: "Unable to check tenant existence"
    };
  }
}

/**
 * Get tenant creation progress
 */
export async function getTenantCreationProgress(progressId: string) {
  try {
    const response = await axiosInstance.get(`/auth/tenant-creation-progress/${progressId}`);
    return {
      data: response.data,
      error: null
    };
  } catch (error) {
    console.error('Error fetching tenant creation progress:', error);
    return {
      data: null,
      error: 'Failed to fetch progress'
    };
  }
}
