<?php

declare(strict_types=1);

namespace App\Services\Notification\Adapters;

use App\Models\Notification;
use App\Models\NotificationDeliveryLog;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppChannelAdapter implements ChannelAdapterInterface
{
    public function getChannelName(): string
    {
        return 'whatsapp';
    }

    public function getToken(): ?string
    {
        return Config::get('services.whatsapp.token') ?: env('WHATSAPP_TOKEN');
    }

    public function getPhoneNumberId(): ?string
    {
        return Config::get('services.whatsapp.phone_number_id') ?: env('WHATSAPP_PHONE_NUMBER_ID');
    }

    public function isConfigured(): bool
    {
        return !empty($this->getToken()) && !empty($this->getPhoneNumberId());
    }

    public function getOnboardingStatus(): array
    {
        return [
            'provider' => 'Meta WhatsApp Business Cloud API (v21.0)',
            'is_configured' => $this->isConfigured(),
            'merchant_onboarding_status' => $this->isConfigured() ? 'active' : 'pending_merchant_onboarding',
            'required_assets' => [
                'Meta Business Manager Verification',
                'WhatsApp Business Account (WABA)',
                'Registered & Verified Sender Phone Number',
                'Pre-Approved High Structured Message (HSM) Templates',
            ],
            'compliance_note' => 'Nexovant strictly enforces Meta Cloud API compliance; unauthorized third-party scraping libraries are disabled for security and regulatory adherence.',
        ];
    }

    public function send(Notification $notification, User $recipient, array $context = []): array
    {
        if (!$this->isConfigured()) {
            $log = NotificationDeliveryLog::create([
                'notification_id' => $notification->id,
                'channel' => 'whatsapp',
                'status' => 'unconfigured_channel',
                'attempt_count' => 1,
                'recipient' => $recipient->id,
                'error_message' => 'Official Meta WhatsApp Cloud API credentials not configured; channel pending merchant onboarding.',
                'sent_at' => now(),
            ]);

            return [
                'status' => 'unconfigured_channel',
                'error' => 'WhatsApp Cloud API pending merchant onboarding',
            ];
        }

        $phone = $context['phone'] ?? $recipient->notificationPreference?->whatsapp_phone;
        if (empty($phone)) {
            $log = NotificationDeliveryLog::create([
                'notification_id' => $notification->id,
                'channel' => 'whatsapp',
                'status' => 'unconfigured_channel',
                'attempt_count' => 1,
                'recipient' => $recipient->id,
                'error_message' => 'Subscriber has no verified WhatsApp phone number configured',
                'sent_at' => now(),
            ]);

            return [
                'status' => 'unconfigured_channel',
                'error' => 'Subscriber has no verified WhatsApp phone number',
            ];
        }

        try {
            $response = Http::withToken($this->getToken())
                ->timeout(8)
                ->post("https://graph.facebook.com/v21.0/{$this->getPhoneNumberId()}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $phone,
                    'type' => 'text',
                    'text' => [
                        'preview_url' => true,
                        'body' => "[NEXOVANT INTEL] {$notification->title}\n\n{$notification->message}\n\n" . ($notification->link ?? ''),
                    ],
                ]);

            if ($response->successful()) {
                $log = NotificationDeliveryLog::create([
                    'notification_id' => $notification->id,
                    'channel' => 'whatsapp',
                    'status' => 'delivered',
                    'attempt_count' => 1,
                    'recipient' => $phone,
                    'sent_at' => now(),
                ]);

                return [
                    'status' => 'delivered',
                    'message_id' => $log->id,
                ];
            }

            $errorMsg = $response->json('error.message') ?? 'WhatsApp API error (' . $response->status() . ')';
            $log = NotificationDeliveryLog::create([
                'notification_id' => $notification->id,
                'channel' => 'whatsapp',
                'status' => 'failed',
                'attempt_count' => 1,
                'recipient' => $phone,
                'error_message' => $errorMsg,
                'sent_at' => now(),
            ]);

            return [
                'status' => 'failed',
                'error' => $errorMsg,
            ];
        } catch (\Throwable $e) {
            Log::warning('WhatsApp Cloud API exception', ['error' => $e->getMessage()]);

            $log = NotificationDeliveryLog::create([
                'notification_id' => $notification->id,
                'channel' => 'whatsapp',
                'status' => 'failed',
                'attempt_count' => 1,
                'recipient' => $phone,
                'error_message' => $e->getMessage(),
                'sent_at' => now(),
            ]);

            return [
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
        }
    }
}
