'use client';

import { useEffect, useState } from 'react';
import { Menu, User } from 'lucide-react';
import { useRouter } from 'next/navigation';
import { Button } from '@/components/ui/button';
import { logout } from '@/app/_actions/auth-actions';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

interface AdminTopbarProps {
  onSidebarToggle?: () => void;
  portalType?: 'super-admin' | 'tenant-admin' | 'team' | 'default';
}

export default function AdminTopbar({ onSidebarToggle }: AdminTopbarProps) {
  const router = useRouter();
  const [isMounted, setIsMounted] = useState(false);

  useEffect(() => {
    setIsMounted(true);
  }, []);

  const handleLogout = async () => {
    const result = await logout();
    if (!result.error) {
      router.push('/login');
    }
  };

  if (!isMounted) {
    return null;
  }

  return (
    <div className="h-[64px] border-b bg-white">
      <div className="flex h-full items-center justify-between px-4">
        <div className="flex items-center gap-x-4">
          <Button
            variant="ghost"
            size="icon"
            className="md:hidden"
            onClick={onSidebarToggle}
          >
            <Menu className="h-5 w-5" />
            <span className="sr-only">Toggle sidebar</span>
          </Button>
          <div className="hidden md:block">
            <span className="text-xl font-bold">Admin Dashboard</span>
          </div>
        </div>
        <div className="flex items-center gap-x-4">
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button
                variant="ghost"
                className="flex items-center gap-2"
                size="sm"
              >
                <User className="h-5 w-5" />
                <span className="hidden md:inline">Account</span>
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-[200px]">
              <DropdownMenuItem onClick={() => router.push('/admin/profile')}>
                Profile Settings
              </DropdownMenuItem>
              <DropdownMenuItem onClick={() => router.push('/admin/users')}>
                User Management
              </DropdownMenuItem>
              <DropdownMenuItem onClick={() => router.push('/admin/settings')}>
                System Settings
              </DropdownMenuItem>
              <DropdownMenuItem onClick={handleLogout} className="text-red-600">
                Logout
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>
    </div>
  );
}