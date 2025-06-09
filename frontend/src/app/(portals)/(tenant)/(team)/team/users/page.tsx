'use client';

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { AlertDialog } from '@/components/admin/AlertDialog';
import { 
  getAdminUsers, 
  inviteAdmin, 
  revokeInvite, 
  removeAdmin,
  resendInvite 
} from '@/app/_actions/admin/user-management-actions';
import { AdminInvite, User, UserRole } from '@/types/user';
import { toast } from 'sonner';

export default function AdminUsersPage() {
  const router = useRouter();
  const [users, setUsers] = useState<User[]>([]);
  const [invites, setInvites] = useState<AdminInvite[]>([]);
  const [loading, setLoading] = useState(true);
  const [email, setEmail] = useState('');
  const [showInviteForm, setShowInviteForm] = useState(false);
  const [userToRemove, setUserToRemove] = useState<number | null>(null);
  const [inviteToRevoke, setInviteToRevoke] = useState<number | null>(null);

  useEffect(() => {
    loadUsers();
  }, []);

  const loadUsers = async () => {
    const result = await getAdminUsers();
    if (result.error) {
      toast.error('Error', {
        description: result.error,
      });
    } else if (result.data) {
      setUsers(result.data.users);
      setInvites(result.data.invites);
    }
    setLoading(false);
  };

  const handleInviteSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const result = await inviteAdmin({ email, role: UserRole.ADMIN });
    if (result.error) {
      toast.error('Error', {
        description: result.error,
      });
    } else {
      toast.success('Success', {
        description: 'Admin invite sent successfully',
      });
      setEmail('');
      setShowInviteForm(false);
      loadUsers();
    }
  };

  const handleResendInvite = async (inviteId: number) => {
    const result = await resendInvite(inviteId);
    if (result.error) {
      toast.error('Error', {
        description: result.error,
      });
    } else {
      toast.success('Success', {
        description: 'Invite resent successfully',
      });
    }
  };

  const handleRevokeInvite = async (inviteId: number) => {
    const result = await revokeInvite(inviteId);
    if (result.error) {
      toast.error('Error', {
        description: result.error,
      });
    } else {
      toast.success('Success', {
        description: 'Invite revoked successfully',
      });
      loadUsers();
    }
    setInviteToRevoke(null);
  };

  const handleRemoveAdmin = async (userId: number) => {
    const result = await removeAdmin(userId);
    if (result.error) {
      toast.error('Error', {
        description: result.error,
      });
    } else {
      toast.success('Success', {
        description: 'Admin removed successfully',
      });
      loadUsers();
    }
    setUserToRemove(null);
  };

  if (loading) {
    return (
      <div className="flex h-[50vh] items-center justify-center">
        <div className="text-center space-y-4">
          <div className="animate-spin h-8 w-8 border-4 border-primary border-t-transparent rounded-full mx-auto"></div>
          <p className="text-muted-foreground">Loading users...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-bold tracking-tight">Admin Users</h2>
          <p className="text-muted-foreground">
            Manage admin users and invites
          </p>
        </div>
        <Button onClick={() => setShowInviteForm(true)}>
          Invite Admin
        </Button>
      </div>

      {showInviteForm && (
        <Card className="p-6">
          <form onSubmit={handleInviteSubmit} className="space-y-4">
            <div className="space-y-2">
              <label className="text-sm font-medium">Email</label>
              <Input
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
                placeholder="Enter email address"
              />
            </div>
            <div className="flex justify-end space-x-2">
              <Button
                type="button"
                variant="outline"
                onClick={() => setShowInviteForm(false)}
              >
                Cancel
              </Button>
              <Button type="submit">
                Send Invite
              </Button>
            </div>
          </form>
        </Card>
      )}

      <div className="space-y-6">
        <Card className="p-6">
          <h3 className="text-lg font-semibold mb-4">Active Admins</h3>
          <div className="space-y-4">
            {users.map((user) => (
              <div
                key={user.id}
                className="flex items-center justify-between py-2 border-b last:border-0"
              >
                <div className="flex items-center space-x-4">
                  {user.avatar_url && (
                    <img
                      src={user.avatar_url}
                      alt=""
                      className="h-8 w-8 rounded-full"
                    />
                  )}
                  <div>
                    <div className="font-medium">{user.name}</div>
                    <div className="text-sm text-muted-foreground">
                      {user.email}
                    </div>
                  </div>
                </div>
                <Button
                  variant="destructive"
                  size="sm"
                  onClick={() => setUserToRemove(user.id)}
                >
                  Remove Admin
                </Button>
              </div>
            ))}
          </div>
        </Card>

        {invites.length > 0 && (
          <Card className="p-6">
            <h3 className="text-lg font-semibold mb-4">Pending Invites</h3>
            <div className="space-y-4">
              {invites.map((invite) => (
                <div
                  key={invite.id}
                  className="flex items-center justify-between py-2 border-b last:border-0"
                >
                  <div>
                    <div className="font-medium">{invite.email}</div>
                    <div className="text-sm text-muted-foreground">
                      Expires: {new Date(invite.expires_at).toLocaleDateString()}
                    </div>
                  </div>
                  <div className="flex space-x-2">
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => handleResendInvite(invite.id)}
                    >
                      Resend
                    </Button>
                    <Button
                      variant="destructive"
                      size="sm"
                      onClick={() => setInviteToRevoke(invite.id)}
                    >
                      Revoke
                    </Button>
                  </div>
                </div>
              ))}
            </div>
          </Card>
        )}
      </div>

      {userToRemove && (
        <AlertDialog
          trigger={<></>}
          title="Remove Admin"
          description="Are you sure you want to remove this admin? They will lose all admin privileges."
          confirmText="Remove"
          cancelText="Cancel"
          variant="destructive"
          onConfirm={() => handleRemoveAdmin(userToRemove)}
          onCancel={() => setUserToRemove(null)}
        />
      )}

      {inviteToRevoke && (
        <AlertDialog
          trigger={<></>}
          title="Revoke Invite"
          description="Are you sure you want to revoke this invite? The link will no longer work."
          confirmText="Revoke"
          cancelText="Cancel"
          variant="destructive"
          onConfirm={() => handleRevokeInvite(inviteToRevoke)}
          onCancel={() => setInviteToRevoke(null)}
        />
      )}
    </div>
  );
}