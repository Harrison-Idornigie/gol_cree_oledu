'use client';

import Link from 'next/link';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';

export default function NotFound() {
  return (
    <div className="min-h-screen flex items-center justify-center bg-slate-50">
      <Card className="w-[400px] p-6 space-y-4">
        <div className="space-y-2 text-center">
          <h1 className="text-6xl font-bold tracking-tight text-slate-900">404</h1>
          <h2 className="text-2xl font-bold tracking-tight text-slate-900">Page Not Found</h2>
          <p className="text-muted-foreground">
            The page you're looking for doesn't exist or the tenant could not be found.
          </p>
        </div>
        
        <div className="flex justify-center space-x-2">
          <Button variant="outline" onClick={() => window.history.back()}>
            Go Back
          </Button>
          <Button asChild>
            <Link href="/login">
              Return to Login
            </Link>
          </Button>
        </div>
      </Card>
    </div>
  );
}
