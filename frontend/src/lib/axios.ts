import axios from "axios";

const axiosInstance = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_URL,
  withCredentials: true,
});

// Type for axios config that we need to modify
interface ConfigWithHeaders {
  headers?: Record<string, string>;
  url?: string;
  method?: string;
}

// Helper function to extract tenant slug from JWT token
function extractTenantSlugFromToken(token: string): string | null {
  try {
    // JWT tokens have 3 parts separated by dots
    const parts = token.split('.');
    if (parts.length !== 3) {
      console.log("[Server] Invalid JWT token format");
      return null;
    }

    // Decode the payload (second part)
    const payload = JSON.parse(atob(parts[1]));
    console.log("[Server] JWT payload:", JSON.stringify(payload, null, 2));

    // Extract tenant slug from the token payload - try multiple possible locations
    const tenantSlug = payload.tenant_slug ||
                      payload.tenant?.slug ||
                      payload.sub?.tenant_slug ||
                      payload.context?.tenant_slug ||
                      null;

    console.log("[Server] Extracted tenant slug:", tenantSlug);
    return tenantSlug;
  } catch (error) {
    console.error("Error extracting tenant slug from token:", error);
    return null;
  }
}

// Helper function to extract tenant slug from cookies
async function extractTenantSlugFromCookies(): Promise<string | null> {
  try {
    const { cookies } = await import("next/headers");
    const cookieStore = await cookies();

    // Get tenant slug from dedicated cookie
    const tenantSlug = cookieStore.get('tenant_slug')?.value;
    if (tenantSlug) {
      console.log("[Server] Extracted tenant slug from cookie:", tenantSlug);
      return tenantSlug;
    }

    return null;
  } catch (error) {
    console.error("Error extracting tenant slug from cookies:", error);
    return null;
  }
}

// Helper function to set auth token and tenant context for server-side requests
export async function setServerAuthToken(config: ConfigWithHeaders) {
  if (typeof window === "undefined") {
    try {
      const { cookies } = await import("next/headers");
      const cookieStore = await cookies();

      let token = cookieStore.get("auth_token")?.value;
      if (token) {
        token = decodeURIComponent(token);

        // Set authorization header
        if (!config.headers) {
          config.headers = {};
        }
        config.headers.Authorization = `Bearer ${token}`;

        // Try to extract tenant slug from cookies first, then from token as fallback
        let tenantSlug = await extractTenantSlugFromCookies();
        if (!tenantSlug) {
          tenantSlug = extractTenantSlugFromToken(token);
          console.log("[Server] Fallback: extracted tenant slug from token:", tenantSlug);
        }

        if (tenantSlug && config.url) {
          config.url = transformUrlWithTenant(config.url, tenantSlug);
          console.log("[Server] Transformed URL with tenant context:", config.url);
        } else {
          console.log("[Server] No tenant slug found, URL not transformed");
        }

        console.log("[Server] Setting auth token:", token.substring(0, 10) + "...");
      } else {
        console.log("[Server] No auth token found in cookies");
      }
    } catch (error) {
      console.error("Error setting auth token in server component:", error);
    }
  }
  return config;
}

