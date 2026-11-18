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

class ForgotPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $recipient,
        public readonly string $token,
        public readonly int $expiresInMinutes = 60
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reset Your Executive Credentials — Nexovant Intelligence',
        );
    }

    public function content(): Content
    {
        $frontendUrl = rtrim((string) Config::get('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');
        $resetUrl = $frontendUrl . '/reset-password?token=' . urlencode($this->token) . '&email=' . urlencode($this->recipient->email);

        return new Content(
            view: 'emails.forgot_password',
            with: [
                'recipientName' => $this->recipient->name,
                'recipientEmail' => $this->recipient->email,
                'resetUrl' => $resetUrl,
                'expiresInMinutes' => $this->expiresInMinutes,
                'frontendUrl' => $frontendUrl,
            ],
        );
    }

    public function headers(): Headers
    {
        return new Headers(
            text: [
                'X-Nexovant-Mail-Type' => 'password-reset',
                'X-Nexovant-User-ID' => $this->recipient->id,
            ],
        );
    }
}
