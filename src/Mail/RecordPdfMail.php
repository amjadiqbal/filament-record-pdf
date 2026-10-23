<?php

namespace AmjadIqbal\FilamentRecordPdf\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The PDF travels as a base64 string so the message can be queued.
 */
class RecordPdfMail extends Mailable
{
    use Queueable;

    public function __construct(
        public string $subjectLine,
        public string $body,
        public string $pdfBase64,
        public string $filename,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            view: 'filament-record-pdf::mail',
            text: 'filament-record-pdf::mail-text',
            with: ['body' => $this->body, 'filename' => $this->filename],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => (string) base64_decode($this->pdfBase64, true), $this->filename)
                ->withMime('application/pdf'),
        ];
    }
}
