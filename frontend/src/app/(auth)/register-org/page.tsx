"use client";

import Link from "next/link";
import { useState, useCallback, useEffect } from "react";
import { useRouter } from "next/navigation";
import { registerTenantAdmin, validateTenantSlug } from "@/app/_actions/auth-actions";

export default function RegisterOrganizationPage() {
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);
  const [slugAvailable, setSlugAvailable] = useState<boolean | null>(null);
  const [checkingSlug, setCheckingSlug] = useState(false);
  const router = useRouter();

  const handleRegister = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setError("");
    setLoading(true);

    try {
      const formData = new FormData(e.currentTarget);
      const data = {
        organizationName: formData.get("organizationName") as string,
        organizationSlug: formData.get("organizationSlug") as string,
        organizationDescription: formData.get("organizationDescription") as string,
        adminName: formData.get("adminName") as string,
        adminEmail: formData.get("adminEmail") as string,
        password: formData.get("password") as string,
        passwordConfirmation: formData.get("passwordConfirmation") as string,
      };

      console.log('🚀 Starting tenant registration with data:', {
        ...data,
        password: '[REDACTED]',
        passwordConfirmation: '[REDACTED]'
      });

      const result = await registerTenantAdmin(data);

      console.log('📡 Registration result:', {
        success: result?.success,
        error: result?.error,
        redirect: result?.redirect,
        hasData: !!result?.data
      });

      if (result?.error) {
        console.error('❌ Registration failed:', result.error);
        setError(result.error);
      } else if (result?.success) {
        console.log('✅ Registration successful, preparing redirect...');
        router.refresh();

        const redirectPath = result.redirect || "/admin";
        const redirectUrl = new URL(redirectPath, window.location.origin);
        redirectUrl.searchParams.set("post_login", "true");

        console.log(`🔄 Redirecting to: ${redirectPath}`);
        router.push(redirectUrl.pathname + redirectUrl.search);
      } else {
        console.error('⚠️ Unexpected result structure:', result);
        setError('Registration completed but received unexpected response');
      }
    } catch {
      setError("Failed to create organization");
    } finally {
      setLoading(false);
    }
  };

  // Debounced slug validation - only check availability, let backend handle format validation
  const validateSlugWithDelay = useCallback(async (slug: string) => {
    if (slug.length < 3) {
      setSlugAvailable(null);
      return;
    }

    setCheckingSlug(true);
    try {
      const result = await validateTenantSlug(slug);
      setSlugAvailable(result.available);
    } catch {
      setSlugAvailable(false);
    } finally {
      setCheckingSlug(false);
    }
  }, []);

  const handleSlugChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    // Basic input sanitization only - let backend handle validation
    const value = e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, '');
    e.target.value = value;

    // Reset state and trigger validation with delay
    setSlugAvailable(null);
    setCheckingSlug(false);

    // Simple debounce using setTimeout
    const timeoutId = setTimeout(() => {
      validateSlugWithDelay(value);
    }, 500);

    // Store timeout ID for cleanup
    e.target.dataset.timeoutId = timeoutId.toString();

    // Clear previous timeout
    const previousTimeoutId = e.target.dataset.previousTimeoutId;
    if (previousTimeoutId) {
      clearTimeout(parseInt(previousTimeoutId));
    }
    e.target.dataset.previousTimeoutId = timeoutId.toString();
  };

  const generateSlug = (orgName: string) => {
    return orgName
      .toLowerCase()
      .replace(/[^a-z0-9\s-]/g, '')
      .replace(/\s+/g, '-')
      .replace(/-+/g, '-')
      .replace(/^-|-$/g, '')
      .substring(0, 50);
  };

  return (
    <div className="space-y-6">
      <div className="text-center mb-6">
        <h1 className="text-2xl font-bold text-gray-900">Create Your Organization</h1>
        <p className="text-gray-600 mt-2">
          Set up your language learning platform for your school or organization
        </p>
      </div>

      <form onSubmit={handleRegister} className="space-y-6">
        {error && (
          <div className="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-xl text-sm">
            {error}
          </div>
        )}

        {/* Organization Information */}
        <div className="space-y-4">
          <h2 className="text-lg font-semibold text-gray-900">Organization Information</h2>
          
          <div>
            <label htmlFor="organizationName" className="block text-sm font-bold text-gray-700">
              Organization Name *
            </label>
            <div className="mt-1">
              <input
                id="organizationName"
                name="organizationName"
                type="text"
                required
                className="duo-input"
                placeholder="Springfield School District"
                onChange={(e) => {
                  const slugField = document.getElementById('organizationSlug') as HTMLInputElement;
                  if (slugField && !slugField.value) {
                    slugField.value = generateSlug(e.target.value);
                    handleSlugChange({ target: slugField } as React.ChangeEvent<HTMLInputElement>);
                  }
                }}
              />
            </div>
          </div>

          <div>
            <label htmlFor="organizationSlug" className="block text-sm font-bold text-gray-700">
              Organization URL Slug *
            </label>
            <div className="mt-1">
              <div className="flex">
                <span className="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">
                  yoursite.com/
                </span>
                <input
                  id="organizationSlug"
                  name="organizationSlug"
                  type="text"
                  required
                  className="duo-input rounded-l-none"
                  placeholder="springfield-schools"
                  onChange={handleSlugChange}
                />
              </div>
              <p className="text-xs text-gray-500 mt-1">
                Only lowercase letters, numbers, and hyphens. This will be your organization's URL.
              </p>
              {checkingSlug && (
                <p className="text-xs text-blue-500 mt-1">Checking availability...</p>
              )}
              {!checkingSlug && slugAvailable === false && (
                <p className="text-xs text-red-500 mt-1">Invalid format or already taken</p>
              )}
              {!checkingSlug && slugAvailable === true && (
                <p className="text-xs text-green-500 mt-1">✓ Available!</p>
              )}
            </div>
          </div>

          <div>
            <label htmlFor="organizationDescription" className="block text-sm font-bold text-gray-700">
              Description (Optional)
            </label>
            <div className="mt-1">
              <textarea
                id="organizationDescription"
                name="organizationDescription"
                rows={3}
                className="duo-input"
                placeholder="Brief description of your organization..."
              />
            </div>
          </div>
        </div>

        {/* Administrator Information */}
        <div className="space-y-4">
          <h2 className="text-lg font-semibold text-gray-900">Administrator Account</h2>
          
          <div>
            <label htmlFor="adminName" className="block text-sm font-bold text-gray-700">
              Your Full Name *
            </label>
            <div className="mt-1">
              <input
                id="adminName"
                name="adminName"
                type="text"
                required
                className="duo-input"
                placeholder="John Smith"
              />
            </div>
          </div>

          <div>
            <label htmlFor="adminEmail" className="block text-sm font-bold text-gray-700">
              Your Email Address *
            </label>
            <div className="mt-1">
              <input
                id="adminEmail"
                name="adminEmail"
                type="email"
                required
                className="duo-input"
                placeholder="admin@springfield-schools.edu"
              />
            </div>
          </div>

          <div>
            <label htmlFor="password" className="block text-sm font-bold text-gray-700">
              Password *
            </label>
            <div className="mt-1">
              <input
                id="password"
                name="password"
                type="password"
                required
                className="duo-input"
                placeholder="••••••••"
              />
            </div>
          </div>

          <div>
            <label htmlFor="passwordConfirmation" className="block text-sm font-bold text-gray-700">
              Confirm Password *
            </label>
            <div className="mt-1">
              <input
                id="passwordConfirmation"
                name="passwordConfirmation"
                type="password"
                required
                className="duo-input"
                placeholder="••••••••"
              />
            </div>
          </div>
        </div>

        <div>
          <button
            type="submit"
            disabled={loading || slugAvailable === false}
            className="w-full duo-button"
          >
            {loading ? "Creating Organization..." : "Create Organization"}
          </button>
        </div>
      </form>

      <div className="text-center">
        <p className="text-sm text-gray-600">
          Already have an organization account?{" "}
          <Link
            href="/login"
            className="font-bold text-[var(--duo-blue)] hover:text-[var(--duo-blue-hover)]"
          >
            Sign in
          </Link>
        </p>
        <p className="text-sm text-gray-600 mt-2">
          Looking to join an existing organization?{" "}
          <Link
            href="/register"
            className="font-bold text-[var(--duo-blue)] hover:text-[var(--duo-blue-hover)]"
          >
            Student Registration
          </Link>
        </p>
      </div>
    </div>
  );
}
