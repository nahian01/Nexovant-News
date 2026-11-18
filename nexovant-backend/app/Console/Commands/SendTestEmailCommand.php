<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\ExecutiveReportDeliveryMail;
use App\Mail\ForgotPasswordMail;
use App\Mail\NexovantNotificationMail;
use App\Mail\WelcomeAccountMail;
use App\Models\Notification;
use App\Models\Publication;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendTestEmailCommand extends Command
{
    protected $signature = 'nexovant:send-test-email 
                            {email : Destination email address}
                            {--type=welcome : Email type: welcome, forgot, report, notification, or all}';

    protected $description = 'Dispatch a verified test email via Brevo SMTP (smtp-relay.brevo.com:587) to verify delivery';

    public function handle(): int
    {
        $recipientEmail = $this->argument('email');
        $type = strtolower($this->option('type') ?? 'welcome');

        $this->info("=================================================");
        $this->info("   NEXOVANT BREVO SMTP RELAY DIAGNOSTIC TEST     ");
        $this->info("=================================================");
        $this->line("Mailer Driver  : " . Config::get('mail.default'));
        $this->line("SMTP Host      : " . Config::get('mail.mailers.smtp.host'));
        $this->line("SMTP Port      : " . Config::get('mail.mailers.smtp.port'));
        $this->line("SMTP User      : " . Config::get('mail.mailers.smtp.username'));
        $this->line("From Address   : " . Config::get('mail.from.address'));
        $this->line("Target Email   : {$recipientEmail}");
        $this->line("Template Type  : {$type}");
        $this->info("-------------------------------------------------");

        // Find or mock user
        $user = User::where('email', $recipientEmail)->first() ?? new User([
            'id' => (string) Str::uuid(),
            'name' => 'Executive Test Recipient',
            'email' => $recipientEmail,
            'account_type' => 'individual',
        ]);

        $dispatched = 0;

        try {
            if ($type === 'welcome' || $type === 'all') {
                $this->comment("Dispatching Welcome / Account Creation Email...");
                Mail::to($recipientEmail)->send(new WelcomeAccountMail($user, 'TempPass-2026!'));
                $this->info("✓ Welcome email sent successfully.");
                $dispatched++;
            }

            if ($type === 'forgot' || $type === 'all') {
                $this->comment("Dispatching Forgot Password Reset Email...");
                $token = Str::random(64);
                Mail::to($recipientEmail)->send(new ForgotPasswordMail($user, $token));
                $this->info("✓ Password reset email sent successfully.");
                $dispatched++;
            }

            if ($type === 'report' || $type === 'all') {
                $this->comment("Dispatching Executive Dossier Report Email...");
                $publication = Publication::first() ?? new Publication([
                    'id' => (string) Str::uuid(),
                    'title' => 'Q4 Global Semiconductor Sovereign Foundry Synthesis',
                    'slug' => 'q4-semiconductor-synthesis',
                    'summary' => 'Comprehensive multi-source analysis of high-NA lithography investments across EMEA and Asia.',
                    'visibility' => 'premium',
                    'publication_date' => now(),
                ]);
                $downloadUrl = Config::get('app.url') . "/api/v1/publications/{$publication->id}/download?token=test-token";
                Mail::to($recipientEmail)->send(new ExecutiveReportDeliveryMail($user, $publication, $downloadUrl));
                $this->info("✓ Executive report email sent successfully.");
                $dispatched++;
            }

            if ($type === 'notification' || $type === 'all') {
                $this->comment("Dispatching Breaking Intelligence Notification Email...");
                $notification = new Notification([
                    'id' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'title' => 'Breaking: Strategic Silicon Supply Realignment',
                    'message' => 'New export licensing restrictions filed for EUV foundry components.',
                    'priority' => 'urgent',
                    'category' => 'regulatory_legal',
                    'link' => '/news/silicon-realignment',
                ]);
                Mail::to($recipientEmail)->send(new NexovantNotificationMail($notification, $user));
                $this->info("✓ Breaking notification email sent successfully.");
                $dispatched++;
            }

            $this->info("-------------------------------------------------");
            $this->info("SUCCESS: All {$dispatched} test email(s) dispatched through Brevo SMTP.");
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("FAILED TO SEND EMAIL VIA BREVO SMTP RELAY:");
            $this->error($e->getMessage());
            $this->line("");
            $this->warn("Note: If authentication failed, ensure MAIL_PASSWORD in .env contains your valid Brevo SMTP Master Key.");
            return Command::FAILURE;
        }
    }
}
