'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import {
  BarChart3,
  Building2,
  
  Home,
  Settings,
  Shield,
  Users,
  Activity,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { ScrollArea } from '@/components/ui/scroll-area';

const routes = [
  {
    label: 'System Overview',
    icon: Home,
    href: '/super',
    color: 'text-sky-500',
  },
  {
    label: 'Tenant Management',
    icon: Building2,
    href: '/super/tenants',
    color: 'text-violet-500',
  },
  {
    label: 'System Analytics',
    icon: BarChart3,
    href: '/super/analytics',
    color: 'text-green-500',
  },
  {
    label: 'Global Users',
    icon: Users,
    href: '/super/users',
    color: 'text-emerald-500',
  },
  {
    label: 'System Health',
    icon: Activity,
    href: '/super/health',
    color: 'text-orange-500',
  },
  {
    label: 'Configuration',
    icon: Settings,
    href: '/super/settings',
    color: 'text-gray-700',
  },
];

export default function SuperAdminSidebar() {
  const pathname = usePathname();

  return (
    <div className="space-y-4 py-4 flex flex-col h-full bg-slate-900">
      <div className="px-3 py-2">
        <div className="flex items-center gap-2 mb-4 px-4">
          <Shield className="h-6 w-6 text-blue-400" />
          <h2 className="text-lg font-semibold tracking-tight text-white">
            Super Admin
          </h2>
        </div>
        <div className="space-y-1">
          <ScrollArea className="h-[calc(100vh-10rem)]">
            {routes.map((route) => (
              <Link
                key={route.href}
                href={route.href}
                className={cn(
                  'flex items-center w-full p-3 rounded-lg text-sm font-medium hover:text-white hover:bg-slate-800 transition',
                  pathname === route.href
                    ? 'text-white bg-slate-800'
                    : 'text-slate-300'
                )}
              >
                <route.icon className={cn('h-5 w-5 mr-3', route.color)} />
                {route.label}
              </Link>
            ))}
          </ScrollArea>
        </div>
      </div>
    </div>
  );
}
