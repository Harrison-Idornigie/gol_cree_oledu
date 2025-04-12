import axios from "axios";

import { cookies } from "next/headers";

const axiosInstance = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_URL,
  withCredentials: true,
});

// Request interceptor to add auth token
axiosInstance.interceptors.request.use(
  async (config: any) => {
    const cookieStore = await cookies();
    const token = cookieStore.get("auth_token")?.value;

    if (token && config.headers) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Define API response type for BaseAPIController format
interface ApiBaseResponse {
  success: boolean;
  data: any;
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
        const cookieStore = await cookies();
        cookieStore.set("auth_token", "", { maxAge: 0 }); // This effectively deletes the cookie
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
