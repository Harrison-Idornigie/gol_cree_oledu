"use server";

import { revalidatePath } from "next/cache";
import axiosInstance from "@/lib/axios";
import { Lesson } from "@/types/tenant/lesson";
import { Section } from "@/types/section";

interface APIResponse {
  id: number;
  topic_id: number;
  title: string;
  description: string;
  slug?: string;
  order: number;
  status: "draft" | "published" | "archived";
  estimated_time?: number;
  xp_reward?: number;
  difficulty_level: string;
  exercises: any[];
  created_at: string;
  updated_at: string;
}

function getErrorMessage(error: unknown): string {
  if (error instanceof Error) return error.message;
  if (typeof error === "object" && error && "message" in error) {
    return String(error.message);
  }
  return "An unexpected error occurred";
}

function transformAPIResponse(data: APIResponse): Lesson {
  return {
    ...data,
    slug: data.slug || `lesson-${data.id}`,
    estimated_time: data.estimated_time || 0,
    xp_reward: data.xp_reward || 0,
    exercises: data.exercises || [],
    is_published: data.status === "published",
  };
}

export async function createLesson(formData: FormData) {
  try {
    const response = await axiosInstance.post<APIResponse>(
      "/api/admin/lessons",
      formData
    );
    revalidatePath("/admin/units/[id]", "page");
    revalidatePath("/admin/lessons", "page");
    return { data: transformAPIResponse(response.data) };
  } catch (error) {
    return { error: getErrorMessage(error) };
  }
}

export async function updateLesson(id: number, formData: FormData) {
  try {
    const response = await axiosInstance.put<APIResponse>(
      `/api/admin/lessons/${id}`,
      formData
    );
    revalidatePath("/admin/units/[id]", "page");
    revalidatePath("/admin/lessons/[id]", "page");
    return { data: transformAPIResponse(response.data) };
  } catch (error) {
    return { error: getErrorMessage(error) };
  }
}

export async function deleteLesson(id: number) {
  try {
    await axiosInstance.delete(`/api/admin/lessons/${id}`);
    revalidatePath("/admin/units/[id]", "page");
    revalidatePath("/admin/lessons", "page");
    return { success: true };
  } catch (error) {
    return { error: getErrorMessage(error) };
  }
}

export async function getLesson(id: number) {
  try {
    const response = await axiosInstance.get<APIResponse>(
      `/api/admin/lessons/${id}`
    );
    return { data: transformAPIResponse(response.data) };
  } catch (error) {
    return { error: getErrorMessage(error) };
  }
}

export async function getLessons(topicId?: number) {
  try {
    const url = topicId
      ? `/api/admin/topics/${topicId}/lessons`
      : "/api/admin/lessons";
    const response = await axiosInstance.get<APIResponse[]>(url);
    return { data: response.data.map(transformAPIResponse) };
  } catch (error) {
    return { error: getErrorMessage(error) };
  }
}

export async function updateLessonOrder(id: number, order: number) {
  try {
    const response = await axiosInstance.patch<APIResponse>(
      `/api/admin/lessons/${id}/order`,
      { order }
    );
    revalidatePath("/admin/units/[id]", "page");
    return { data: transformAPIResponse(response.data) };
  } catch (error) {
    return { error: getErrorMessage(error) };
  }
}

export async function toggleLessonPublished(id: number) {
  try {
    const response = await axiosInstance.patch<APIResponse>(
      `/api/admin/lessons/${id}/toggle-published`
    );
    revalidatePath("/admin/units/[id]", "page");
    revalidatePath("/admin/lessons/[id]", "page");
    return { data: transformAPIResponse(response.data) };
  } catch (error) {
    return { error: getErrorMessage(error) };
  }
}
