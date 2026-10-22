<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\AffiliationCorporates\Actions;

use App\Models\AffiliationCorporate;
use App\Models\AffiliationCorporateDateCorrection;
use App\Models\User;
use App\Support\AffiliationCorporates\DateCorrection\CorporateDateCorrectionPreview;
use App\Support\AffiliationCorporates\DateCorrection\CorporateDateCorrectionRollback;
use App\Support\AffiliationCorporates\DateCorrection\CorporateDateCorrector;
use App\Support\Collections\CollectionDueDate;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use RuntimeException;
use Throwable;

/**
 * «Corregir emisión y vigencia» y su reversión, en la vista de la afiliación corporativa
 * (Administración). Ambas exigen el permiso propio de la acción.
 */
final class CorporateEffectiveDateActions
{
    public static function correct(): Action
    {
        return Action::make('correctEffectiveDate')
            ->label('Corregir emisión y vigencia')
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color('warning')
            ->modalHeading('Corregir fecha de emisión y vigencia')
            ->modalDescription('Mueve la vigencia y todo lo que se calculó con ella: avisos POR PAGAR, renovación y fechas informativas del pago. Los avisos pagados y los montos no cambian. Queda un punto de restauración.')
            ->modalIcon(Heroicon::OutlinedCalendarDays)
            ->modalSubmitActionLabel('Corregir ahora')
            ->modalCancelActionLabel('Cancelar')
            ->modalWidth(Width::FourExtraLarge)
            ->visible(fn (AffiliationCorporate $record): bool => self::allowed()
                && strtoupper(trim((string) $record->status)) === CorporateDateCorrector::ACTIVE_STATUS)
            ->authorize(fn (): bool => self::allowed())
            ->fillForm(fn (AffiliationCorporate $record): array => [
                'new_date' => CollectionDueDate::parse($record->activated_at)?->format('d/m/Y'),
                'reason' => '',
                'confirmed' => false,
            ])
            ->schema([
                DatePicker::make('new_date')
                    ->label('Fecha correcta de emisión y vigencia')
                    ->helperText('Emisión y vigencia quedan iguales; la renovación será un año después.')
                    ->required()
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->format('d/m/Y')
                    ->closeOnDateSelection()
                    ->live(),
                Placeholder::make('preview')
                    ->label('Vista previa (sin escribir todavía)')
                    ->content(function (Get $get, AffiliationCorporate $record): HtmlString {
                        if (blank($get('new_date'))) {
                            return new HtmlString('<p class="text-sm text-gray-600 dark:text-gray-400">Elija la fecha correcta para ver qué cambia.</p>');
                        }

                        try {
                            return CorporateDateCorrectionPreview::html(CorporateDateCorrector::plan($record, $get('new_date')));
                        } catch (Throwable $exception) {
                            report($exception);

                            return new HtmlString('<p class="text-sm text-red-700 dark:text-red-300">No se pudo armar la vista previa: '.e($exception->getMessage()).'</p>');
                        }
                    })
                    ->columnSpanFull(),
                Textarea::make('reason')
                    ->label('Motivo de la corrección')
                    ->required()
                    ->minLength(CorporateDateCorrector::REASON_MIN_LENGTH)
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
            ->action(function (AffiliationCorporate $record, array $data): void {
                $user = Auth::user();

                if (! $user instanceof User || ! self::allowed()) {
                    return;
                }

                try {
                    $correction = CorporateDateCorrector::apply($record, $data['new_date'] ?? null, (string) ($data['reason'] ?? ''), $user);
                } catch (RuntimeException $exception) {
                    self::failed('No se corrigió la vigencia', $exception->getMessage());

                    return;
                } catch (Throwable $exception) {
                    report($exception);
                    self::failed('No se corrigió la vigencia', 'Ocurrió un error inesperado y se deshizo todo: no se cambió ningún dato.');

                    return;
                }

                $summary = (array) $correction->summary;

                Notification::make()
                    ->title('Vigencia corregida')
                    ->body('Emisión y vigencia: '.$correction->new_date.'. Renovación: '.($summary['renewal_after'] ?? '—')
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
            ->visible(fn (AffiliationCorporate $record): bool => self::allowed()
                && CorporateDateCorrectionRollback::latestAppliedFor($record) instanceof AffiliationCorporateDateCorrection)
            ->authorize(fn (): bool => self::allowed())
            ->schema([
                Placeholder::make('rollback_preview')
                    ->hiddenLabel()
                    ->content(fn (AffiliationCorporate $record): HtmlString => self::rollbackHtml(CorporateDateCorrectionRollback::latestAppliedFor($record)))
                    ->columnSpanFull(),
                Textarea::make('note')
                    ->label('Motivo de la reversión')
                    ->required()
                    ->minLength(CorporateDateCorrectionRollback::NOTE_MIN_LENGTH)
                    ->maxLength(2000)
                    ->rows(3)
                    ->columnSpanFull(),
                Checkbox::make('confirmed')
                    ->label('Entiendo que las fechas vuelven a las anteriores, incluidos los avisos POR PAGAR.')
                    ->accepted()
                    ->required()
                    ->columnSpanFull(),
            ])
            ->action(function (AffiliationCorporate $record, array $data): void {
                $user = Auth::user();
                $correction = CorporateDateCorrectionRollback::latestAppliedFor($record);

                if (! $user instanceof User || ! self::allowed() || ! $correction instanceof AffiliationCorporateDateCorrection) {
                    return;
                }

                try {
                    CorporateDateCorrectionRollback::execute($correction, $user, (string) ($data['note'] ?? ''));
                } catch (RuntimeException $exception) {
                    self::failed('No se revirtió la corrección', $exception->getMessage());

                    return;
                } catch (Throwable $exception) {
                    report($exception);
                    self::failed('No se revirtió la corrección', 'Ocurrió un error inesperado y se deshizo todo: no se cambió ningún dato.');

                    return;
                }

                Notification::make()
                    ->title('Corrección revertida')
                    ->body('Las fechas volvieron a '.($correction->activated_at_before ?? 'las anteriores').'.')
                    ->success()
                    ->persistent()
                    ->send();

                $record->refresh();
            });
    }

    private static function allowed(): bool
    {
        return BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::CORRECT_CORPORATE_EFFECTIVE_DATE);
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

    private static function rollbackHtml(?AffiliationCorporateDateCorrection $correction): HtmlString
    {
        if (! $correction instanceof AffiliationCorporateDateCorrection) {
            return new HtmlString('<p class="text-sm">No hay una corrección aplicada que revertir.</p>');
        }

        $blockers = CorporateDateCorrectionRollback::blockers($correction);
        $html = '<div class="space-y-3 text-sm"><p>Corrección del <strong>'.e($correction->applied_at?->format('d/m/Y H:i') ?? '').'</strong> por '
            .e($correction->applied_by_name).': <strong>'.e($correction->activated_at_before ?? '—').' → '.e($correction->new_date).'</strong>.</p>'
            .'<p class="text-gray-600 dark:text-gray-400">Motivo: '.e($correction->reason).'</p>';

        if ($blockers === []) {
            $html .= '<p class="font-semibold text-green-700 dark:text-green-300">Nada cambió desde la corrección: se puede revertir de forma segura.</p>';
        } else {
            $html .= '<div class="text-red-700 dark:text-red-300"><p class="font-semibold">No se puede revertir todavía:</p><ul class="list-disc pl-5">';

            foreach ($blockers as $blocker) {
                $html .= '<li>'.e($blocker).'</li>';
            }

            $html .= '</ul></div>';
        }

        return new HtmlString($html.'</div>');
    }
}
