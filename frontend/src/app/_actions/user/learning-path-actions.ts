"use server";

import axiosInstance from "@/lib/axios";
import { revalidatePath } from "next/cache";
import {
  ApiResponse,
  Language,
  LearningPath,
  UserProgress,
} from "@/types/learning-path";

// Get all learning paths with optional filters
export async function getLearningPaths(filters?: {
  language_id?: number;
  target_level?: string;
  with_language?: boolean;
  with_units?: boolean;
}) {
  try {
    const params = new URLSearchParams();

    if (filters?.language_id) {
      params.append("language_id", filters.language_id.toString());
    }

    if (filters?.target_level) {
      params.append("target_level", filters.target_level);
    }

    if (filters?.with_language) {
      params.append("with_language", "true");
    }

    if (filters?.with_units) {
      params.append("with_units", "true");
    }

    const response = await axiosInstance.get<ApiResponse<LearningPath[]>>(
      `/learning-paths?${params.toString()}`
    );

    // Transform the data to match the expected format in components
    const transformedData = response.data.data.map((path: LearningPath) => ({
      ...path,
      name: path.title, // Map title to name for compatibility
      units: path.units_count || 0,
      unitsCompleted: 0, // This will be updated with actual progress data
      unlocked: path.status === "published",
    }));

    return {
      data: transformedData,
      error: null,
    };
  } catch (error) {
    console.error("Error fetching learning paths:", error);
    return {
      data: null,
      error: "Failed to fetch learning paths",
    };
  }
}

// Get learning paths by language
export async function getLearningPathsByLanguage(languageId: number) {
  try {
    const response = await axiosInstance.get<ApiResponse<LearningPath[]>>(
      `/learning-paths/by-language/${languageId}`
    );

    const transformedData = response.data.data.map((path: LearningPath) => ({
      ...path,
      name: path.title,
      units: path.units_count || 0,
      unitsCompleted: 0,
      unlocked: path.status === "published",
    }));

    return {
      data: transformedData,
      error: null,
    };
  } catch (error) {
    console.error("Error fetching learning paths by language:", error);
    return {
      data: null,
      error: "Failed to fetch learning paths by language",
    };
  }
}

// Get learning paths by proficiency level
export async function getLearningPathsByLevel(level: string) {
  try {
    const response = await axiosInstance.get<ApiResponse<LearningPath[]>>(
      `/learning-paths/by-level/${level}`
    );

    const transformedData = response.data.data.map((path: LearningPath) => ({
      ...path,
      name: path.title,
      units: path.units_count || 0,
      unitsCompleted: 0,
      unlocked: path.status === "published",
    }));

    return {
      data: transformedData,
      error: null,
    };
  } catch (error) {
    console.error("Error fetching learning paths by level:", error);
    return {
      data: null,
      error: "Failed to fetch learning paths by level",
    };
  }
}

// Get available languages with learning paths
export async function getLanguagesWithLearningPaths() {
  try {
    const response = await axiosInstance.get<ApiResponse<Language[]>>(
      "/languages/with-learning-paths"
    );
    return {
      data: response.data.data,
      error: null,
    };
  } catch (error) {
    console.error("Error fetching languages:", error);
    return {
      data: null,
      error: "Failed to fetch languages",
    };
  }
}

// Enroll in a learning path
export async function enrollInLearningPath(pathId: number) {
  try {
    const response = await axiosInstance.post<ApiResponse<UserProgress>>(
      `/learning-paths/${pathId}/enroll`
    );

    // Revalidate the learning path page to reflect enrollment
    revalidatePath(`/learn/path/${pathId}`);
    revalidatePath("/learn");

    return {
      data: response.data.data,
      message:
        response.data.message || "Successfully enrolled in learning path",
      error: null,
    };
  } catch (error) {
    console.error("Error enrolling in learning path:", error);
    return {
      data: null,
      message: null,
      error: "Failed to enroll in learning path",
    };
  }
}

// Get user progress for a learning path
export async function getLearningPathProgress(pathId: number) {
  try {
    const response = await axiosInstance.get<
      ApiResponse<{
        learning_path_progress: string;
        units_progress: Array<{
          unit_id: number;
          status: string;
          completion_percentage: number;
        }>;
      }>
    >(`/learning-paths/${pathId}/progress`);

    return {
      data: response.data.data,
      error: null,
    };
  } catch (error) {
    console.error("Error fetching learning path progress:", error);
    return {
      data: null,
      error: "Failed to fetch learning path progress",
    };
  }
}
