'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import {
  Book,
  Flag,
  GraduationCap,
  Home,
  Layers,
  Settings,
  Users,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { ScrollArea } from '@/components/ui/scroll-area';

const routes = [
  {
    label: 'Overview',
    icon: Home,
    href: '/admin',
    color: 'text-sky-500',
  },
  {
    label: 'Learning Paths',
    icon: Flag,
    href: '/admin/learning-paths',
    color: 'text-violet-500',
  },
  {
    label: 'Units',
    icon: Layers,
    href: '/admin/units',
    color: 'text-pink-700',
  },
  {
    label: 'Lessons',
    icon: Book,
    href: '/admin/lessons',
    color: 'text-orange-700',
  },
  {
    label: 'Users',
    icon: Users,
    href: '/admin/users',
    color: 'text-emerald-500',
  },
  {
    label: 'Learning Stats',
    icon: GraduationCap,
    href: '/admin/stats',
    color: 'text-green-700',
  },
  {
    label: 'Settings',
    icon: Settings,
    href: '/admin/settings',
    color: 'text-gray-700',
  },
];

export default function AdminSidebar() {
  const pathname = usePathname();

  return (
    <div className="space-y-4 py-4 flex flex-col h-full bg-slate-50">
      <div className="px-3 py-2">
        <h2 className="mb-2 px-4 text-lg font-semibold tracking-tight">
          Admin Dashboard
        </h2>
        <div className="space-y-1">
          <ScrollArea className="h-[calc(100vh-10rem)]">
            {routes.map((route) => (
              <Link
                key={route.href}
                href={route.href}
                className={cn(
                  'flex items-center w-full p-3 rounded-lg text-sm font-medium hover:text-primary hover:bg-primary/10 transition',
                  pathname === route.href
                    ? 'text-primary bg-primary/10'
                    : 'text-muted-foreground'
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