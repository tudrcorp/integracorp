<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\FiscalBooks\Pages;

use App\Enums\FiscalBook;
use App\Filament\Administration\Resources\FiscalBooks\FiscalBookResource;
use App\Filament\Administration\Resources\FiscalBooks\Schemas\FiscalBookEntryForm;
use App\Models\FiscalBookPeriod;
use App\Support\Administration\FiscalBooks\FiscalBookEntryWriter;
use App\Support\Administration\FiscalBooks\FiscalBookLedger;
use App\Support\Administration\FiscalBooks\FiscalBookPeriods;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\Url;

/**
 * Página de un libro fiscal: un mes a la vez.
 *
 * El mes vive en la URL (`?mes=2026-10`), así se puede compartir el enlace y el
 * botón «atrás» funciona. Cambiar de mes es una sola consulta de totales más la
 * página de la tabla.
 */
abstract class ListFiscalBookEntries extends ListRecords
{
    #[Url(as: 'mes')]
    public ?string $month = null;

    private ?FiscalBookPeriod $periodRecord = null;

    private bool $periodRecordLoaded = false;

    /**
     * @return class-string<FiscalBookResource>
     */
    abstract protected static function bookResource(): string;

    public function book(): FiscalBook
    {
        return static::bookResource()::book();
    }

    public function mount(): void
    {
        parent::mount();

        $this->month = $this->periodDate()->format('Y-m');
    }

    public function updatedMonth(): void
    {
        $this->month = $this->periodDate()->format('Y-m');
        $this->periodRecordLoaded = false;
        FiscalBookLedger::flush();
        $this->resetPage();
    }

    public function periodDate(): CarbonImmutable
    {
        return FiscalBookPeriods::parse($this->month);
    }

    public function periodRecord(): ?FiscalBookPeriod
    {
        if (! $this->periodRecordLoaded) {
            $this->periodRecord = FiscalBookPeriods::find($this->book(), $this->periodDate());
            $this->periodRecordLoaded = true;
        }

        return $this->periodRecord;
    }

    public function isClosed(): bool
    {
        return (bool) $this->periodRecord()?->isClosed();
    }

    public function getTitle(): string|Htmlable
    {
        return $this->book()->label();
    }

