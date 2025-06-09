'use client';

import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Eye, Edit, Trash2, Users, BookOpen, Globe } from 'lucide-react';
import Link from 'next/link';
import { Tenant } from '@/types/super-admin';

interface TenantCardProps {
  tenant: Tenant;
  onView: (tenant: Tenant) => void;
  onDelete: (tenantId: number) => void;
}

export default function TenantCard({ tenant, onView, onDelete }: TenantCardProps) {
  const getStatusBadge = (status: string) => {
    const variants = {
      active: 'bg-green-100 text-green-800',
      inactive: 'bg-gray-100 text-gray-800',
      suspended: 'bg-red-100 text-red-800',
    };
    
    return (
      <Badge className={variants[status as keyof typeof variants] || variants.inactive}>
        {status.charAt(0).toUpperCase() + status.slice(1)}
      </Badge>
    );
  };

  return (
    <Card className="hover:shadow-md transition-shadow">
      <CardContent className="pt-6">
        <div className="flex items-center justify-between">
          <div className="flex-1">
            <div className="flex items-center gap-3 mb-2">
              <h3 className="text-lg font-semibold text-gray-900">{tenant.name}</h3>
              {getStatusBadge(tenant.status)}
              {tenant.domain && (
                <Badge variant="outline" className="text-xs">
                  {tenant.domain}
                </Badge>
              )}
            </div>
            
            {tenant.description && (
              <p className="text-gray-600 mb-3">{tenant.description}</p>
            )}
            
            <div className="flex items-center gap-6 text-sm text-gray-500">
              <div className="flex items-center gap-1">
                <Users className="h-4 w-4" />
                <span>{tenant.users_count} users</span>
              </div>
              <div className="flex items-center gap-1">
                <BookOpen className="h-4 w-4" />
                <span>{tenant.learning_paths_count} paths</span>
              </div>
              <div className="flex items-center gap-1">
                <Globe className="h-4 w-4" />
                <span>{tenant.languages_count} languages</span>
              </div>
            </div>
          </div>
          
          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              onClick={() => onView(tenant)}
              className="flex items-center gap-1"
            >
              <Eye className="h-4 w-4" />
              View
            </Button>
            <Button
              variant="outline"
              size="sm"
              asChild
              className="flex items-center gap-1"
            >
              <Link href={`/super/tenants/${tenant.id}/edit`}>
                <Edit className="h-4 w-4" />
                Edit
              </Link>
            </Button>
            <Button
              variant="outline"
              size="sm"
              onClick={() => onDelete(tenant.id)}
              className="flex items-center gap-1 text-red-600 hover:text-red-700"
            >
              <Trash2 className="h-4 w-4" />
              Delete
            </Button>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
