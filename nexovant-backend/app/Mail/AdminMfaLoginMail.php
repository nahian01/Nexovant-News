<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;

class AdminMfaLoginMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $recipient,
        public readonly string $mfaCode,
        public readonly int $expiresInMinutes = 10,
        public readonly ?string $ipAddress = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[Nexovant Security] Admin MFA One-Time Login Password: {$this->mfaCode}",
        );
    }

    public function content(): Content
    {
        $frontendUrl = rtrim((string) Config::get('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');

        return new Content(
            view: 'emails.admin_mfa',
            with: [
                'recipientName' => $this->recipient->name,
                'recipientEmail' => $this->recipient->email,
                'mfaCode' => $this->mfaCode,
                'expiresInMinutes' => $this->expiresInMinutes,
                'ipAddress' => $this->ipAddress ?? request()?->ip() ?? 'Unknown',
                'frontendUrl' => $frontendUrl,
            ],
        );
    }

    public function headers(): Headers
    {
        return new Headers(
            text: [
                'X-Nexovant-Mail-Type' => 'admin-mfa-login',
                'X-Nexovant-User-ID' => (string) $this->recipient->id,
            ],
        );
    }
}
