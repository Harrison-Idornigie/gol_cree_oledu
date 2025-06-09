'use client';

import { useState } from 'react';
import TenantAdminSidebar from '@/components/admin/TenantAdminSidebar';
import AdminTopbar from '@/components/admin/AdminTopbar';

interface TenantAdminLayoutProps {
  children: React.ReactNode;
}

export default function TenantAdminLayout({ children }: TenantAdminLayoutProps) {
  const [isSidebarCollapsed, setIsSidebarCollapsed] = useState(false);

  return (
    <div className="min-h-screen">
      <div className="fixed inset-y-0 z-50 hidden h-full w-72 flex-col md:flex">
        <TenantAdminSidebar />
      </div>
      <div
        className={`fixed top-0 z-50 w-full flex-col md:pl-72 ${
          isSidebarCollapsed ? 'md:pl-20' : 'md:pl-72'
        }`}
      >
        <AdminTopbar 
          onToggleSidebar={() => setIsSidebarCollapsed(!isSidebarCollapsed)}
          portalType="tenant-admin"
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
