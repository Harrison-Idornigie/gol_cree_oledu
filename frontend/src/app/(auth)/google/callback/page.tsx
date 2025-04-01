'use client';

import { useEffect } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { handleGoogleCallback } from '@/app/_actions/auth-actions';
import { toast } from 'sonner';

// Define user type
type GoogleUser = {
  id: number;
  name: string;
  email: string;
  email_verified_at: string | null;
  avatar_url: string | null;
  avatar: string;
  points: number;
  role: string;
  created_at: string;
  updated_at: string;
};

type GoogleResponse = {
  success: boolean;
  data: {
    token: string;
    user: GoogleUser;
    redirect_url: string;
  };
  message: string;
};

export default function GoogleCallbackPage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const code = searchParams.get('code');

  useEffect(() => {
    async function handleCallback() {
      try {
        if (!code) {
          toast.error('No authorization code present');
          router.push('/login');
          return;
        }

        // Handle the response with proper type conversion
        const rawResponse = await handleGoogleCallback(code);
        const response = JSON.parse(typeof rawResponse === 'string' ? rawResponse : JSON.stringify(rawResponse)) as unknown as GoogleResponse;
        console.log('Response:', response);

        if (response.success) {
          // Store the token
          localStorage.setItem('token', response.data.token);
          // Redirect to root path
          window.location.href = '/';
        } else {
          toast.error(response.message || 'Authentication failed');
          router.push('/login');
        }

      } catch (error) {
        if (error instanceof Error) {
          toast.error(error.message);
        } else {
          toast.error('Failed to authenticate with Google');
        }
        router.push('/login');
      }
    }

    handleCallback();
  }, [code, router]);

  return (
    <div className="flex items-center justify-center min-h-screen">
      <div className="text-lg">Processing Google login...</div>
    </div>
  );
}