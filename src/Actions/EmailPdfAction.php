<?php

namespace AmjadIqbal\FilamentRecordPdf\Actions;

use AmjadIqbal\FilamentRecordPdf\Concerns\InteractsWithRecordPdf;
use AmjadIqbal\FilamentRecordPdf\Mail\RecordPdfMail;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;

class EmailPdfAction extends Action
{
    use InteractsWithRecordPdf;

    protected string|Closure|null $defaultRecipient = null;

    protected string|Closure|null $defaultSubject = null;

    protected string|Closure|null $defaultMessage = null;

    protected bool|Closure|null $queueMail = null;

    protected ?Closure $sendUsing = null;

    public static function getDefaultName(): ?string
    {
        return 'emailPdf';
    }

    protected function getDefaultPdfAbilities(): array
    {
        return ['emailPdf', 'view'];
    }

    public function defaultRecipient(string|Closure|null $email): static
    {
        $this->defaultRecipient = $email;

        return $this;
    }

    public function defaultSubject(string|Closure|null $subject): static
    {
        $this->defaultSubject = $subject;

        return $this;
    }

    public function defaultMessage(string|Closure|null $message): static
    {
        $this->defaultMessage = $message;

        return $this;
    }

    public function queueMail(bool|Closure $condition = true): static
    {
        $this->queueMail = $condition;

        return $this;
    }

    /**
     * Send it yourself: fn (array $recipients, RecordPdfMail $mail, Model $record, array $data).
     */
    public function sendUsing(?Closure $callback): static
    {
        $this->sendUsing = $callback;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public static function parseRecipients(?string $value): array
    {
        return array_values(array_unique(array_filter(array_map('trim', preg_split('/[,;\s]+/', (string) $value) ?: []))));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('filament-record-pdf::record-pdf.email.label'));

        $this->icon(Heroicon::OutlinedEnvelope);

        $this->authorize(fn (?Model $record): bool => $record === null || $this->canAccessRecordPdf($record));

        $this->modalHeading(fn (Model $record): string => __('filament-record-pdf::record-pdf.email.heading', ['file' => $this->getPdfFilename($record)]));

        $this->modalSubmitActionLabel(__('filament-record-pdf::record-pdf.email.submit'));

        $this->schema([
            TextInput::make('to')
                ->label(__('filament-record-pdf::record-pdf.email.to'))
                ->helperText(__('filament-record-pdf::record-pdf.email.to_help'))
                ->required()
                ->maxLength(1000)
                ->default(fn (Model $record): ?string => $this->evaluateForRecord($this->defaultRecipient, $record))
                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    $emails = static::parseRecipients((string) $value);

                    if ($emails === [] || count($emails) > 10) {
                        $fail(__('filament-record-pdf::record-pdf.email.to_count'));

                        return;
                    }

                    foreach ($emails as $email) {
                        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                            $fail(__('filament-record-pdf::record-pdf.email.to_invalid', ['email' => $email]));

                            return;
                        }
                    }
                }),
            TextInput::make('subject')
                ->label(__('filament-record-pdf::record-pdf.email.subject'))
                ->required()
                ->maxLength(200)
                ->default(fn (Model $record): string => (string) ($this->evaluateForRecord($this->defaultSubject, $record)
                    ?? $this->getPdfFilename($record))),
            Textarea::make('message')
                ->label(__('filament-record-pdf::record-pdf.email.message'))
                ->rows(4)
                ->maxLength(5000)
                ->default(fn (Model $record): ?string => $this->evaluateForRecord($this->defaultMessage, $record)),
        ]);

        $this->action(function (Model $record, array $data): void {
            $recipients = static::parseRecipients($data['to'] ?? '');
            $filename = $this->getPdfFilename($record);

            $mail = new RecordPdfMail(
                subjectLine: (string) $data['subject'],
                body: (string) ($data['message'] ?? ''),
                pdfBase64: base64_encode($this->renderPdf($record)),
                filename: $filename,
            );

            if ($this->sendUsing) {
                $this->evaluateForRecord($this->sendUsing, $record, ['recipients' => $recipients, 'mail' => $mail, 'data' => $data]);
            } elseif ($this->evaluate($this->queueMail) ?? config('filament-record-pdf.mail.queue', false)) {
                Mail::to($recipients)->queue($mail);
            } else {
                Mail::to($recipients)->send($mail);
            }

            Notification::make()
                ->success()
                ->title(__('filament-record-pdf::record-pdf.email.sent', ['file' => $filename, 'count' => count($recipients)]))
                ->send();
        });
    }
}
