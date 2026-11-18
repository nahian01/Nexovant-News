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

class WelcomeAccountMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $recipient,
        public readonly ?string $temporaryPassword = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to Nexovant Intelligence — Executive Account Initialized',
        );
    }

    public function content(): Content
    {
        $appUrl = rtrim((string) Config::get('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');
        $loginUrl = $appUrl . '/login';
        $dashboardUrl = $appUrl . '/dashboard';

        return new Content(
            view: 'emails.welcome_account',
            with: [
                'recipientName' => $this->recipient->name,
                'recipientEmail' => $this->recipient->email,
                'accountType' => ucfirst(str_replace('_', ' ', $this->recipient->account_type ?? 'Subscriber')),
                'loginUrl' => $loginUrl,
                'dashboardUrl' => $dashboardUrl,
                'temporaryPassword' => $this->temporaryPassword,
                'appUrl' => $appUrl,
            ],
        );
    }

    public function headers(): Headers
    {
        return new Headers(
            text: [
                'X-Nexovant-Mail-Type' => 'account-welcome',
                'X-Nexovant-User-ID' => $this->recipient->id,
            ],
        );
    }
}
