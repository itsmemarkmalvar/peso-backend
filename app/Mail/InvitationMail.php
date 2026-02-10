<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @var int User id – resolved when the job runs (avoids ModelNotFoundException if user was deleted) */
    public int $userId;
    public string $invitationUrl;
    public string $role;
    public $logoBase64;
    public $logoPath;

    /**
     * Create a new message instance.
     * Pass user id (not the model) so queued jobs don't fail when the user was deleted after queueing.
     */
    public function __construct(int $userId, string $invitationUrl, string $role)
    {
        $this->userId = $userId;
        $this->invitationUrl = $invitationUrl;
        $this->role = $role;
        
        // Find logo file path and convert to base64
        $logoPath = public_path('images/image-Photoroom.png');
        if (!file_exists($logoPath)) {
            $logoPath = base_path('public/images/image-Photoroom.png');
        }
        if (!file_exists($logoPath)) {
            $logoPath = storage_path('app/public/images/image-Photoroom.png');
        }
        
        $this->logoPath = file_exists($logoPath) && is_readable($logoPath) ? $logoPath : null;
        
        // Convert to base64 for email embedding
        if ($this->logoPath) {
            try {
                $logoData = file_get_contents($this->logoPath);
                if ($logoData !== false && strlen($logoData) > 0) {
                    $this->logoBase64 = 'data:image/png;base64,' . base64_encode($logoData);
                }
            } catch (\Exception $e) {
                \Log::error('Failed to read logo file: ' . $e->getMessage());
                $this->logoBase64 = null;
            }
        } else {
            \Log::warning('Logo file not found. Tried: ' . public_path('images/image-Photoroom.png') . ' and ' . base_path('public/images/image-Photoroom.png'));
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

    /**
     * Build the message.
     * Resolve the user when the job runs; if user was deleted, fail with a clear message.
     */
    public function build()
    {
        $user = User::find($this->userId);
        if (!$user) {
            throw new \RuntimeException(
                'User no longer exists (id: ' . $this->userId . '). Invitation email not sent. You may flush old jobs: php artisan queue:flush'
            );
        }

        return $this->view('emails.invitation')
            ->with([
                'user' => $user,
                'invitationUrl' => $this->invitationUrl,
                'role' => $this->role,
                'logoBase64' => $this->logoBase64,
                'logoPath' => $this->logoPath,
            ]);
    }
}
