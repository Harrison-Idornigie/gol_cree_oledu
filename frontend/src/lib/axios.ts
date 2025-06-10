import axios from "axios";

const axiosInstance = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_URL,
  withCredentials: true,
});

// Helper function to extract tenant slug from current URL
function getCurrentTenantSlug(): string | null {
  if (typeof window === "undefined") return null;

  const pathSegments = window.location.pathname.replace(/^\//, '').split('/');
  if (pathSegments.length < 2) return null;

  const [tenantSlug, role] = pathSegments;
  const validRoles = ['admin', 'team', 'student'];

  return /^[a-z0-9-]+$/.test(tenantSlug) && validRoles.includes(role) ? tenantSlug : null;
}

// Helper function to transform URL to include tenant context
function transformUrlWithTenant(url: string, tenantSlug: string | null): string {
  if (!tenantSlug || !url.startsWith('/')) return url;

  // Skip if URL already has tenant context or is a central route
  if (url.match(/^\/api\/[a-z0-9-]+\//) ||
      url.startsWith('/api/public/') ||
      url.startsWith('/api/super-admin/') ||
      url.startsWith('/api/auth/register-tenant-admin')) {
    return url;
  }

  // Transform /api/auth/* to /api/{tenant}/auth/*
  if (url.startsWith('/api/auth/')) {
    return url.replace('/api/auth/', `/api/${tenantSlug}/auth/`);
  }

  // Transform other API routes to include tenant context
  if (url.startsWith('/api/')) {
    return url.replace('/api/', `/api/${tenantSlug}/`);
  }

  return url;
}

// Request interceptor to add auth token and tenant context
axiosInstance.interceptors.request.use(
  async (config) => {
    // Add tenant context to URL if needed
    const tenantSlug = getCurrentTenantSlug();
    if (config.url && tenantSlug) {
      config.url = transformUrlWithTenant(config.url, tenantSlug);
    }

    // In server components, we can get the token from the cookie directly
    if (typeof window === "undefined") {
      try {
        // For server components, extract token from the request headers
        // This is a more reliable way to get the token in server components
        const { cookies } = await import("next/headers");
        const cookieStore = await cookies();

        // Get the token and make sure it's properly decoded
        let token = cookieStore.get("auth_token")?.value;
        if (token) {
          // Ensure the token is properly decoded
          token = decodeURIComponent(token);

          if (config.headers) {
            // Set the Authorization header with the Bearer token
            config.headers.Authorization = `Bearer ${token}`;
            console.log("[Server] Setting auth token:", token.substring(0, 10) + "...");
          }
        } else {
          console.log("[Server] No auth token found in cookies");
        }
      } catch (error) {
        console.error("Error setting auth token in server component:", error);
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
  async (error) => {
    // Check if error is from axios
    if (error?.isAxiosError) {
      // Handle token expiration
      if (error.response?.status === 401) {
        if (typeof window === "undefined") {
          try {
            const { cookies } = await import("next/headers");
            const cookieStore = await cookies();
            cookieStore.set("auth_token", "", { maxAge: 0 }); // This effectively deletes the cookie
          } catch (error) {
            console.error(
              "Error handling token expiration in server component:",
              error
            );
          }
        } else {
          // In client components, we can set the cookie directly
          document.cookie = "auth_token=; max-age=0; path=/;";
        }
      }

      // Extract error message from our BaseAPIController format
      const errorMessage =
        error.response?.data?.message ||
        error.response?.data?.error ||
        error.message;

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
