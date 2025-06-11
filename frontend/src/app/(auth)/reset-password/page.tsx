'use client';

import { useState } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import Link from 'next/link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { resetPassword } from '@/app/_actions/auth-actions';
import { toast } from 'sonner';

export default function ResetPasswordPage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const token = searchParams.get('token');
  const email = searchParams.get('email');

  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!token || !email) {
      setError('Invalid reset link. Please request a new password reset link.');
      return;
    }

    if (password.length < 8) {
      setError('Password must be at least 8 characters long');
      return;
    }

    if (password !== passwordConfirmation) {
      setError('Passwords do not match');
      return;
    }

    setLoading(true);
    setError('');
    
    try {
      const result = await resetPassword({
        token,
        email,
        password,
        password_confirmation: passwordConfirmation
      });

      if (result.success) {
        toast.success('Success', {
          description: 'Your password has been reset successfully',
        });
        router.push('/login');
      } else {
        setError(result.error || 'Failed to reset password');
      }
    } catch (error) {
      setError('An unexpected error occurred. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  if (!token || !email) {
    return (
      <div className="space-y-6">
        <div className="text-center">
          <h2 className="text-2xl font-bold">Invalid Reset Link</h2>
          <p className="mt-2 text-gray-600">
            The password reset link is invalid or has expired.
          </p>
        </div>
        
        <div className="text-center">
          <Link 
            href="/forgot-password" 
            className="font-bold text-[var(--duo-blue)] hover:text-[var(--duo-blue-hover)]"
          >
            Request a new password reset link
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="text-center">
        <h2 className="text-2xl font-bold">Reset Your Password</h2>
        <p className="mt-2 text-gray-600">
          Create a new password for your account
        </p>
      </div>
      
      <form onSubmit={handleSubmit} className="space-y-4">
        {error && (
          <div className="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-xl text-sm">
            {error}
          </div>
        )}

        <div>
          <label htmlFor="password" className="block text-sm font-bold text-gray-700">
            New Password
          </label>
          <div className="mt-1">
            <Input
              id="password"
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
              className="duo-input"
              placeholder="••••••••"
              spellCheck={false}
              autoComplete="new-password"
              suppressHydrationWarning
            />
          </div>
        </div>

        <div>
          <label htmlFor="password_confirmation" className="block text-sm font-bold text-gray-700">
            Confirm New Password
          </label>
          <div className="mt-1">
            <Input
              id="password_confirmation"
              type="password"
              value={passwordConfirmation}
              onChange={(e) => setPasswordConfirmation(e.target.value)}
              required
              className="duo-input"
              placeholder="••••••••"
              spellCheck={false}
              autoComplete="new-password"
              suppressHydrationWarning
            />
          </div>
        </div>

        <div>
          <Button
            type="submit"
            disabled={loading}
            className="w-full duo-button"
          >
            {loading ? 'Resetting Password...' : 'Reset Password'}
          </Button>
        </div>
      </form>

      <div className="text-center">
        <Link 
          href="/login" 
          className="font-bold text-[var(--duo-blue)] hover:text-[var(--duo-blue-hover)]"
        >
          Back to Login
        </Link>
      </div>
    </div>
  );
}
