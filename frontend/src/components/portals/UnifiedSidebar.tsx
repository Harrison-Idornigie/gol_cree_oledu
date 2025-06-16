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
  BarChart3,
  BookOpen,
  Globe,
  User,
  Shield,
  BookText,
  LucideIcon,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Tooltip, TooltipProvider } from '@/components/ui/tooltip';
import { useTenant, useTenantAccess } from '@/app/providers/auth-provider';

interface NavigationItem {
  label: string;
  icon: LucideIcon;
  path: string;
  color: string;
  memberships: string[];
  description?: string;
}

const navigationItems: NavigationItem[] = [
  // Dashboard
  {
    label: 'Dashboard',
    icon: Home,
    path: '/dashboard',
    color: 'text-sky-500',
    memberships: ['admin', 'team', 'student'],
  },
  
  // Content Management (Admin & Team)
  {
    label: 'Learning Paths',
    icon: Flag,
    path: '/learning-paths',
    color: 'text-violet-500',
    memberships: ['admin', 'team'],
  },
  {
    label: 'Units',
    icon: Layers,
    path: '/units',
    color: 'text-pink-700',
    memberships: ['admin', 'team'],
  },
  {
    label: 'Lessons',
    icon: Book,
    path: '/lessons',
    color: 'text-orange-700',
    memberships: ['admin', 'team'],
  },
  {
    label: 'Content Library',
    icon: BookOpen,
    path: '/content',
    color: 'text-blue-600',
    memberships: ['team'],
  },
  {
    label: 'Word Management',
    icon: BookText,
    path: '/words',
    color: 'text-indigo-600',
    memberships: ['admin', 'team'],
  },
  
  // Learning (Student)
  {
    label: 'Languages',
    icon: Globe,
    path: '/languages',
    color: 'text-green-600',
    memberships: ['student'],
  },
  {
    label: 'My Progress',
    icon: BarChart3,
    path: '/progress',
    color: 'text-purple-600',
    memberships: ['student'],
  },
  
  // User Management
  {
    label: 'Users',
    icon: Users,
    path: '/users',
    color: 'text-emerald-500',
    memberships: ['admin'],
  },
  {
    label: 'Team Members',
    icon: Users,
    path: '/team-members',
    color: 'text-emerald-500',
    memberships: ['team'],
  },
  
  // Analytics & Reports
  {
    label: 'Learning Stats',
    icon: GraduationCap,
    path: '/stats',
    color: 'text-green-700',
    memberships: ['admin'],
  },
  {
    label: 'Progress Reports',
    icon: BarChart3,
    path: '/reports',
    color: 'text-green-700',
    memberships: ['team'],
  },
  
  // Profile & Settings
  {
    label: 'Profile',
    icon: User,
    path: '/profile',
    color: 'text-gray-600',
    memberships: ['student'],
  },
  {
    label: 'Settings',
    icon: Settings,
    path: '/settings',
    color: 'text-gray-700',
    memberships: ['admin', 'team', 'student'],
  },
];

interface UnifiedSidebarProps {
  membership: string;
  isCollapsed?: boolean;
}

export default function UnifiedSidebar({ membership, isCollapsed = false }: UnifiedSidebarProps) {
  const pathname = usePathname();
  const { currentTenant, tenantSlug } = useTenant();
  const { canAccessMembership } = useTenantAccess();

  // Build tenant-aware URL
  const buildUrl = (path: string) => {
    if (!tenantSlug || !membership) return path;
    return `/${tenantSlug}/${membership}${path}`;
  };

  // Filter navigation items based on current membership
  const visibleItems = navigationItems.filter(item =>
    item.memberships.includes(membership || '')
  );

  // Get portal title based on membership
  const getPortalTitle = () => {
    switch (membership) {
      case 'admin':
        return 'Admin Portal';
      case 'team':
        return 'Team Portal';
      case 'student':
        return 'Learning Portal';
      default:
        return 'Portal';
    }
  };

  // Get portal icon based on membership
  const getPortalIcon = () => {
    switch (membership) {
      case 'admin':
        return Shield;
      case 'team':
        return Users;
      case 'student':
        return GraduationCap;
      default:
        return Home;
    }
  };

  const PortalIcon = getPortalIcon();

  return (
    <TooltipProvider>
      <div className="space-y-4 py-4 flex flex-col h-full bg-slate-50">
        <div className={cn("px-3 py-2", isCollapsed && "px-2")}>
          {/* Portal Header */}
          <div className={cn(
            "flex items-center gap-2 mb-4",
            isCollapsed ? "justify-center px-2" : "px-4"
          )}>
            <PortalIcon className="h-6 w-6 text-primary flex-shrink-0" />
            {!isCollapsed && (
              <div>
                <h2 className="text-lg font-semibold tracking-tight">
                  {getPortalTitle()}
                </h2>
                {currentTenant && (
                  <p className="text-xs text-muted-foreground">
                    {currentTenant.name}
                  </p>
                )}
              </div>
            )}
          </div>

        {/* Navigation */}
        <div className="space-y-1">
          <ScrollArea className="h-[calc(100vh-10rem)]">
            {visibleItems.map((item) => {
              const href = buildUrl(item.path);
              const isActive = pathname === href;

              const linkContent = (
                <Link
                  key={item.path}
                  href={href}
                  className={cn(
                    'flex items-center w-full rounded-lg text-sm font-medium hover:text-primary hover:bg-primary/10 transition',
                    isCollapsed ? 'p-2 justify-center' : 'p-3',
                    isActive
                      ? 'text-primary bg-primary/10'
                      : 'text-muted-foreground'
                  )}
                >
                  <item.icon className={cn(
                    'h-5 w-5 flex-shrink-0',
                    item.color,
                    !isCollapsed && 'mr-3'
                  )} />
                  {!isCollapsed && (
                    <span className="truncate">{item.label}</span>
                  )}
                </Link>
              );

              if (isCollapsed) {
                return (
                  <Tooltip
                    key={item.path}
                    side="right"
                    content={
                      <div>
                        <div className="font-medium">{item.label}</div>
                        {item.description && (
                          <div className="text-xs opacity-75 mt-1">
                            {item.description}
                          </div>
                        )}
                      </div>
                    }
                  >
                    {linkContent}
                  </Tooltip>
                );
              }

              return linkContent;
            })}

            {/* Cross-membership navigation for elevated users */}
            {canAccessMembership('admin') && membership !== 'admin' && (
              <div className="mt-6 pt-4 border-t">
                {!isCollapsed && (
                  <p className="px-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider mb-2">
                    Quick Access
                  </p>
                )}
                {isCollapsed ? (
                  <Tooltip side="right" content="Admin Portal">
                    <Link
                      href={`/${tenantSlug}/admin/dashboard`}
                      className="flex items-center w-full p-2 rounded-lg text-sm font-medium hover:text-primary hover:bg-primary/10 transition text-muted-foreground justify-center"
                    >
                      <Shield className="h-5 w-5 text-blue-600" />
                    </Link>
                  </Tooltip>
                ) : (
                  <Link
                    href={`/${tenantSlug}/admin/dashboard`}
                    className="flex items-center w-full p-3 rounded-lg text-sm font-medium hover:text-primary hover:bg-primary/10 transition text-muted-foreground"
                  >
                    <Shield className="h-5 w-5 mr-3 text-blue-600" />
                    Admin Portal
                  </Link>
                )}
              </div>
            )}
          </ScrollArea>
        </div>
      </div>
    </div>
    </TooltipProvider>
  );
}
