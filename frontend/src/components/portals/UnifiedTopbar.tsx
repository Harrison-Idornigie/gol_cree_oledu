'use client';

import { useEffect, useState } from 'react';
import { Menu, User, LogOut, Settings, Shield } from 'lucide-react';
import { useRouter } from 'next/navigation';
import { Button } from '@/components/ui/button';
import { logout } from '@/app/_actions/auth-actions';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from '@/components/ui/sheet';
import { useAuth, useTenant, useTenantAccess } from '@/app/providers/auth-provider';
import UnifiedSidebar from './UnifiedSidebar';

interface UnifiedTopbarProps {
  onSidebarToggle?: () => void;
  showMobileSidebar?: boolean;
  membership: string;
}

export default function UnifiedTopbar({ 
  onSidebarToggle, 
  showMobileSidebar = false,
  membership 
}: UnifiedTopbarProps) {
  const router = useRouter();
  const { user } = useAuth();
  const { currentTenant, tenantSlug } = useTenant();
  const { canAccessMembership } = useTenantAccess();

  const handleLogout = async () => {
    try {
      await logout();
      router.push('/login');
    } catch (error) {
      console.error('Logout failed:', error);
      // Force redirect even if logout fails
      window.location.href = '/login';
    }
  };

  const getMembershipLabel = () => {
    switch (membership) {
      case 'admin':
        return 'Administrator';
      case 'team':
        return 'Team Member';
      case 'student':
        return 'Student';
      default:
        return 'Member';
    }
  };

  return (
    <header className="border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
      <div className="flex h-14 items-center px-4">
        {/* Mobile Sidebar Toggle (for student layout) */}
        {showMobileSidebar && (
          <Sheet>
            <SheetTrigger asChild>
              <Button variant="outline" size="icon" className="md:hidden">
                <Menu className="h-4 w-4" />
              </Button>
            </SheetTrigger>
            <SheetContent side="left" className="w-64">
              <SheetHeader>
                <SheetTitle>Navigation</SheetTitle>
              </SheetHeader>
              <UnifiedSidebar membership={membership} />
            </SheetContent>
          </Sheet>
        )}

        {/* Desktop Sidebar Toggle (for admin/team layout) */}
        {!showMobileSidebar && onSidebarToggle && (
          <Button
            variant="outline"
            size="icon"
            onClick={onSidebarToggle}
            className="hidden md:flex"
          >
            <Menu className="h-4 w-4" />
          </Button>
        )}

        {/* Tenant Info */}
        <div className="flex items-center gap-2 ml-4">
          <div className="flex flex-col">
            <span className="text-sm font-medium">
              {currentTenant?.name || 'Organization'}
            </span>
            <span className="text-xs text-muted-foreground">
              {getMembershipLabel()}
            </span>
          </div>
        </div>

        {/* Spacer */}
        <div className="flex-1" />

        {/* User Menu */}
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" className="relative h-8 w-8 rounded-full">
              <User className="h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent className="w-56" align="end" forceMount>
            <div className="flex items-center justify-start gap-2 p-2">
              <div className="flex flex-col space-y-1 leading-none">
                <p className="font-medium">{user?.name}</p>
                <p className="text-xs text-muted-foreground">{user?.email}</p>
              </div>
            </div>
            <DropdownMenuSeparator />
            
            {/* Profile Settings */}
            <DropdownMenuItem
              onClick={() => router.push(`/${tenantSlug}/${membership}/profile`)}
            >
              <User className="mr-2 h-4 w-4" />
              Profile
            </DropdownMenuItem>
            
            <DropdownMenuItem
              onClick={() => router.push(`/${tenantSlug}/${membership}/settings`)}
            >
              <Settings className="mr-2 h-4 w-4" />
              Settings
            </DropdownMenuItem>

            {/* Admin Access for elevated users */}
            {canAccessMembership('admin') && membership !== 'admin' && (
              <>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  onClick={() => router.push(`/${tenantSlug}/admin/dashboard`)}
                >
                  <Shield className="mr-2 h-4 w-4" />
                  Admin Portal
                </DropdownMenuItem>
              </>
            )}

            <DropdownMenuSeparator />
            <DropdownMenuItem onClick={handleLogout}>
              <LogOut className="mr-2 h-4 w-4" />
              Log out
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </header>
  );
}
