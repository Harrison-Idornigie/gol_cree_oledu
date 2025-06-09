"use server";

import axiosInstance from "@/lib/axios";
import { ApiResponse } from "@/types/learning-path";
import { revalidatePath } from "next/cache";

export interface InterfaceLanguage {
  id: number;
  code: string;
  name: string;
  native_name: string;
}

export interface UserSettings {
  interface_language: string;
}

// Get user settings
export async function getUserSettings() {
  try {
    const response = await axiosInstance.get<ApiResponse<UserSettings>>(
      "/user/settings"
    );

    return {
      success: true,
      data: response.data.data,
      error: null,
    };
  } catch (error) {
    console.error("Error fetching user settings:", error);
    return {
      success: false,
      data: null,
      error: "Failed to fetch user settings",
    };
  }
}

// Get available interface languages
export async function getAvailableInterfaceLanguages() {
  try {
    const response = await axiosInstance.get<ApiResponse<InterfaceLanguage[]>>(
      "/user/settings/languages"
    );

    return {
      success: true,
      data: response.data.data,
      error: null,
    };
  } catch (error) {
    console.error("Error fetching available interface languages:", error);
    return {
      success: false,
      data: null,
      error: "Failed to fetch available interface languages",
    };
  }
}

// Update interface language
export async function updateInterfaceLanguage(languageCode: string) {
  try {
    const response = await axiosInstance.patch<
      ApiResponse<{ success: boolean; interface_language: string }>
    >("/user/settings/interface-language", {
      language_code: languageCode,
    });

    // Revalidate relevant paths
    revalidatePath("/account");
    revalidatePath("/student");
    revalidatePath("/profile");

    return {
      success: true,
      data: response.data.data,
      error: null,
    };
  } catch (error) {
    console.error("Error updating interface language:", error);
    return {
      success: false,
      data: null,
      error: "Failed to update interface language",
    };
  }
}
