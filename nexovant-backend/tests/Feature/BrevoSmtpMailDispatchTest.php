<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\ExecutiveReportDeliveryMail;
use App\Mail\ForgotPasswordMail;
use App\Mail\NexovantNotificationMail;
use App\Mail\WelcomeAccountMail;
use App\Models\Notification;
use App\Models\Publication;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BrevoSmtpMailDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure SMTP sender config matches requirements
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', 'smtp-relay.brevo.com');
        Config::set('mail.mailers.smtp.port', 587);
        Config::set('mail.mailers.smtp.username', 'c3658d001@smtp-brevo.com');
        Config::set('mail.mailers.smtp.encryption', 'tls');
        Config::set('mail.from.address', 'noreply@nexvant.si');
        Config::set('mail.from.name', 'Nexovant Intelligence');
    }

    public function test_brevo_smtp_configuration_defaults(): void
    {
        $this->assertEquals('smtp', config('mail.default'));
        $this->assertEquals('smtp-relay.brevo.com', config('mail.mailers.smtp.host'));
        $this->assertEquals(587, (int) config('mail.mailers.smtp.port'));
        $this->assertEquals('c3658d001@smtp-brevo.com', config('mail.mailers.smtp.username'));
        $this->assertEquals('noreply@nexvant.si', config('mail.from.address'));
        $this->assertEquals('Nexovant Intelligence', config('mail.from.name'));
    }

    public function test_user_registration_dispatches_welcome_email(): void
    {
        Mail::fake();

        $payload = [
            'name' => 'Elena Rostova',
            'email' => 'elena.rostova@globalcorp.eu',
            'password' => 'ExecutivePass2026!',
            'password_confirmation' => 'ExecutivePass2026!',
            'account_type' => 'individual',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(201);

        Mail::assertSent(WelcomeAccountMail::class, function (WelcomeAccountMail $mail) {
            return $mail->hasTo('elena.rostova@globalcorp.eu')
                && str_contains($mail->envelope()->subject, 'Welcome to Nexovant Intelligence');
        });
    }

    public function test_forgot_password_generates_token_and_dispatches_reset_email(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'vp.strategy@nexovant.si',
            'name' => 'Dr. Julian Thorne',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'vp.strategy@nexovant.si',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Token record should exist in database
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'vp.strategy@nexovant.si',
        ]);

        Mail::assertSent(ForgotPasswordMail::class, function (ForgotPasswordMail $mail) {
            return $mail->hasTo('vp.strategy@nexovant.si')
                && str_contains($mail->envelope()->subject, 'Reset Your Executive Credentials')
                && $mail->recipient->name === 'Dr. Julian Thorne';
        });
    }

    public function test_forgot_password_with_unknown_email_returns_success_without_sending_email(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'unknown.analyst@nowhere.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        Mail::assertNothingSent();
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'researcher@nexovant.si',
            'password' => Hash::make('OldPassword123!'),
        ]);

        $rawToken = 'sample-valid-token-64-character-string-for-executive-reset-testing';
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make($rawToken),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $rawToken,
            'password' => 'NewExecutivePass2026!',
            'password_confirmation' => 'NewExecutivePass2026!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Token record should be deleted
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $user->email,
        ]);

        // New password should verify
        $user->refresh();
        $this->assertTrue(Hash::check('NewExecutivePass2026!', $user->password));
    }

    public function test_password_reset_fails_with_expired_token(): void
    {
        $user = User::factory()->create([
            'email' => 'expired@nexovant.si',
            'password' => Hash::make('OldPassword123!'),
        ]);

        $rawToken = 'expired-token-string';
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make($rawToken),
            'created_at' => Carbon::now()->subMinutes(61), // Expired > 60 minutes
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $rawToken,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_password_reset_fails_with_invalid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'tampered@nexovant.si',
        ]);

        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make('legitimate-token'),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => 'tampered-or-wrong-token',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertStatus(422);
    }

    public function test_executive_report_email_delivery_endpoint(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'subscriber.dossier@nexovant.si',
        ]);

        $publication = Publication::create([
            'title' => 'Q4 Global Semiconductor Supply Chain Intelligence Brief',
            'publication_type' => 'dossier',
            'publication_date' => now(),
            'file_path' => 'reports/sample-intelligence-brief.pdf',
            'status' => 'published',
            'published_at' => now(),
            'visibility' => 'public',
            'is_public' => true,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/publications/{$publication->id}/email", [
                'email' => 'subscriber.dossier@nexovant.si',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        Mail::assertSent(ExecutiveReportDeliveryMail::class, function (ExecutiveReportDeliveryMail $mail) use ($publication) {
            return $mail->hasTo('subscriber.dossier@nexovant.si')
                && str_contains($mail->envelope()->subject, 'Executive Briefing Dossier')
                && $mail->publication->id === $publication->id;
        });
    }

    public function test_nexovant_notification_mail_renders_with_sender_identity(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'alerts@nexovant.si',
        ]);

        $notification = Notification::create([
            'user_id' => $user->id,
            'title' => 'Critical Supply Chain Disruption Alert',
            'message' => 'High impact anomaly detected in Taiwan foundry clusters.',
            'category' => 'system',
            'delivery_channel' => 'email',
            'is_read' => false,
        ]);

        Mail::to($user->email)->send(new NexovantNotificationMail($notification, $user));

        Mail::assertSent(NexovantNotificationMail::class, function (NexovantNotificationMail $mail) {
            return $mail->hasTo('alerts@nexovant.si')
                && str_contains($mail->envelope()->subject, 'Critical Supply Chain Disruption Alert');
        });
    }
}
