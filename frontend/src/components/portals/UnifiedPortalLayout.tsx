'use client';

import { useState } from 'react';
import { useTenant, useTenantAccess } from '@/app/providers/tenant-provider';
import { useAuth } from '@/app/providers/auth-provider';
import UnifiedSidebar from './UnifiedSidebar';
import UnifiedTopbar from './UnifiedTopbar';
import { Loader2 } from 'lucide-react';

interface UnifiedPortalLayoutProps {
  children: React.ReactNode;
}

export default function UnifiedPortalLayout({ children }: UnifiedPortalLayoutProps) {
  const [isSidebarCollapsed, setIsSidebarCollapsed] = useState(false);
  const { currentTenant, currentMembership, isLoading: tenantLoading } = useTenant();
  const { user } = useAuth();
  const { hasAccess } = useTenantAccess();

  // Show loading state while tenant context is being resolved
  if (tenantLoading || !user) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <Loader2 className="h-8 w-8 animate-spin" />
      </div>
    );
  }

  // Check if user has access to current tenant/membership
  if (!hasAccess()) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="text-center">
          <h1 className="text-2xl font-bold text-red-600">Access Denied</h1>
          <p className="text-muted-foreground">
            You don't have permission to access this area.
          </p>
        </div>
      </div>
    );
  }

  // Determine layout style based on membership
  const isStudentLayout = currentMembership === 'student';

  if (isStudentLayout) {
    // Student layout: Top navigation with mobile sidebar
    return (
      <div className="min-h-screen bg-background">
        <div className="flex flex-col">
          <div className="sticky top-0 z-10 shadow-sm bg-background">
            <UnifiedTopbar 
              onSidebarToggle={() => setIsSidebarCollapsed(!isSidebarCollapsed)}
              showMobileSidebar={true}
            />
          </div>
          <main className="flex justify-center w-full">
            <div className="w-full max-w-6xl p-4">
              {children}
            </div>
          </main>
        </div>
      </div>
    );
  }

  // Admin/Team layout: Fixed sidebar with topbar
  return (
    <div className="min-h-screen">
      <div className="fixed inset-y-0 z-50 hidden h-full w-72 flex-col md:flex">
        <UnifiedSidebar />
      </div>
      <div
        className={`fixed top-0 z-50 w-full flex-col md:pl-72 ${
          isSidebarCollapsed ? 'md:pl-20' : 'md:pl-72'
        }`}
      >
        <UnifiedTopbar 
          onSidebarToggle={() => setIsSidebarCollapsed(!isSidebarCollapsed)}
          showMobileSidebar={false}
        />
      </div>
      <div
        className={`pb-20 pt-16 min-h-screen ${
          isSidebarCollapsed ? 'md:pl-20' : 'md:pl-72'
        }`}
      >
        <div className="container py-6">{children}</div>
      </div>
    </div>
  );
}
