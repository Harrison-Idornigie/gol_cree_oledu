'use client';

import { useState } from 'react';
import Link from 'next/link';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { resendVerificationEmail } from '@/app/_actions/auth-actions';
import { toast } from 'sonner';

export default function VerifyEmailPage() {
  const [loading, setLoading] = useState(false);

  const handleResend = async () => {
    setLoading(true);
    try {
      const result = await resendVerificationEmail();
      if (result.success) {
        toast.success('Success', {
          description: 'Verification email has been sent',
        });
      } else {
        toast.error('Error', {
          description: result.error || 'Failed to send verification email',
        });
      }
    } catch (error) {
      toast.error('Error', {
        description: 'Failed to send verification email',
      });
    } finally {
      setLoading(false);
    }
  };

  return (
    <Card className="p-6 space-y-4">
      <div className="space-y-2 text-center">
        <h1 className="text-2xl font-bold">Verify Your Email</h1>
        <p className="text-muted-foreground">
          We've sent a verification link to your email address. Please check your inbox and click the link to verify your account.
        </p>
      </div>

      <div className="space-y-2 text-center">
        <p className="text-sm text-muted-foreground">
          Didn't receive the email? Check your spam folder or click below to resend.
        </p>
        <Button 
          onClick={handleResend} 
          disabled={loading}
          className="w-full"
        >
          {loading ? 'Sending...' : 'Resend Verification Email'}
        </Button>
      </div>

      <div className="text-center">
        <Link 
          href="/login" 
          className="text-sm font-medium text-primary hover:underline"
        >
          Return to Login
        </Link>
      </div>
    </Card>
  );
}