    public function getSubheading(): string|Htmlable|null
    {
        return FiscalBookPeriods::label($this->periodDate()).' · montos en bolívares';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.administration.fiscal-books.header')
                ->viewData(fn (): array => $this->headerData()),
            EmbeddedTable::make(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function headerData(): array
    {
        $book = $this->book();
        $period = $this->periodDate();
        $totals = FiscalBookLedger::totals($book, $period);

        return [
            'book' => $book,
            'monthOptions' => FiscalBookPeriods::options(),
            'periodLabel' => FiscalBookPeriods::label($period),
            'record' => $this->periodRecord(),
            'closed' => $this->isClosed(),
            'totals' => $totals,
            'summary' => FiscalBookLedger::summaryLines($book, $totals),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->registerAction(),
            ...$this->sourceActions(),
            ActionGroup::make([
                Action::make('downloadExcel')
                    ->label('Excel (.xlsx)')
                    ->icon(Heroicon::OutlinedTableCells)
                    ->url(fn (): string => $this->downloadUrl('xlsx'), shouldOpenInNewTab: true),
                Action::make('downloadPdf')
                    ->label('PDF')
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->url(fn (): string => $this->downloadUrl('pdf'), shouldOpenInNewTab: true),
            ])
                ->label('Descargar')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->button()
                ->color('gray'),
            $this->closeAction(),
            $this->reopenAction(),
        ];
    }

    /**
     * @return list<Action>
     */
    abstract protected function sourceActions(): array;

    protected function downloadUrl(string $format): string
    {
        return route('administration.fiscal-books.download', [
            'book' => $this->book()->slug(),
            'period' => $this->periodDate()->format('Y-m'),
            'format' => $format,
        ]);
    }

    protected function registerAction(): Action
    {
        return Action::make('registerDocument')
            ->label('Registrar documento')
            ->icon(Heroicon::OutlinedPlus)
            ->visible(fn (): bool => ! $this->isClosed())
            ->modalHeading(fn (): string => 'Registrar documento en el '.mb_strtolower($this->book()->label()).' de '.mb_strtolower(FiscalBookPeriods::label($this->periodDate())))
            ->modalWidth('5xl')
            ->modalSubmitActionLabel('Registrar')
            ->fillForm(fn (): array => [
                'document_date' => $this->defaultDocumentDate(),
            ])
            ->schema(fn (): array => FiscalBookEntryForm::components($this->book()))
            ->action(function (array $data): void {
                try {
                    FiscalBookEntryWriter::create($this->book(), [...$data, 'period' => $this->periodDate()->format('Y-m')], Auth::user());
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title('No se registró')->body($exception->getMessage())->danger()->send();

                    throw new Halt;
                }

                Notification::make()->title('Documento registrado')->success()->send();
            });
    }

    protected function closeAction(): Action
    {
        return Action::make('closePeriod')
            ->label('Cerrar mes')
            ->icon(Heroicon::OutlinedLockClosed)
            ->color('warning')
            ->visible(fn (): bool => ! $this->isClosed() && FiscalBookPeriods::userCanCloseAndReopen())
            ->requiresConfirmation()
            ->modalHeading(fn (): string => 'Cerrar el '.mb_strtolower($this->book()->label()).' de '.mb_strtolower(FiscalBookPeriods::label($this->periodDate())))
            ->modalDescription('Úselo cuando el mes ya se declaró. Nadie podrá agregar, editar, anular ni quitar documentos de este mes, y Ventas tampoco le agregará facturas. Para corregirlo habrá que reabrirlo con una razón.')
            ->modalSubmitActionLabel('Cerrar mes')
            ->action(function (): void {
                try {
                    FiscalBookPeriods::close($this->book(), $this->periodDate(), Auth::user());
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title('No se cerró')->body($exception->getMessage())->danger()->send();

                    return;
                }

                $this->periodRecordLoaded = false;
                Notification::make()->title('Mes cerrado')->success()->send();
            });
    }

    protected function reopenAction(): Action
    {
        return Action::make('reopenPeriod')
            ->label('Reabrir mes')
            ->icon(Heroicon::OutlinedLockOpen)
            ->color('danger')
            ->visible(fn (): bool => $this->isClosed() && FiscalBookPeriods::userCanCloseAndReopen())
            ->modalHeading(fn (): string => 'Reabrir el '.mb_strtolower($this->book()->label()).' de '.mb_strtolower(FiscalBookPeriods::label($this->periodDate())))
            ->modalDescription('El mes vuelve a aceptar cambios. Queda registrado quién lo reabrió y por qué.')
            ->modalIcon(Heroicon::OutlinedExclamationTriangle)
            ->modalSubmitActionLabel('Reabrir mes')
            ->schema([
                Textarea::make('reason')
                    ->label('Razón')
                    ->required()
                    ->minLength(FiscalBookPeriods::MIN_REASON_LENGTH)
                    ->maxLength(1000)
                    ->rows(3),
            ])
            ->action(function (array $data): void {
                try {
                    FiscalBookPeriods::reopen($this->book(), $this->periodDate(), (string) $data['reason'], Auth::user());
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title('No se reabrió')->body($exception->getMessage())->danger()->send();

                    throw new Halt;
                }

                $this->periodRecordLoaded = false;
                Notification::make()->title('Mes reabierto')->success()->send();
            });
    }

    private function defaultDocumentDate(): string
    {
        $period = $this->periodDate();
        $today = CarbonImmutable::today();

        return $today->format('Y-m') === $period->format('Y-m') ? $today->toDateString() : $period->endOfMonth()->toDateString();
    }
}
