'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { acceptInvite } from '@/app/_actions/admin/user-management-actions';
import { toast } from 'sonner';

interface AcceptInvitePageProps {
  params: {
    token: string;
  };
}

export default function AcceptInvitePage({ params }: AcceptInvitePageProps) {
  const router = useRouter();
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const handleAcceptInvite = async () => {
      try {
        const result = await acceptInvite(params.token);
        if (result.error) {
          setError(result.error);
          toast.error('Error', {
            description: result.error,
          });
        } else {
          toast.success('Success', {
            description: 'Admin invite accepted successfully',
          });
          router.push('/admin');
        }
      } catch {
        const errorMessage = 'Failed to accept invite. Please try again or contact support.';
        setError(errorMessage);
        toast.error('Error', {
          description: errorMessage,
        });
      } finally {
        setLoading(false);
      }
    };

    handleAcceptInvite();
  }, [params.token, router]);

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-slate-50">
        <Card className="w-[400px] p-6 space-y-4">
          <div className="space-y-2 text-center">
            <h1 className="text-2xl font-bold tracking-tight">Accepting Invite</h1>
            <p className="text-muted-foreground">Please wait while we process your invite...</p>
          </div>
          <div className="flex justify-center">
            <div className="animate-spin h-8 w-8 border-4 border-primary border-t-transparent rounded-full" />
          </div>
        </Card>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-slate-50">
        <Card className="w-[400px] p-6 space-y-4">
          <div className="space-y-2 text-center">
            <h1 className="text-2xl font-bold tracking-tight text-red-600">Error</h1>
            <p className="text-muted-foreground">{error}</p>
            <p className="text-sm text-muted-foreground">
              If you continue to experience issues, please contact the administrator.
            </p>
          </div>
          <div className="flex justify-center space-x-2">
            <Button variant="outline" onClick={() => window.location.reload()}>
              Try Again
            </Button>
            <Button onClick={() => router.push('/login')}>
              Return to Login
            </Button>
          </div>
        </Card>
      </div>
    );
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-slate-50">
      <Card className="w-[400px] p-6 space-y-4">
        <div className="space-y-2 text-center">
          <h1 className="text-2xl font-bold tracking-tight">Invite Accepted</h1>
          <p className="text-muted-foreground">
            Your admin account has been activated. Redirecting you to the dashboard...
          </p>
        </div>
        <div className="flex justify-center">
          <div className="animate-spin h-8 w-8 border-4 border-primary border-t-transparent rounded-full" />
        </div>
      </Card>
    </div>
  );
}