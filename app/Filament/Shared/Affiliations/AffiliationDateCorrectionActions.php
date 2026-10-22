<?php

declare(strict_types=1);

namespace App\Filament\Shared\Affiliations;

use App\Models\AffiliationDateCorrection;
use App\Models\User;
use App\Support\Affiliations\DateCorrection\AffiliationDateCorrectionPreview;
use App\Support\Affiliations\DateCorrection\AffiliationDateCorrectionRollback;
use App\Support\Affiliations\DateCorrection\AffiliationDateCorrector;
use App\Support\Affiliations\DateCorrection\DateCorrectionSubject;
use App\Support\Collections\CollectionDueDate;
use App\Support\Filament\BusinessFilamentActionAccess;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use RuntimeException;
use Throwable;

/**
 * «Corregir emisión y vigencia» y su reversión, para afiliaciones individuales y corporativas,
 * en Negocios y Administración. Es la única vía para cambiar esas fechas una vez emitida la
 * póliza: en las tablas quedan de solo lectura. Ambas exigen el permiso propio de cada tipo.
 */
final class AffiliationDateCorrectionActions
{
    public static function correct(): Action
    {
        return Action::make('correctEffectiveDate')
            ->label('Corregir emisión y vigencia')
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color('warning')
            ->modalHeading('Corregir fecha de emisión y vigencia')
            ->modalDescription('Mueve la fecha y todo lo que se calculó con ella: avisos POR PAGAR, renovación y fechas informativas del pago. Los avisos pagados y los montos no cambian. Queda un punto de restauración.')
            ->modalIcon(Heroicon::OutlinedCalendarDays)
            ->modalSubmitActionLabel('Corregir ahora')
            ->modalCancelActionLabel('Cancelar')
            ->modalWidth(Width::FourExtraLarge)
            ->visible(fn (Model $record): bool => self::allowed($record)
                && strtoupper(trim((string) $record->status)) === AffiliationDateCorrector::ACTIVE_STATUS)
            ->authorize(fn (Model $record): bool => self::allowed($record))
            ->fillForm(fn (Model $record): array => [
                'new_date' => AffiliationDateCorrector::plan($record, null)['before']['cycle_start'] ?? CollectionDueDate::parse($record->activated_at)?->format('d/m/Y'),
                'reason' => '',
                'confirmed' => false,
            ])
            ->schema([
                DatePicker::make('new_date')
                    ->label('Fecha correcta')
                    ->helperText('Póliza sin renovaciones: la fecha de emisión (si ya va por el segundo año, la vigencia se calcula sola). Póliza renovada: el inicio del año actual; la emisión original no cambia.')
                    ->required()
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->format('d/m/Y')
                    ->closeOnDateSelection()
                    ->live(),
                Placeholder::make('preview')
                    ->label('Vista previa (sin escribir todavía)')
                    ->content(function (Get $get, Model $record): HtmlString {
                        if (blank($get('new_date'))) {
                            return new HtmlString('<p class="text-sm text-gray-600 dark:text-gray-400">Elija la fecha correcta para ver qué cambia.</p>');
                        }

                        try {
                            return AffiliationDateCorrectionPreview::html(AffiliationDateCorrector::plan($record, $get('new_date')));
                        } catch (Throwable $exception) {
                            report($exception);

                            return new HtmlString('<p class="text-sm text-red-700 dark:text-red-300">No se pudo armar la vista previa: '.e($exception->getMessage()).'</p>');
                        }
                    })
                    ->columnSpanFull(),
                Textarea::make('reason')
                    ->label('Motivo de la corrección')
                    ->required()
                    ->minLength(AffiliationDateCorrector::REASON_MIN_LENGTH)
                    ->maxLength(2000)
                    ->rows(3)
                    ->placeholder('Ejemplo: la póliza se emitió el 26/08/2026 según contrato; se cargó por error el 20/07/2026.')
                    ->columnSpanFull(),
                Checkbox::make('confirmed')
                    ->label('Revisé la vista previa. Entiendo que se reprograman los avisos POR PAGAR y la renovación, y que se puede revertir mientras nadie cambie esos datos.')
                    ->accepted()
                    ->required()
                    ->columnSpanFull(),
            ])
            ->action(function (Model $record, array $data): void {
                $user = Auth::user();

                if (! $user instanceof User || ! self::allowed($record)) {
                    return;
                }

                try {
                    $correction = AffiliationDateCorrector::apply($record, $data['new_date'] ?? null, (string) ($data['reason'] ?? ''), $user);
                } catch (RuntimeException $exception) {
                    self::failed('No se corrigió la fecha', $exception->getMessage());

                    return;
                } catch (Throwable $exception) {
                    report($exception);
                    self::failed('No se corrigió la fecha', 'Ocurrió un error inesperado y se deshizo todo.');

                    return;
                }

                $summary = (array) $correction->summary;

                Notification::make()
                    ->title('Fecha corregida')
                    ->body('Nuevo inicio del ciclo: '.$correction->new_date.'. Renovación: '.($summary['renewal_after'] ?? '—')
                        .'. Avisos reprogramados: '.count((array) ($summary['collections'] ?? []))
                        .'. Si algo no cuadra, use «Revertir corrección de vigencia».')
                    ->success()
                    ->persistent()
                    ->send();

                $record->refresh();
            });
    }

