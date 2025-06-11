'use client';

import { ReactNode } from 'react';
import { useTenantAccess } from '@/app/providers/tenant-provider';
import { useAuth } from '@/app/providers/auth-provider';
import { AlertTriangle, Lock } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import Link from 'next/link';

interface MembershipGuardProps {
  children: ReactNode;
  allowedMemberships: string[];
  fallback?: ReactNode;
  requireExactMembership?: boolean;
}

export default function MembershipGuard({ 
  children, 
  allowedMemberships, 
  fallback,
  requireExactMembership = false 
}: MembershipGuardProps) {
  const { user } = useAuth();
  const { hasAccess, canAccessMembership } = useTenantAccess();

  // Check if user is authenticated
  if (!user) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <Card className="w-full max-w-md">
          <CardHeader className="text-center">
            <Lock className="h-12 w-12 mx-auto text-muted-foreground mb-4" />
            <CardTitle>Authentication Required</CardTitle>
            <CardDescription>
              Please log in to access this page.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <Button asChild className="w-full">
              <Link href="/login">Sign In</Link>
            </Button>
          </CardContent>
        </Card>
      </div>
    );
  }

  // Check tenant access
  if (!hasAccess()) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <Card className="w-full max-w-md">
          <CardHeader className="text-center">
            <AlertTriangle className="h-12 w-12 mx-auto text-red-500 mb-4" />
            <CardTitle>Access Denied</CardTitle>
            <CardDescription>
              You don&apos;t have permission to access this tenant.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <Button asChild variant="outline" className="w-full">
              <Link href="/dashboard">Go to Dashboard</Link>
            </Button>
          </CardContent>
        </Card>
      </div>
    );
  }

  // Check membership-based access
  const hasMembershipAccess = requireExactMembership 
    ? allowedMemberships.includes(user.membership)
    : allowedMemberships.some(membership => canAccessMembership(membership));

  if (!hasMembershipAccess) {
    if (fallback) {
      return <>{fallback}</>;
    }

    return (
      <div className="min-h-screen flex items-center justify-center">
        <Card className="w-full max-w-md">
          <CardHeader className="text-center">
            <Lock className="h-12 w-12 mx-auto text-orange-500 mb-4" />
            <CardTitle>Insufficient Permissions</CardTitle>
            <CardDescription>
              You need {allowedMemberships.join(' or ')} membership to access this page.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <Button asChild variant="outline" className="w-full">
              <Link href="/dashboard">Go to Dashboard</Link>
            </Button>
          </CardContent>
        </Card>
      </div>
    );
  }

  return <>{children}</>;
}

// Convenience components for specific memberships
export function AdminOnly({ children, fallback }: { children: ReactNode; fallback?: ReactNode }) {
  return (
    <MembershipGuard allowedMemberships={['admin']} fallback={fallback}>
      {children}
    </MembershipGuard>
  );
}

export function TeamOnly({ children, fallback }: { children: ReactNode; fallback?: ReactNode }) {
  return (
    <MembershipGuard allowedMemberships={['team']} fallback={fallback}>
      {children}
    </MembershipGuard>
  );
}

export function StudentOnly({ children, fallback }: { children: ReactNode; fallback?: ReactNode }) {
  return (
    <MembershipGuard allowedMemberships={['student']} fallback={fallback}>
      {children}
    </MembershipGuard>
  );
}

export function AdminOrTeam({ children, fallback }: { children: ReactNode; fallback?: ReactNode }) {
  return (
    <MembershipGuard allowedMemberships={['admin', 'team']} fallback={fallback}>
      {children}
    </MembershipGuard>
  );
}

// Hook for conditional rendering
export function useMembershipAccess() {
  const { user } = useAuth();
  const { canAccessMembership } = useTenantAccess();

  return {
    isAdmin: () => canAccessMembership('admin'),
    isTeam: () => canAccessMembership('team'),
    isStudent: () => canAccessMembership('student'),
    hasMembership: (membership: string) => canAccessMembership(membership),
    hasAnyMembership: (memberships: string[]) => memberships.some(membership => canAccessMembership(membership)),
    userMembership: user?.membership || null,
  };
}
