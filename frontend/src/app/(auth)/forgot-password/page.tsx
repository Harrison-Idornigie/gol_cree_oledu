'use client';

import { useState } from 'react';
import Link from 'next/link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { forgotPassword } from '@/app/_actions/auth-actions';
import { toast } from 'sonner';

export default function ForgotPasswordPage() {
  const [email, setEmail] = useState('');
  const [loading, setLoading] = useState(false);
  const [emailSent, setEmailSent] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    
    try {
      const result = await forgotPassword(email);
      if (result.success) {
        setEmailSent(true);
        toast.success('Success', {
          description: 'Password reset link has been sent to your email',
        });
      } else {
        toast.error('Error', {
          description: result.error || 'Failed to send password reset link',
        });
      }
    } catch (error) {
      toast.error('Error', {
        description: 'Failed to send password reset link',
      });
    } finally {
      setLoading(false);
    }
  };

  if (emailSent) {
    return (
      <div className="space-y-6">
        <div className="text-center">
          <h2 className="text-2xl font-bold">Check Your Email</h2>
          <p className="mt-2 text-gray-600">
            We've sent a password reset link to {email}
          </p>
        </div>
        
        <div className="space-y-4">
          <p className="text-sm text-center text-gray-600">
            Please check your email inbox and click the link to reset your password.
            If you don't see the email, check your spam folder.
          </p>
          
          <div className="text-center">
            <Link 
              href="/login" 
              className="font-bold text-[var(--duo-blue)] hover:text-[var(--duo-blue-hover)]"
            >
              Return to Login
            </Link>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="text-center">
        <h2 className="text-2xl font-bold">Reset Your Password</h2>
        <p className="mt-2 text-gray-600">
          Enter your email address and we'll send you a link to reset your password
        </p>
      </div>
      
      <form onSubmit={handleSubmit} className="space-y-4">
        <div>
          <label htmlFor="email" className="block text-sm font-bold text-gray-700">
            Email address
          </label>
          <div className="mt-1">
            <Input
              id="email"
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
              className="duo-input"
              placeholder="student@example.com"
              spellCheck={false}
              autoComplete="email"
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
            {loading ? 'Sending...' : 'Send Reset Link'}
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
