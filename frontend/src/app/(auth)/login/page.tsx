"use client";

import Link from "next/link";
import { useState } from "react";
import { useRouter } from "next/navigation";
import { getGoogleAuthUrl, login } from "@/app/_actions/auth-actions";

export default function LoginPage() {
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  const router = useRouter();

  const handleEmailLogin = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setError("");
    setLoading(true);

    try {
      const result = await login(new FormData(e.currentTarget));
      if (result?.error) {
        setError(result.error);
      } else {
        // Use client-side navigation after cookies are set
        router.refresh(); // Refresh to update auth state

        // Add post_login parameter to indicate this is a post-login redirect
        const redirectUrl = new URL(result.redirect, window.location.origin);
        redirectUrl.searchParams.set("post_login", "true");

        console.log(`Login successful, redirecting to: ${result.redirect}`);
        router.push(redirectUrl.pathname + redirectUrl.search);
      }
    } catch {
      setError("Failed to login");
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
          <span className="px-2 text-gray-500 bg-white">or</span>
        </div>
      </div>

      {/* Email Login Form */}
      <form onSubmit={handleEmailLogin} className="space-y-4">
        {error && (
          <div className="px-4 py-3 text-sm text-red-600 border border-red-200 bg-red-50 rounded-xl">
            {error}
          </div>
        )}

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
              spellCheck={false}
              suppressHydrationWarning
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
              autoComplete="current-password"
              required
              className="duo-input"
              placeholder="••••••••"
              spellCheck={false}
              suppressHydrationWarning
            />
          </div>
        </div>

        <div>
          <button
            type="submit"
            disabled={loading}
            className="w-full duo-button"
          >
            {loading ? "Logging in..." : "Start Learning!"}
          </button>
        </div>
      </form>

      <div className="space-y-4 text-center">
        <p className="text-sm text-gray-600">
          {"Don't have an account? "}
          <Link
            href="/register"
            className="font-bold text-[var(--duo-blue)] hover:text-[var(--duo-blue-hover)]"
          >
            Sign up for free
          </Link>
        </p>

        <Link
          href="/forgot-password"
          className="block text-sm font-bold text-[var(--duo-blue)] hover:text-[var(--duo-blue-hover)]"
        >
          Forgot your password?
        </Link>
      </div>
    </div>
  );
}
