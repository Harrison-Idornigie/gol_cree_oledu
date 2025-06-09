'use client';

import { Button } from '@/components/ui/button';
import TenantCard from './TenantCard';
import { Tenant } from '@/types/super-admin';

interface TenantListProps {
  tenants: Tenant[];
  loading: boolean;
  currentPage: number;
  totalPages: number;
  onPageChange: (page: number) => void;
  onTenantView: (tenant: Tenant) => void;
  onTenantDelete: (tenantId: number) => void;
}

export default function TenantList({
  tenants,
  loading,
  currentPage,
  totalPages,
  onPageChange,
  onTenantView,
  onTenantDelete
}: TenantListProps) {
  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {/* Tenants List */}
      <div className="grid gap-4">
        {tenants.map((tenant) => (
          <TenantCard
            key={tenant.id}
            tenant={tenant}
            onView={onTenantView}
            onDelete={onTenantDelete}
          />
        ))}
      </div>

      {/* Pagination */}
      {totalPages > 1 && (
        <div className="flex justify-center gap-2">
          <Button
            variant="outline"
            onClick={() => onPageChange(Math.max(1, currentPage - 1))}
            disabled={currentPage === 1}
          >
            Previous
          </Button>
          <span className="flex items-center px-4">
            Page {currentPage} of {totalPages}
          </span>
          <Button
            variant="outline"
            onClick={() => onPageChange(Math.min(totalPages, currentPage + 1))}
            disabled={currentPage === totalPages}
          >
            Next
          </Button>
        </div>
      )}
    </div>
  );
}
