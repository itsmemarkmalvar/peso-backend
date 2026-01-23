<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $invitationUrl;
    public $role;
    public $logoBase64;

    /**
     * Create a new message instance.
     */
    public function __construct($user, $invitationUrl, $role)
    {
        $this->user = $user;
        $this->invitationUrl = $invitationUrl;
        $this->role = $role;
        
        // Embed logo as base64 for email compatibility
        $logoPath = public_path('images/image-Photoroom.png');
        if (file_exists($logoPath)) {
            $logoData = file_get_contents($logoPath);
            $logoBase64 = base64_encode($logoData);
            $this->logoBase64 = 'data:image/png;base64,' . $logoBase64;
        } else {
            $this->logoBase64 = null;
        }
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to PESO OJT Attendance System - Account Invitation',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.invitation',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