    public static function revert(): Action
    {
        return Action::make('revertEffectiveDateCorrection')
            ->label('Revertir corrección de vigencia')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->modalHeading('Revertir corrección de vigencia')
            ->modalDescription('La afiliación, los avisos, la renovación y las fechas del pago vuelven a como estaban antes de corregir. Es todo o nada.')
            ->modalSubmitActionLabel('Revertir ahora')
            ->modalCancelActionLabel('Cancelar')
            ->modalWidth(Width::ThreeExtraLarge)
            ->visible(fn (Model $record): bool => self::allowed($record)
                && AffiliationDateCorrectionRollback::latestAppliedFor($record) instanceof AffiliationDateCorrection)
            ->authorize(fn (Model $record): bool => self::allowed($record))
            ->schema([
                Placeholder::make('rollback_preview')
                    ->hiddenLabel()
                    ->content(function (Model $record): HtmlString {
                        $correction = AffiliationDateCorrectionRollback::latestAppliedFor($record);

                        return AffiliationDateCorrectionPreview::rollbackHtml(
                            $correction,
                            $correction instanceof AffiliationDateCorrection ? AffiliationDateCorrectionRollback::blockers($correction) : [],
                        );
                    })
                    ->columnSpanFull(),
                Textarea::make('note')
                    ->label('Motivo de la reversión')
                    ->required()
                    ->minLength(AffiliationDateCorrectionRollback::NOTE_MIN_LENGTH)
                    ->maxLength(2000)
                    ->rows(3)
                    ->columnSpanFull(),
                Checkbox::make('confirmed')
                    ->label('Entiendo que las fechas vuelven a las anteriores, incluidos los avisos POR PAGAR.')
                    ->accepted()
                    ->required()
                    ->columnSpanFull(),
            ])
            ->action(function (Model $record, array $data): void {
                $user = Auth::user();
                $correction = AffiliationDateCorrectionRollback::latestAppliedFor($record);

                if (! $user instanceof User || ! self::allowed($record) || ! $correction instanceof AffiliationDateCorrection) {
                    return;
                }

                try {
                    AffiliationDateCorrectionRollback::execute($correction, $user, (string) ($data['note'] ?? ''));
                } catch (RuntimeException $exception) {
                    self::failed('No se revirtió la corrección', $exception->getMessage());

                    return;
                } catch (Throwable $exception) {
                    report($exception);
                    self::failed('No se revirtió la corrección', 'Ocurrió un error inesperado y se deshizo todo.');

                    return;
                }

                Notification::make()
                    ->title('Corrección revertida')
                    ->body('Las fechas volvieron a: emisión '.($correction->activated_at_before ?? '—').', vigencia '.($correction->effective_date_before ?? '—').'.')
                    ->success()
                    ->persistent()
                    ->send();

                $record->refresh();
            });
    }

    private static function allowed(Model $record): bool
    {
        try {
            return BusinessFilamentActionAccess::userCan(DateCorrectionSubject::for($record)->permissionSlug);
        } catch (Throwable) {
            return false;
        }
    }

    private static function failed(string $title, string $body): void
    {
        Notification::make()
            ->title($title)
            ->body($body.' No se cambió ningún dato.')
            ->danger()
            ->persistent()
            ->send();
    }
}