// Helper function to extract tenant slug from current URL
function getCurrentTenantSlug(): string | null {
  if (typeof window === "undefined") return null;

  const pathSegments = window.location.pathname.replace(/^\//, '').split('/');
  if (pathSegments.length < 2) return null;

  const [tenantSlug, membership] = pathSegments;
  const validMemberships = ['admin', 'team', 'student'];

  return /^[a-z0-9-]+$/.test(tenantSlug) && validMemberships.includes(membership) ? tenantSlug : null;
}

// Helper function to transform URL to include tenant context
function transformUrlWithTenant(url: string, tenantSlug: string | null): string {
  if (!url.startsWith('/') || !tenantSlug) return url;

  // Skip if URL already has tenant context or is a central route
  if (url.match(/^\/api\/[a-z0-9-]+\//) ||
      url.startsWith('/api/public/') ||
      url.startsWith('/api/super-admin/') ||
      url.startsWith('/api/auth/register-tenant-admin') ||
      url.startsWith('/api/auth/validate-tenant-slug/') ||
      url.startsWith('/api/auth/central-')) {
    return url;
  }

  // Special handling for /auth/me endpoint
  if (url === '/auth/me') {
    return `/api/${tenantSlug}/auth/me`;
  }

  // Transform /api/auth/* to /api/{tenant}/auth/*
  if (url.startsWith('/api/auth/')) {
    return url.replace('/api/auth/', `/api/${tenantSlug}/auth/`);
  }

  // Transform other API routes to include tenant context
  if (url.startsWith('/api/')) {
    return url.replace('/api/', `/api/${tenantSlug}/`);
  }

  // Handle server action URLs that don't start with /api (e.g., /team/words, /admin/users)
  // Since baseURL already includes /api, we just need to add the tenant slug
  if (url.startsWith('/team/') || url.startsWith('/admin/') || url.startsWith('/student/')) {
    return `/${tenantSlug}${url}`;
  }

  return url;
}

// Request interceptor to add auth token and tenant context
axiosInstance.interceptors.request.use(
  (config) => {
    // Add tenant context to URL if needed
    const tenantSlug = getCurrentTenantSlug();
    if (config.url && tenantSlug) {
      config.url = transformUrlWithTenant(config.url, tenantSlug);
    }

    // In server components, we can get the token from the cookie directly
    if (typeof window === "undefined") {
      try {
        // For server components, we need to handle this synchronously
        // The async import will be handled at the module level or in the calling code
        // For now, we'll skip server-side token handling in the interceptor
        console.log("[Server] Skipping token handling in interceptor - should be handled at request level");
      } catch (error) {
        console.error("Error in server component interceptor:", error);
      }
    } else {
      // In client components, we can get the token from document.cookie
      const token = document.cookie
        .split("; ")
        .find((row) => row.startsWith("auth_token="))
        ?.split("=")[1];

      if (token && config.headers) {
        // Ensure the token is properly decoded
        const decodedToken = decodeURIComponent(token);
        config.headers.Authorization = `Bearer ${decodedToken}`;
        console.log("[Client] Setting auth token:", decodedToken.substring(0, 10) + "...");
      } else {
        console.log("[Client] No auth token found in cookies");
      }
    }

    console.log(`[${typeof window === "undefined" ? "Server" : "Client"}] API Request:`, config.method?.toUpperCase(), config.url);
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Define API response type for BaseAPIController format
interface ApiBaseResponse {
  success: boolean;
  data: unknown;
  message: string;
}

// Response interceptor for error handling
axiosInstance.interceptors.response.use(
  (response) => {
    // Extract the actual data from the response using our BaseAPIController format
    const responseData = response.data as ApiBaseResponse;
    if (responseData && typeof responseData.success !== "undefined") {
      // Return the actual data and message
      return {
        ...response,
        data: responseData.data || {},
        message: responseData.message || "",
        success: responseData.success,
      };
    }
    return response;
  },
  (error) => {
    // Check if error is from axios
    if (error?.isAxiosError) {
      // Handle token expiration
      if (error.response?.status === 401) {
        if (typeof window === "undefined") {
          // For server-side, we can't easily clear cookies in interceptors
          // This should be handled at the application level
          console.log("[Server] Token expired - should be handled at application level");
        } else {
          // In client components, we can set the cookie directly
          document.cookie = "auth_token=; max-age=0; path=/;";
        }
      }

      // Extract error message from our BaseAPIController format
      let errorMessage = error.response?.data?.message || error.message;

      // If there are validation errors, format them nicely
      if (error.response?.data?.errors) {
        const validationErrors = error.response.data.errors;
        const errorMessages = [];

        for (const [, messages] of Object.entries(validationErrors)) {
          if (Array.isArray(messages)) {
            errorMessages.push(...messages);
          } else {
            errorMessages.push(String(messages));
          }
        }

        if (errorMessages.length > 0) {
          errorMessage = errorMessages.join('. ');
        }
      }

      throw new Error(errorMessage);
    }
    throw new Error("An unexpected error occurred");
  }
);

export interface ApiResponse<T> {
  data: T;
  message: string;
  success: boolean;
}

export default axiosInstance;
