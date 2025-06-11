'use client';

import { useState, useEffect, useCallback } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { 
  Search, 
  Eye, 
  UserX, 
  UserCheck, 
  Shield,
  Building2,
  Mail,
  Calendar
} from 'lucide-react';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { useToast } from '@/hooks/use-toast';
import { 
  searchGlobalUsers, 
  suspendUser, 
  activateUser, 
  impersonateUser 
} from '@/app/_actions/super/user-actions';
import { GlobalUser, UserSearchResult } from '@/types/super/super-admin';

export default function GlobalUserManagement() {
  const [users, setUsers] = useState<GlobalUser[]>([]);
  const [loading, setLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState('');
  const [membershipFilter, setMembershipFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [total, setTotal] = useState(0);
  const { toast } = useToast();

  const fetchUsers = useCallback(async () => {
    try {
      setLoading(true);
      
      const result = await searchGlobalUsers({
        query: searchQuery || undefined,
        membership: membershipFilter || undefined,
        is_active: statusFilter ? statusFilter === 'active' : undefined,
        page: currentPage,
        per_page: 20
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
        setUsers(result.data.users);
        setCurrentPage(result.data.current_page);
        setTotalPages(result.data.last_page);
        setTotal(result.data.total);
      }
    } catch {
      toast({
        title: 'Error',
        description: 'Failed to fetch users',
        variant: 'destructive',
      });
    } finally {
      setLoading(false);
    }
  }, [currentPage, searchQuery, membershipFilter, statusFilter, toast]);

  useEffect(() => {
    fetchUsers();
  }, [fetchUsers]);

  const handleSuspendUser = async (userId: number) => {
    if (!confirm('Are you sure you want to suspend this user?')) {
      return;
    }

    try {
      const result = await suspendUser(userId, 'Suspended by super admin');
      
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
        description: 'User suspended successfully',
      });
      fetchUsers();
    } catch {
      toast({
        title: 'Error',
        description: 'Failed to suspend user',
        variant: 'destructive',
      });
    }
  };

  const handleActivateUser = async (userId: number) => {
    try {
      const result = await activateUser(userId);
      
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
        description: 'User activated successfully',
      });
      fetchUsers();
    } catch {
      toast({
        title: 'Error',
        description: 'Failed to activate user',
        variant: 'destructive',
      });
    }
  };

  const handleImpersonateUser = async (userId: number) => {
    if (!confirm('Are you sure you want to impersonate this user?')) {
      return;
    }

    try {
      const result = await impersonateUser(userId);
      
      if (result.error) {
        toast({
          title: 'Error',
          description: result.error,
          variant: 'destructive',
        });
        return;
      }
      
      if (result.data) {
        toast({
          title: 'Success',
          description: 'Impersonation started successfully',
        });
        // Redirect to the user's dashboard
        window.location.href = result.data.redirect_url;
      }
    } catch {
      toast({
        title: 'Error',
        description: 'Failed to start impersonation',
        variant: 'destructive',
      });
    }
  };

  const getStatusBadge = (isActive: boolean) => {
    return (
      <Badge className={isActive ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}>
        {isActive ? 'Active' : 'Suspended'}
      </Badge>
    );
  };

  const getMembershipBadge = (membership: string) => {
    const membershipColors = {
      'super-admin': 'bg-purple-100 text-purple-800',
      'tenant-admin': 'bg-blue-100 text-blue-800',
      'team': 'bg-green-100 text-green-800',
      'student': 'bg-gray-100 text-gray-800',
    };
    
    return (
      <Badge className={membershipColors[membership as keyof typeof membershipColors] || 'bg-gray-100 text-gray-800'}>
        {membership.replace('-', ' ').replace(/\b\w/g, l => l.toUpperCase())}
      </Badge>
    );
  };

  const handleSearchChange = (value: string) => {
    setSearchQuery(value);
    setCurrentPage(1);
  };

  const handleMembershipFilterChange = (value: string) => {
    setMembershipFilter(value);
    setCurrentPage(1);
  };

  const handleStatusFilterChange = (value: string) => {
    setStatusFilter(value);
    setCurrentPage(1);
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Global User Management</h1>
          <p className="text-gray-600">Search and manage users across all tenants</p>
        </div>
        <div className="text-sm text-gray-600">
          {total.toLocaleString()} total users
        </div>
      </div>

      {/* Filters */}
      <Card>
        <CardContent className="pt-6">
          <div className="flex gap-4 items-center">
            <div className="flex-1">
              <div className="relative">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4" />
                <Input
                  placeholder="Search users by name or email..."
                  value={searchQuery}
                  onChange={(e) => handleSearchChange(e.target.value)}
                  className="pl-10"
                />
              </div>
            </div>
            
            <Select value={membershipFilter} onValueChange={handleMembershipFilterChange}>
              <SelectTrigger className="w-[150px]">
                <SelectValue placeholder="All Memberships" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="">All Memberships</SelectItem>
                <SelectItem value="super-admin">Super Admin</SelectItem>
                <SelectItem value="tenant-admin">Tenant Admin</SelectItem>
                <SelectItem value="team">Team</SelectItem>
                <SelectItem value="student">Student</SelectItem>
              </SelectContent>
            </Select>
            
            <Select value={statusFilter} onValueChange={handleStatusFilterChange}>
              <SelectTrigger className="w-[150px]">
                <SelectValue placeholder="All Status" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="">All Status</SelectItem>
                <SelectItem value="active">Active</SelectItem>
                <SelectItem value="suspended">Suspended</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </CardContent>
      </Card>

      {/* Users List */}
      <div className="grid gap-4">
        {users.map((user) => (
          <Card key={user.id} className="hover:shadow-md transition-shadow">
            <CardContent className="pt-6">
              <div className="flex items-center justify-between">
                <div className="flex-1">
                  <div className="flex items-center gap-3 mb-2">
                    <h3 className="text-lg font-semibold text-gray-900">{user.name}</h3>
                    {getStatusBadge(user.is_active)}
                    {getMembershipBadge(user.membership)}
                  </div>
                  
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                    <div className="flex items-center gap-2">
                      <Mail className="h-4 w-4 text-gray-400" />
                      <span>{user.email}</span>
                    </div>
                    
                    <div className="flex items-center gap-2">
                      <Building2 className="h-4 w-4 text-gray-400" />
                      <span>{user.tenant_name}</span>
                    </div>
                    
                    <div className="flex items-center gap-2">
                      <Calendar className="h-4 w-4 text-gray-400" />
                      <span>
                        {user.last_login 
                          ? `Last login: ${new Date(user.last_login).toLocaleDateString()}`
                          : 'Never logged in'
                        }
                      </span>
                    </div>
                  </div>
                </div>
                
                <div className="flex items-center gap-2">
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => handleImpersonateUser(user.id)}
                    className="flex items-center gap-1"
                  >
                    <Shield className="h-4 w-4" />
                    Impersonate
                  </Button>
                  
                  {user.is_active ? (
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => handleSuspendUser(user.id)}
                      className="flex items-center gap-1 text-red-600 hover:text-red-700"
                    >
                      <UserX className="h-4 w-4" />
                      Suspend
                    </Button>
                  ) : (
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => handleActivateUser(user.id)}
                      className="flex items-center gap-1 text-green-600 hover:text-green-700"
                    >
                      <UserCheck className="h-4 w-4" />
                      Activate
                    </Button>
                  )}
                </div>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>

      {/* Pagination */}
      {totalPages > 1 && (
        <div className="flex justify-center gap-2">
          <Button
            variant="outline"
            onClick={() => setCurrentPage(prev => Math.max(1, prev - 1))}
            disabled={currentPage === 1}
          >
            Previous
          </Button>
          <span className="flex items-center px-4">
            Page {currentPage} of {totalPages}
          </span>
          <Button
            variant="outline"
            onClick={() => setCurrentPage(prev => Math.min(totalPages, prev + 1))}
            disabled={currentPage === totalPages}
          >
            Next
          </Button>
        </div>
      )}

      {users.length === 0 && (
        <Card>
          <CardContent className="pt-6">
            <div className="text-center py-8">
              <p className="text-gray-500">No users found matching your criteria</p>
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  );
}
