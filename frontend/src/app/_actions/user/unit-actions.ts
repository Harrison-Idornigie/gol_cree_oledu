"use server";

import axiosInstance from "@/lib/axios";
import { ApiResponse } from "@/lib/axios";

interface Unit {
  id: number;
  learning_path_id: number;
  title: string;
  description: string;
  order: number;
  status: string;
  review_status: string;
  is_unlocked: boolean;
  lessons?: any[];
   progress?: any;
}

/**
 * Get a specific unit
 */
export async function getUnit(unitId: number) {
  try {
    const response = await axiosInstance.get<ApiResponse<Unit>>(
      `/api/units/${unitId}?with_lessons=1&with_progress=1`
    );
    return response.data.data;
  } catch (error) {
    console.error("Error fetching unit:", error);
    return null;
  }
}

/**
 * Get units for a learning path
 */
export async function getUnitsForPath(pathId: number) {
  try {
    const response = await axiosInstance.get<ApiResponse<Unit[]>>(
      `/api/learning-paths/${pathId}/units?with_progress=1`
    );
    return response.data.data || [];
  } catch (error) {
    console.error("Error fetching units for path:", error);
    return [];
  }
}

/**
 * Get unit progress
 */
export async function getUnitProgress(unitId: number) {
  try {
    const response = await axiosInstance.get<ApiResponse<{
      unit_progress: string;
      completion_percentage: number;
      lessons_progress: Array<{
        lesson_id: number;
        status: string;
      }>;
      is_unlocked: boolean;
      unlocked_lessons: number[];
    }>>(`/api/units/${unitId}/progress`);
    
    return response.data.data;
  } catch (error) {
    console.error("Error fetching unit progress:", error);
    return null;
  }
}
