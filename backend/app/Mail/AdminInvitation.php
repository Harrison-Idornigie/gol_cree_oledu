<?php

namespace App\Mail;

use App\Models\Tenants\AdminInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdminInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public AdminInvite $invite;

    /**
     * Create a new message instance.
     */
    public function __construct(AdminInvite $invite)
    {
        $this->invite = $invite;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->markdown('emails.admin.invitation')
            ->subject('Invitation to Join as Administrator')
            ->with([
                'inviteUrl' => config('app.url') . '/admin/accept-invite?token=' . $this->invite->token,
                'role' => $this->invite->role,
                'expiresAt' => $this->invite->expires_at,
                'invitedBy' => $this->invite->inviter->name
            ]);
    }
}