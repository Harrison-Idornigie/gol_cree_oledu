'use client';

import { useState, useEffect, useCallback } from 'react';
import { Button } from '@/components/ui/button';
import { Plus } from 'lucide-react';
import { useToast } from '@/hooks/use-toast';
import Link from 'next/link';
import { getTenants, deleteTenant } from '@/app/_actions/super/tenant-actions';
import TenantFilters from './TenantFilters';
import TenantList from './TenantList';
import { Tenant } from '@/types/super-admin';

export default function TenantManagement() {
  const [tenants, setTenants] = useState<Tenant[]>([]);
  const [loading, setLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');
  const [statusFilter, setStatusFilter] = useState<string>('');
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const { toast } = useToast();

  const fetchTenants = useCallback(async () => {
    try {
      setLoading(true);
      
      const result = await getTenants({
        page: currentPage,
        per_page: 15,
        search: searchTerm || undefined,
        status: statusFilter || undefined,
      });

      if (result.error) {
        toast({
          title: 'Error',
          description: result.error,
          variant: 'destructive',
        });
        return;
      }

      if (result.data) {
        setTenants(result.data.data);
        setCurrentPage(result.data.current_page);
        setTotalPages(result.data.last_page);
      }
    } catch {
      toast({
        title: 'Error',
        description: 'Failed to fetch tenants',
        variant: 'destructive',
      });
    } finally {
      setLoading(false);
    }
  }, [currentPage, searchTerm, statusFilter, toast]);

  useEffect(() => {
    fetchTenants();
  }, [fetchTenants]);

  const handleDeleteTenant = async (tenantId: number) => {
    if (!confirm('Are you sure you want to delete this tenant? This action cannot be undone.')) {
      return;
    }

    try {
      const result = await deleteTenant(tenantId);
      
      if (result.error) {
        toast({
          title: 'Error',
          description: result.error,
          variant: 'destructive',
        });
        return;
      }
      
      toast({
        title: 'Success',
        description: 'Tenant deleted successfully',
      });
      fetchTenants();
    } catch {
      toast({
        title: 'Error',
        description: 'Failed to delete tenant',
        variant: 'destructive',
      });
    }
  };

  const handleSwitchTenant = async (tenant: Tenant) => {
    try {
      // TODO: Replace with actual API call
      // await axios.post(`/api/super-admin/tenants/${tenant.id}/switch`);
      
      toast({
        title: 'Success',
        description: `Switched to tenant: ${tenant.name}`,
      });
    } catch {
      toast({
        title: 'Error',
        description: 'Failed to switch tenant context',
        variant: 'destructive',
      });
    }
  };

  const handlePageChange = (page: number) => {
    setCurrentPage(page);
  };

  const handleSearchChange = (value: string) => {
    setSearchTerm(value);
    setCurrentPage(1); // Reset to first page when searching
  };

  const handleStatusFilterChange = (value: string) => {
    setStatusFilter(value);
    setCurrentPage(1); // Reset to first page when filtering
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Tenant Management</h1>
          <p className="text-gray-600">Manage school districts and organizations</p>
        </div>
        <Button asChild>
          <Link href="/super/tenants/create" className="flex items-center gap-2">
            <Plus className="h-4 w-4" />
            Create Tenant
          </Link>
        </Button>
      </div>

      {/* Filters */}
      <TenantFilters
        searchTerm={searchTerm}
        statusFilter={statusFilter}
        onSearchChange={handleSearchChange}
        onStatusFilterChange={handleStatusFilterChange}
      />

      {/* Tenant List */}
      <TenantList
        tenants={tenants}
        loading={loading}
        currentPage={currentPage}
        totalPages={totalPages}
        onPageChange={handlePageChange}
        onTenantView={handleSwitchTenant}
        onTenantDelete={handleDeleteTenant}
      />
    </div>
  );
}
