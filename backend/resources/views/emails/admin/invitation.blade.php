@component('mail::message')
# You've Been Invited

You have been invited to join as a {{ $membership }} by {{ $invitedBy }}.

Click the button below to accept your invitation:

@component('mail::button', ['url' => $inviteUrl])
Accept Invitation
@endcomponent

This invitation will expire on {{ $expiresAt->format('F j, Y') }}.

Thanks,<br>
{{ config('app.name') }}
@endcomponent