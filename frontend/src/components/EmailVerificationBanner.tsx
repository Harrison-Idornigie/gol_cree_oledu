'use client';

import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { AlertCircle, CheckCircle2 } from 'lucide-react';
import { resendVerificationEmail } from '@/app/_actions/auth-actions';
import { toast } from 'sonner';
import { User, isEmailVerified } from '@/types/user';

interface EmailVerificationBannerProps {
  user: User | null;
}

export function EmailVerificationBanner({ user }: EmailVerificationBannerProps) {
  const [loading, setLoading] = useState(false);
  const [dismissed, setDismissed] = useState(false);

  // If user is null, email is verified, or banner is dismissed, don't show anything
  if (!user || isEmailVerified(user) || dismissed) {
    return null;
  }

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
    <Alert className="mb-4 border-amber-500 bg-amber-50">
      <AlertCircle className="w-4 h-4 text-amber-500" />
      <AlertTitle className="text-amber-800">Verify your email</AlertTitle>
      <AlertDescription className="text-amber-700">
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <span>Please verify your email address to access all features.</span>
          <div className="flex gap-2">
            <Button 
              variant="outline" 
              size="sm" 
              onClick={handleResend} 
              disabled={loading}
              className="border-amber-500 text-amber-700 hover:bg-amber-100 hover:text-amber-800"
            >
              {loading ? 'Sending...' : 'Resend Email'}
            </Button>
            <Button 
              variant="ghost" 
              size="sm" 
              onClick={() => setDismissed(true)}
              className="text-amber-700 hover:bg-amber-100 hover:text-amber-800"
            >
              Dismiss
            </Button>
          </div>
        </div>
      </AlertDescription>
    </Alert>
  );
}
