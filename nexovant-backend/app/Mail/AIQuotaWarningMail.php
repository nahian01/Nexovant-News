<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\AIProvider;
use App\Models\AIProviderCredential;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class AIQuotaWarningMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly AIProvider $provider,
        public readonly AIProviderCredential $credential,
        public readonly int $currentTokens,
        public readonly int $dailyTokenBudget,
        public readonly float $usagePercentage,
        public readonly bool $isRotated = false,
        public readonly ?string $nextProviderName = null
    ) {}

    public function envelope(): Envelope
    {
        $status = $this->isRotated ? '[ROTATED]' : '[QUOTA ALERT]';
        return new Envelope(
            subject: "{$status} Nexovant AI Engine: {$this->provider->name} at " . round($this->usagePercentage, 1) . "% Free Token Limit",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ai_quota_alert',
            with: [
                'providerName' => $this->provider->name,
                'providerSlug' => $this->provider->slug,
                'credentialLabel' => $this->credential->display_name ?? $this->credential->label,
                'currentTokens' => $this->currentTokens,
                'dailyTokenBudget' => $this->dailyTokenBudget,
                'usagePercentage' => round($this->usagePercentage, 1),
                'isRotated' => $this->isRotated,
                'nextProviderName' => $this->nextProviderName,
            ],
        );
    }

    public function headers(): Headers
    {
        return new Headers(
            text: [
                'X-Nexovant-Mail-Type' => 'ai-quota-alert',
                'X-Nexovant-Provider' => $this->provider->slug,
            ],
        );
    }
}
