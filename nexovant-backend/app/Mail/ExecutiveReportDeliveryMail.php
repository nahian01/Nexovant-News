<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Publication;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

class ExecutiveReportDeliveryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User|string $recipient,
        public readonly Publication $publication,
        public readonly string $downloadUrl,
        public readonly ?string $attachmentPath = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Executive Briefing Dossier: ' . $this->publication->title . ' — Nexovant',
        );
    }

    public function content(): Content
    {
        $appUrl = rtrim((string) Config::get('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/');
        $recipientName = is_string($this->recipient) ? 'Executive Subscriber' : $this->recipient->name;
        $recipientEmail = is_string($this->recipient) ? $this->recipient : $this->recipient->email;

        return new Content(
            view: 'emails.executive_report',
            with: [
                'recipientName' => $recipientName,
                'recipientEmail' => $recipientEmail,
                'publication' => $this->publication,
                'title' => $this->publication->title,
                'summary' => $this->publication->summary,
                'seriesName' => $this->publication->series_name ?? 'Strategic Briefing',
                'publicationDate' => $this->publication->publication_date?->format('F d, Y') ?? now()->format('F d, Y'),
                'visibility' => strtoupper($this->publication->visibility ?? 'REGISTERED'),
                'pageCount' => $this->publication->page_count ?? 1,
                'downloadUrl' => $this->downloadUrl,
                'appUrl' => $appUrl,
            ],
        );
    }

    /**
     * Attach PDF file if available on disk.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        if (!empty($this->attachmentPath) && Storage::disk('local')->exists($this->attachmentPath)) {
            $absPath = Storage::disk('local')->path($this->attachmentPath);
            if (file_exists($absPath) && filesize($absPath) < 15 * 1024 * 1024) { // Under 15MB
                $attachments[] = Attachment::fromPath($absPath)
                    ->as(basename($this->attachmentPath))
                    ->withMime('application/pdf');
            }
        }

        return $attachments;
    }

    public function headers(): Headers
    {
        return new Headers(
            text: [
                'X-Nexovant-Mail-Type' => 'executive-report',
                'X-Nexovant-Publication-ID' => $this->publication->id,
            ],
        );
    }
}
