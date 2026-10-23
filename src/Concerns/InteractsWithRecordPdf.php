<?php

namespace AmjadIqbal\FilamentRecordPdf\Concerns;

use AmjadIqbal\FilamentRecordPdf\RecordPdf;
use AmjadIqbal\FilamentRecordPdf\Support\Authorizer;
use AmjadIqbal\FilamentRecordPdf\Support\PdfRenderOptions;
use AmjadIqbal\FilamentRecordPdf\Support\SafePath;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\LaravelPdf\PdfBuilder;

/**
 * What every Record PDF action shares: which Blade view renders a record, the data it gets,
 * the file name, paper settings, the driver, and the policy abilities that guard it.
 */
trait InteractsWithRecordPdf
{
    protected string|Closure|null $pdfView = null;

    /** @var array<string, mixed>|Closure */
    protected array|Closure $pdfData = [];

    protected string|Closure|null $pdfFilename = null;

    protected string|Closure|null $pdfFormat = null;

    protected bool|Closure|null $pdfLandscape = null;

    protected string|Closure|null $pdfDriver = null;

    protected ?Closure $modifyPdfUsing = null;

    /** @var array<string>|null */
    protected ?array $pdfAbilities = null;

    protected ?Closure $authorizePdfUsing = null;

    /**
     * The Blade view that renders one record. It receives $record plus anything from pdfData().
     */
    public function pdfView(string|Closure|null $view): static
    {
        $this->pdfView = $view;

        return $this;
    }

    /**
     * @param  array<string, mixed>|Closure  $data
     */
    public function pdfData(array|Closure $data): static
    {
        $this->pdfData = $data;

        return $this;
    }

    /**
     * The download name, without a directory. It is cleaned to [a-z0-9-] and ".pdf" is added.
     */
    public function pdfFilename(string|Closure|null $filename): static
    {
        $this->pdfFilename = $filename;

        return $this;
    }

    public function pdfFormat(string|Closure|null $format): static
    {
        $this->pdfFormat = $format;

        return $this;
    }

    public function pdfLandscape(bool|Closure $condition = true): static
    {
        $this->pdfLandscape = $condition;

        return $this;
    }

    public function pdfDriver(string|Closure|null $driver): static
    {
        $this->pdfDriver = $driver;

        return $this;
    }

    /**
     * Change the spatie/laravel-pdf builder before rendering: margins, metadata, headers, Browsershot options.
     *
     * @param  Closure(PdfBuilder, Model): mixed  $callback
     */
    public function modifyPdfUsing(?Closure $callback): static
    {
        $this->modifyPdfUsing = $callback;

        return $this;
    }

    /**
     * Policy abilities checked per record, in order. The first one the policy defines decides.
     *
     * @param  string|array<string>  $abilities
     */
    public function pdfAbility(string|array $abilities): static
    {
        $this->pdfAbilities = (array) $abilities;

        return $this;
    }

    /**
     * Replace the policy check with your own: fn (Model $record, ?Authenticatable $user): bool.
     */
    public function authorizePdfUsing(?Closure $callback): static
    {
        $this->authorizePdfUsing = $callback;

        return $this;
    }

    /**
     * @return array<string>
     */
    protected function getDefaultPdfAbilities(): array
    {
        return ['downloadPdf', 'view'];
    }

    /**
     * @return array<string>
     */
    public function getPdfAbilities(): array
    {
        return $this->pdfAbilities ?? $this->getDefaultPdfAbilities();
    }

    public function canAccessRecordPdf(Model $record): bool
    {
        $user = Authorizer::user();

        if ($this->authorizePdfUsing) {
            return (bool) $this->evaluateForRecord($this->authorizePdfUsing, $record, ['user' => $user]);
        }

        return Authorizer::allows($record, $this->getPdfAbilities(), $user);
    }

    public function getPdfView(Model $record): string
    {
        return (string) ($this->evaluateForRecord($this->pdfView, $record)
            ?? config('filament-record-pdf.default_view', 'filament-record-pdf::default'));
    }

    /**
     * @return array<string, mixed>
     */
    public function getPdfData(Model $record): array
    {
        return array_merge(['record' => $record], (array) $this->evaluateForRecord($this->pdfData, $record));
    }

    public function getPdfFilename(Model $record): string
    {
        $name = $this->evaluateForRecord($this->pdfFilename, $record)
            ?? Str::kebab(class_basename($record)).'-'.$record->getKey();

        return SafePath::filename((string) $name);
    }

    public function getPdfRenderOptions(Model $record): PdfRenderOptions
    {
        $defaults = PdfRenderOptions::defaults();

        return new PdfRenderOptions(
            driver: (string) ($this->evaluateForRecord($this->pdfDriver, $record) ?? $defaults->driver),
            format: (string) ($this->evaluateForRecord($this->pdfFormat, $record) ?? $defaults->format),
            landscape: (bool) ($this->evaluateForRecord($this->pdfLandscape, $record) ?? $defaults->landscape),
        );
    }

    public function renderPdfHtml(Model $record): string
    {
        return view($this->getPdfView($record), $this->getPdfData($record))->render();
    }

    public function hasPdfModifier(): bool
    {
        return $this->modifyPdfUsing !== null;
    }

    /**
     * The PDF bytes for one record.
     */
    public function renderPdf(Model $record): string
    {
        $modify = $this->modifyPdfUsing
            ? fn (PdfBuilder $pdf) => $this->evaluateForRecord($this->modifyPdfUsing, $record, ['pdf' => $pdf], [PdfBuilder::class => $pdf])
            : null;

        return RecordPdf::render($this->renderPdfHtml($record), $this->getPdfRenderOptions($record), $modify);
    }

    /**
     * Evaluate a configured value for an explicit record (bulk actions have many).
     *
     * @param  array<string, mixed>  $named
     * @param  array<string, mixed>  $typed
     */
    protected function evaluateForRecord(mixed $value, Model $record, array $named = [], array $typed = []): mixed
    {
        return $this->evaluate(
            $value,
            namedInjections: ['record' => $record, ...$named],
            typedInjections: [Model::class => $record, $record::class => $record, ...$typed],
        );
    }
}
