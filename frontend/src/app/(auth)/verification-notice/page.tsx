'use client';

import { useState } from 'react';
import Link from 'next/link';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { resendVerificationEmail } from '@/app/_actions/auth-actions';
import { toast } from 'sonner';

export default function VerificationNoticePage() {
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
    <div className="space-y-6">
      <div className="text-center">
        <h2 className="text-2xl font-bold">Verify Your Email</h2>
        <p className="mt-2 text-gray-600">
          You need to verify your email address before you can access this feature.
        </p>
      </div>
      
      <Card className="p-6 space-y-4">
        <div className="space-y-2">
          <p className="text-center">
            We've sent a verification link to your email address. Please check your inbox and click the link to verify your account.
          </p>
          <p className="text-sm text-center text-gray-600">
            Didn't receive the email? Check your spam folder or click below to resend.
          </p>
        </div>
        
        <div className="flex justify-center">
          <Button 
            onClick={handleResend} 
            disabled={loading}
          >
            {loading ? 'Sending...' : 'Resend Verification Email'}
          </Button>
        </div>
      </Card>

      <div className="text-center">
        <Link 
          href="/learn" 
          className="font-bold text-[var(--duo-blue)] hover:text-[var(--duo-blue-hover)]"
        >
          Return to Dashboard
        </Link>
      </div>
    </div>
  );
}
