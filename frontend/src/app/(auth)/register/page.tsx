"use client";

import Link from "next/link";
import { useState } from "react";
import { useRouter } from "next/navigation";
import { register, getGoogleAuthUrl } from "@/app/_actions/auth-actions";

export default function RegisterPage() {
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);
  const router = useRouter();

  const handleRegister = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setError("");
    setLoading(true);

    try {
      const result = await register(new FormData(e.currentTarget));
      if (result?.error) {
        setError(result.error);
      } else if (result.success) {
        // Use client-side navigation after cookies are set
        router.refresh(); // Refresh to update auth state

        // Add post_login parameter to indicate this is a post-login redirect
        const redirectPath = result.redirect || "/student";
        const redirectUrl = new URL(redirectPath, window.location.origin);
        redirectUrl.searchParams.set("post_login", "true");

        console.log(`Registration successful, redirecting to: ${redirectPath}`);
        router.push(redirectUrl.pathname + redirectUrl.search);
      }
    } catch {
      setError("Failed to create account");
    } finally {
      setLoading(false);
    }
  };

  const handleGoogleLogin = async () => {
    const url = await getGoogleAuthUrl();
    window.location.href = url;
  };

  return (
    <div className="space-y-6">
      {/* Google Sign In */}
      <button
        type="button"
        onClick={handleGoogleLogin}
        className="w-full bg-[#4285f4] text-white duo-button border-[#357abd] hover:bg-[#357abd] flex items-center justify-center gap-2"
      >
        <span className="material-icons-outlined">google</span>
        Continue with Google
      </button>

      {/* Divider */}
      <div className="relative">
        <div className="absolute inset-0 flex items-center">
          <div className="w-full border-t border-gray-300"></div>
        </div>
        <div className="relative flex justify-center text-sm">
          <span className="px-2 bg-white text-gray-500">or</span>
        </div>
      </div>

      {/* Registration Form */}
      <form onSubmit={handleRegister} className="space-y-4">
        {error && (
          <div className="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-xl text-sm">
            {error}
          </div>
        )}

        <div>
          <label
            htmlFor="name"
            className="block text-sm font-bold text-gray-700"
          >
            Full Name
          </label>
          <div className="mt-1">
            <input
              id="name"
              name="name"
              type="text"
              required
              className="duo-input"
              placeholder="Your name"
            />
          </div>
        </div>

        <div>
          <label
            htmlFor="email"
            className="block text-sm font-bold text-gray-700"
          >
            Email address
          </label>
          <div className="mt-1">
            <input
              id="email"
              name="email"
              type="email"
              autoComplete="email"
              required
              className="duo-input"
              placeholder="student@example.com"
            />
          </div>
        </div>

        <div>
          <label
            htmlFor="password"
            className="block text-sm font-bold text-gray-700"
          >
            Password
          </label>
          <div className="mt-1">
            <input
              id="password"
              name="password"
              type="password"
              autoComplete="new-password"
              required
              className="duo-input"
              placeholder="••••••••"
            />
          </div>
        </div>

        <div>
          <label
            htmlFor="password_confirmation"
            className="block text-sm font-bold text-gray-700"
          >
            Confirm Password
          </label>
          <div className="mt-1">
            <input
              id="password_confirmation"
              name="password_confirmation"
              type="password"
              autoComplete="new-password"
              required
              className="duo-input"
              placeholder="••••••••"
            />
          </div>
        </div>

        <div>
          <button
            type="submit"
            disabled={loading}
            className="w-full duo-button"
          >
            {loading ? "Creating Account..." : "Start Your Learning Journey"}
          </button>
        </div>
      </form>

      <div className="text-center space-y-2">
        <p className="text-sm text-gray-600">
          Already have an account?{" "}
          <Link
            href="/login"
            className="font-bold text-[var(--duo-blue)] hover:text-[var(--duo-blue-hover)]"
          >
            Sign in
          </Link>
        </p>
        <p className="text-sm text-gray-600">
          Want to create an organization?{" "}
          <Link
            href="/register-org"
            className="font-bold text-[var(--duo-blue)] hover:text-[var(--duo-blue-hover)]"
          >
            Organization Setup
          </Link>
        </p>
      </div>
    </div>
  );
}
