"use server";

import axiosInstance from "@/lib/axios";
import { ApiResponse, Language } from "@/types/learning-path";
import { revalidatePath } from "next/cache";
import { cookies } from "next/headers";
import axios from "axios";

// Get all available languages
export async function getAllLanguages() {
  try {
    const response = await axiosInstance.get<ApiResponse<Language[]>>(
      "/languages?with_learning_paths_count=true"
    );

    // Check if response.data.data exists
    if (!response.data || !response.data.data) {
      console.error("Invalid response format:", response);
      return {
        data: [],
        error: "Invalid response format from server",
      };
    }

    return {
      data: response.data.data,
      error: null,
    };
  } catch (error) {
    console.error("Error fetching all languages:", error);
    return {
      data: [],
      error: "Failed to fetch languages",
    };
  }
}

// Get user's selected languages
export async function getSelectedLanguages() {
  try {
    // Get the auth token from cookies
    const cookieStore = await cookies();
    const token = cookieStore.get("auth_token")?.value;

    if (!token) {
      console.error("No auth token found in cookies");
      return {
        data: null,
        error: "Authentication required",
      };
    }

    // Make the API call with the token explicitly set in headers
    const response = await axios.get<{ data: Language[] }>(
      `${process.env.NEXT_PUBLIC_API_URL}/user/selected-languages`,
      {
        headers: {
          Authorization: `Bearer ${token}`,
        },
      }
    );

    // Check if response.data exists
    if (!response.data) {
      console.error("Invalid response format:", response);
      return {
        data: [],
        error: "Invalid response format from server",
      };
    }

    return {
      data: response.data.data || [],
      error: null,
    };
  } catch (error) {
    console.error("Error fetching selected languages:", error);
    return {
      data: null,
      error: "Failed to fetch selected languages",
    };
  }
}

// Select a language to learn
export async function selectLanguage(languageId: number) {
  try {
    const response = await axiosInstance.post<
      ApiResponse<{ success: boolean }>
    >("/user/selected-languages", {
      language_id: languageId,
    });

    // Check if response.data exists
    if (!response.data) {
      console.error("Invalid response format:", response);
      return {
        success: false,
        data: null,
        error: "Invalid response format from server",
      };
    }

    // Revalidate relevant paths
    revalidatePath("/languages");
    revalidatePath("/learn");
    revalidatePath("/profile");

    return {
      success: true,
      data: response.data.data,
      error: null,
    };
  } catch (error) {
    console.error("Error selecting language:", error);
    return {
      success: false,
      data: null,
      error: "Failed to select language",
    };
  }
}

// Remove a language from selected languages
export async function unselectLanguage(languageId: number) {
  try {
    const response = await axiosInstance.delete<
      ApiResponse<{ success: boolean }>
    >(`/user/selected-languages/${languageId}`);

    // Check if response.data exists
    if (!response.data) {
      console.error("Invalid response format:", response);
      return {
        success: false,
        data: null,
        error: "Invalid response format from server",
      };
    }

    // Revalidate relevant paths
    revalidatePath("/languages");
    revalidatePath("/learn");
    revalidatePath("/profile");

    return {
      success: true,
      data: response.data.data,
      error: null,
    };
  } catch (error) {
    console.error("Error unselecting language:", error);
    return {
      success: false,
      data: null,
      error: "Failed to unselect language",
    };
  }
}

// Get language details
export async function getLanguageDetails(languageId: number) {
  try {
    const response = await axiosInstance.get<ApiResponse<Language>>(
      `/languages/${languageId}`
    );

    // Check if response.data exists
    if (!response.data || !response.data.data) {
      console.error("Invalid response format:", response);
      return {
        data: null,
        error: "Invalid response format from server",
      };
    }

    return {
      data: response.data.data,
      error: null,
    };
  } catch (error) {
    console.error("Error fetching language details:", error);
    return {
      data: null,
      error: "Failed to fetch language details",
    };
  }
}
