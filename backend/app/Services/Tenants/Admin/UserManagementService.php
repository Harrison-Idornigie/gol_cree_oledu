<?php

namespace App\Services\Tenants\Admin;

use App\Models\Tenants\User;
use App\Models\Tenants\AdminInvite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Exception;

/**
 * User Management Service for Tenant Admins
 *
 * Handles user management operations within tenant scope including
 * invitations, user updates, and user lifecycle management.
 */
class UserManagementService
{
    /**
     * Get all users for the current tenant
     */
    public function getUsers(array $filters = [])
    {
        $query = User::query();

        // Apply filters
        if (isset($filters['membership'])) {
            $query->where('membership', $filters['membership']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate(15);
    }

    /**
     * Get team members (team membership)
     */
    public function getTeams(array $filters = [])
    {
        $filters['membership'] = 'team';
        return $this->getUsers($filters);
    }

    /**
     * Get students
     */
    public function getStudents(array $filters = [])
    {
        $filters['membership'] = 'student';
        return $this->getUsers($filters);
    }

    /**
     * Send invitation to new user
     */
    public function sendInvitation(array $data, User $invitedBy)
    {
        return DB::transaction(function () use ($data, $invitedBy) {
            // Check if user already exists
            $existingUser = User::where('email', $data['email'])->first();
            if ($existingUser) {
                throw new Exception('User with this email already exists');
            }

            // Check if there's already a pending invite
            $existingInvite = AdminInvite::where('email', $data['email'])
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->first();

            if ($existingInvite) {
                throw new Exception('There is already a pending invitation for this email');
            }

            // Create invitation
            $invite = AdminInvite::create([
                'email' => $data['email'],
                'token' => Str::random(32),
                'invited_by' => $invitedBy->id,
                'expires_at' => now()->addDays(7),
                'metadata' => $data['metadata'] ?? null,
            ]);

            // TODO: Send invitation email
            // Mail::to($data['email'])->send(new AdminInvitationMail($invite));

            return $invite;
        });
    }

    /**
     * Cancel/revoke an invitation
     */
    public function cancelInvitation(AdminInvite $invite)
    {
        if ($invite->used_at) {
            throw new Exception('Cannot cancel an invitation that has already been used');
        }

        $invite->delete();
        return true;
    }

    /**
     * Resend an invitation
     */
    public function resendInvitation(AdminInvite $invite)
    {
        if ($invite->used_at) {
            throw new Exception('Cannot resend an invitation that has already been used');
        }

        if ($invite->expires_at < now()) {
            // Extend expiration
            $invite->update([
                'expires_at' => now()->addDays(7),
                'token' => Str::random(32), // Generate new token for security
            ]);
        }

        // TODO: Send invitation email
        // Mail::to($invite->email)->send(new AdminInvitationMail($invite));

        return $invite;
    }

    /**
     * Update user information
     */
    public function updateUser(User $user, array $data, User $updatedBy)
    {
        return DB::transaction(function () use ($user, $data, $updatedBy) {
            // Remove sensitive fields that shouldn't be updated directly
            unset($data['password'], $data['email_verified_at'], $data['remember_token']);

            // Handle email changes carefully
            if (isset($data['email']) && $data['email'] !== $user->email) {
                // Check if email is already taken
                $existingUser = User::where('email', $data['email'])
                    ->where('id', '!=', $user->id)
                    ->first();

                if ($existingUser) {
                    throw new Exception('Email address is already in use');
                }

                // Reset email verification if email changes
                $data['email_verified_at'] = null;
            }

            $user->update($data);
            return $user->fresh();
        });
    }

    /**
     * Update user role/membership
     */
    public function updateUserRole(User $user, string $role, User $updatedBy)
    {
        $validRoles = ['student', 'team', 'admin'];

        if (!in_array($role, $validRoles)) {
            throw new Exception('Invalid role specified');
        }

        $user->update(['membership' => $role]);
        return $user->fresh();
    }

    /**
     * Update user status (active/inactive)
     */
    public function updateUserStatus(User $user, bool $isActive, User $updatedBy)
    {
        $user->update(['is_active' => $isActive]);
        return $user->fresh();
    }

    /**
     * Delete/deactivate user
     */
    public function deleteUser(User $user, User $deletedBy)
    {
        return DB::transaction(function () use ($user, $deletedBy) {
            // Instead of hard delete, we'll soft delete or deactivate
            $user->update([
                'is_active' => false,
                'deleted_at' => now(),
            ]);

            // TODO: Log the deletion activity

            return true;
        });
    }
}
