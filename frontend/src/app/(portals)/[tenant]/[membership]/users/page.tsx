'use client';

import { useState } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Plus, Search, Users, UserCheck, UserX, Mail } from 'lucide-react';
import Link from 'next/link';
import { useTenant } from '@/app/providers/auth-provider';
import { AdminOnly } from '@/components/portals/MembershipGuard';

interface UsersPageProps {
  params: {
    tenant: string;
    membership: string;
  };
}

export default function UsersPage({ params }: UsersPageProps) {
  const { tenantSlug } = useTenant();
  const [searchTerm, setSearchTerm] = useState('');

  // Build URL helper
  const buildUrl = (path: string) => `/${tenantSlug}/${params.membership}${path}`;

  // Mock data
  const users = [
    {
      id: 1,
      name: 'Sarah Johnson',
      email: 'sarah.johnson@school.edu',
      membership: 'team',
      status: 'active',
      lastActive: '2 hours ago',
      joinedAt: '2024-01-15'
    },
    {
      id: 2,
      name: 'Michael Chen',
      email: 'michael.chen@school.edu',
      membership: 'student',
      status: 'active',
      lastActive: '1 day ago',
      joinedAt: '2024-01-20'
    },
    {
      id: 3,
      name: 'Emily Rodriguez',
      email: 'emily.rodriguez@school.edu',
      membership: 'student',
      status: 'active',
      lastActive: '3 hours ago',
      joinedAt: '2024-01-18'
    },
    {
      id: 4,
      name: 'David Thompson',
      email: 'david.thompson@school.edu',
      membership: 'team',
      status: 'inactive',
      lastActive: '1 week ago',
      joinedAt: '2024-01-10'
    },
    {
      id: 5,
      name: 'Lisa Wang',
      email: 'lisa.wang@school.edu',
      membership: 'student',
      status: 'pending',
      lastActive: 'Never',
      joinedAt: '2024-01-25'
    }
  ];

  const filteredUsers = users.filter(user =>
    user.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
    user.email.toLowerCase().includes(searchTerm.toLowerCase())
  );

  const getRoleColor = (membership: string) => {
    switch (membership) {
      case 'admin':
        return 'bg-red-100 text-red-800';
      case 'team':
        return 'bg-blue-100 text-blue-800';
      case 'student':
        return 'bg-green-100 text-green-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'active':
        return 'bg-green-100 text-green-800';
      case 'inactive':
        return 'bg-yellow-100 text-yellow-800';
      case 'pending':
        return 'bg-orange-100 text-orange-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  };

  const stats = {
    total: users.length,
    active: users.filter(u => u.status === 'active').length,
    pending: users.filter(u => u.status === 'pending').length,
    teams: users.filter(u => u.membership === 'team').length,
    students: users.filter(u => u.membership === 'student').length
  };

  return (
    <AdminOnly fallback={
      <div className="text-center py-12">
        <h2 className="text-2xl font-bold">Access Restricted</h2>
        <p className="text-muted-foreground">Only administrators can manage users.</p>
      </div>
    }>
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">User Management</h1>
            <p className="text-muted-foreground">
              Manage teachers and students in your organization
            </p>
          </div>
          <Button asChild>
            <Link href={buildUrl('/users/invite')}>
              <Plus className="mr-2 h-4 w-4" />
              Invite Users
            </Link>
          </Button>
        </div>

        {/* Stats Cards */}
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Total Users</CardTitle>
              <Users className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{stats.total}</div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Active</CardTitle>
              <UserCheck className="h-4 w-4 text-green-600" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{stats.active}</div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Pending</CardTitle>
              <UserX className="h-4 w-4 text-orange-600" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{stats.pending}</div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Teachers</CardTitle>
              <Users className="h-4 w-4 text-blue-600" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{stats.teams}</div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Students</CardTitle>
              <Users className="h-4 w-4 text-green-600" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{stats.students}</div>
            </CardContent>
          </Card>
        </div>

        {/* Search */}
        <Card>
          <CardHeader>
            <CardTitle>Search Users</CardTitle>
            <CardDescription>
              Find users by name or email address
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="relative">
              <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder="Search users..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="pl-8"
              />
            </div>
          </CardContent>
        </Card>

        {/* Users List */}
        <Card>
          <CardHeader>
            <CardTitle>All Users ({filteredUsers.length})</CardTitle>
            <CardDescription>
              Manage user accounts and permissions
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              {filteredUsers.map((user) => (
                <div
                  key={user.id}
                  className="flex items-center justify-between p-4 border rounded-lg hover:bg-muted/50 transition-colors"
                >
                  <div className="flex items-center space-x-4">
                    <div className="w-10 h-10 bg-primary/10 rounded-full flex items-center justify-center">
                      <span className="text-sm font-medium text-primary">
                        {user.name.split(' ').map(n => n[0]).join('')}
                      </span>
                    </div>
                    <div>
                      <h4 className="font-medium">{user.name}</h4>
                      <p className="text-sm text-muted-foreground flex items-center">
                        <Mail className="mr-1 h-3 w-3" />
                        {user.email}
                      </p>
                      <p className="text-xs text-muted-foreground">
                        Last active: {user.lastActive} • Joined: {user.joinedAt}
                      </p>
                    </div>
                  </div>
                  <div className="flex items-center space-x-2">
                    <Badge className={getRoleColor(user.membership)}>
                      {user.membership}
                    </Badge>
                    <Badge className={getStatusColor(user.status)}>
                      {user.status}
                    </Badge>
                    <Button variant="outline" size="sm" asChild>
                      <Link href={buildUrl(`/users/${user.id}`)}>
                        Manage
                      </Link>
                    </Button>
                  </div>
                </div>
              ))}

              {filteredUsers.length === 0 && (
                <div className="text-center py-8">
                  <Users className="mx-auto h-12 w-12 text-muted-foreground mb-4" />
                  <h3 className="text-lg font-semibold mb-2">No users found</h3>
                  <p className="text-muted-foreground">
                    {searchTerm ? 'Try adjusting your search terms.' : 'No users have been added yet.'}
                  </p>
                </div>
              )}
            </div>
          </CardContent>
        </Card>
      </div>
    </AdminOnly>
  );
}
