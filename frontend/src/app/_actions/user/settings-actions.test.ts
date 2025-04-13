import { describe, it, expect, beforeEach, vi } from "vitest";
import {
  getUserSettings,
  getAvailableInterfaceLanguages,
  updateInterfaceLanguage,
} from "./settings-actions";
import axiosInstance from "@/lib/axios";
import { revalidatePath } from "next/cache";

// Mock axios and next/cache
vi.mock("@/lib/axios", () => ({
  default: {
    get: vi.fn(),
    patch: vi.fn(),
  },
}));

vi.mock("next/cache", () => ({
  revalidatePath: vi.fn(),
}));

describe("settings-actions", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe("getUserSettings", () => {
    it("returns user settings when the API call is successful", async () => {
      // Mock successful API response
      (axiosInstance.get as any).mockResolvedValue({
        data: {
          data: {
            interface_language: "en",
          },
        },
      });

      const result = await getUserSettings();

      expect(axiosInstance.get).toHaveBeenCalledWith("/user/settings");
      expect(result).toEqual({
        success: true,
        data: {
          interface_language: "en",
        },
        error: null,
      });
    });

    it("returns an error when the API call fails", async () => {
      // Mock failed API response
      (axiosInstance.get as any).mockRejectedValue(new Error("API error"));

      const result = await getUserSettings();

      expect(axiosInstance.get).toHaveBeenCalledWith("/user/settings");
      expect(result).toEqual({
        success: false,
        data: null,
        error: "Failed to fetch user settings",
      });
    });
  });

  describe("getAvailableInterfaceLanguages", () => {
    it("returns available languages when the API call is successful", async () => {
      // Mock successful API response
      (axiosInstance.get as any).mockResolvedValue({
        data: {
          data: [
            { id: 1, code: "en", name: "English", native_name: "English" },
            { id: 2, code: "es", name: "Spanish", native_name: "Español" },
          ],
        },
      });

      const result = await getAvailableInterfaceLanguages();

      expect(axiosInstance.get).toHaveBeenCalledWith(
        "/user/settings/languages"
      );
      expect(result).toEqual({
        success: true,
        data: [
          { id: 1, code: "en", name: "English", native_name: "English" },
          { id: 2, code: "es", name: "Spanish", native_name: "Español" },
        ],
        error: null,
      });
    });

    it("returns an error when the API call fails", async () => {
      // Mock failed API response
      (axiosInstance.get as any).mockRejectedValue(new Error("API error"));

      const result = await getAvailableInterfaceLanguages();

      expect(axiosInstance.get).toHaveBeenCalledWith(
        "/user/settings/languages"
      );
      expect(result).toEqual({
        success: false,
        data: null,
        error: "Failed to fetch available interface languages",
      });
    });
  });

  describe("updateInterfaceLanguage", () => {
    it("updates the interface language when the API call is successful", async () => {
      // Mock successful API response
      (axiosInstance.patch as any).mockResolvedValue({
        data: {
          data: {
            success: true,
            interface_language: "es",
          },
        },
      });

      const result = await updateInterfaceLanguage("es");

      expect(axiosInstance.patch).toHaveBeenCalledWith(
        "/user/settings/interface-language",
        {
          language_code: "es",
        }
      );
      expect(revalidatePath).toHaveBeenCalledWith("/account");
      expect(revalidatePath).toHaveBeenCalledWith("/learn");
      expect(revalidatePath).toHaveBeenCalledWith("/profile");
      expect(result).toEqual({
        success: true,
        data: {
          success: true,
          interface_language: "es",
        },
        error: null,
      });
    });

    it("returns an error when the API call fails", async () => {
      // Mock failed API response
      (axiosInstance.patch as any).mockRejectedValue(new Error("API error"));

      const result = await updateInterfaceLanguage("es");

      expect(axiosInstance.patch).toHaveBeenCalledWith(
        "/user/settings/interface-language",
        {
          language_code: "es",
        }
      );
      expect(result).toEqual({
        success: false,
        data: null,
        error: "Failed to update interface language",
      });
    });
  });
});
