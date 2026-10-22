<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\AffiliationDateInconsistencies\Pages;

use App\Enums\AffiliationDateInconsistencyKind as Kind;
use App\Filament\Administration\Resources\AffiliationDateInconsistencies\AffiliationDateInconsistencyResource;
use App\Filament\Administration\Resources\Collections\CollectionResource;
use App\Filament\Administration\Resources\RenovationCorporates\RenovationCorporateResource;
use App\Filament\Administration\Resources\Renovations\RenovationResource;
use App\Models\AffiliationDateCorrection;
use App\Models\AffiliationDateInconsistency as Inconsistency;
use App\Models\User;
use App\Support\Affiliations\DateCorrection\AffiliationDateCorrectionPreview;
use App\Support\Affiliations\DateCorrection\AffiliationDateCorrectionRollback;
use App\Support\Affiliations\DateCorrection\DateCorrectionSubject;
use App\Support\Affiliations\DateInconsistencies\DateInconsistencyResolver;
use App\Support\Affiliations\DateInconsistencies\DateInconsistencyScanner;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use RuntimeException;
use Throwable;

class ViewAffiliationDateInconsistency extends ViewRecord
{
    protected static string $resource = AffiliationDateInconsistencyResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var Inconsistency $record */
        $record = $this->getRecord();

        return ($record->kindEnum()?->label() ?? 'Inconsistencia').' · '.$record->affiliation_code;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->repairAction(),
            $this->dismissAction(),
            $this->revertAction(),
            $this->reopenAction(),
            ActionGroup::make([
                Action::make('rescan')
                    ->label('Volver a revisar esta afiliación')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->action(function (Inconsistency $record): void {
                        $affiliation = DateInconsistencyResolver::affiliationOf($record);

                        if ($affiliation === null) {
                            Notification::make()->title('La afiliación ya no existe')->warning()->send();

                            return;
                        }

                        $counts = DateInconsistencyScanner::scanAffiliation($affiliation, $this->actor());
                        $record->refresh();

                        Notification::make()
                            ->title('Afiliación revisada')
                            ->body('Nuevas: '.$counts['opened'].' · Reabiertas: '.$counts['reopened'].' · Resueltas: '.$counts['resolved'].'. Estado de esta: '.$record->statusLabel().'.')
                            ->success()
                            ->send();
                    }),
                Action::make('viewAffiliation')
                    ->label('Ver la afiliación')
                    ->icon(Heroicon::OutlinedIdentification)
                    ->url(fn (Inconsistency $record): string => AffiliationDateInconsistencyResource::affiliationUrl($record)),
                Action::make('goToCollections')
                    ->label('Ir a Gestión de Cobranza (Ajustar cuota)')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->url(fn (Inconsistency $record): string => CollectionResource::getUrl('index', ['search' => $record->affiliation_code]))
                    ->visible(fn (Inconsistency $record): bool => $record->kind === Kind::AvisoFueraDeCalendario->value),
                Action::make('goToRenovations')
                    ->label('Ir a Renovaciones')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->url(fn (Inconsistency $record): string => $record->affiliation_type === DateCorrectionSubject::TYPE_CORPORATE
                        ? RenovationCorporateResource::getUrl('index', ['search' => $record->affiliation_code])
                        : RenovationResource::getUrl('index', ['search' => $record->affiliation_code]))
                    ->visible(fn (Inconsistency $record): bool => $record->kind === Kind::CobrandoSinRenovar->value),
            ])->label('Más')->icon(Heroicon::OutlinedEllipsisVertical)->button()->color('gray'),
            Action::make('back')
                ->label('Volver')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(AffiliationDateInconsistencyResource::getUrl()),
        ];
    }

    private function repairAction(): Action
    {
        return Action::make('repair')
            ->label('Reparar')
            ->icon(Heroicon::OutlinedWrenchScrewdriver)
            ->color('warning')
            ->modalHeading('Reparar inconsistencia')
            ->modalDescription('Primero se calcula el resultado exacto sin guardar nada. Léalo con calma; solo al aprobar con su nota se aplica. Queda un punto de restauración para revertir.')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitActionLabel('Aprobar y reparar')
            ->visible(fn (Inconsistency $record): bool => $this->allowed() && DateInconsistencyResolver::canRepair($record))
            ->authorize(fn (): bool => $this->allowed())
            ->fillForm(fn (Inconsistency $record): array => [
                'date' => $record->details['suggestions'][0]['date'] ?? null,
                'simulation' => null,
                'fingerprint' => null,
                'note' => '',
                'confirmed' => false,
            ])
            ->steps([
                Step::make('Fecha')
                    ->description('Qué fecha se aplica')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->schema([
                        Placeholder::make('suggestions')
                            ->label('Fechas que sugiere el sistema')
                            ->content(fn (Inconsistency $record): HtmlString => self::suggestionsHtml($record))
                            ->columnSpanFull(),
                        DatePicker::make('date')
                            ->label(fn (Inconsistency $record): string => ($record->details['repair_mode'] ?? null) === Kind::REPAIR_AUTO
                                ? 'Fecha que se aplica (sale de los datos)'
                                : 'Fecha correcta (elíjala usted)')
                            ->helperText('Póliza sin renovaciones: fecha de emisión. Póliza renovada: inicio del año actual. La renovación queda un año después.')
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->format('d/m/Y')
                            ->closeOnDateSelection()
                            ->disabled(fn (Inconsistency $record): bool => ($record->details['repair_mode'] ?? null) === Kind::REPAIR_AUTO)
                            ->dehydrated(),
                    ])
                    ->afterValidation(function (Get $get, Set $set, Inconsistency $record): void {
                        try {
                            $simulation = DateInconsistencyResolver::simulate($record, (string) $get('date'), $this->actor());
                        } catch (Throwable $exception) {
                            report($exception);
                            $simulation = ['ok' => false, 'blockers' => ['No se pudo calcular el resultado: '.$exception->getMessage()], 'lines' => [], 'fingerprint' => null, 'effective' => null, 'renewal' => null, 'warnings' => []];
                        }

                        $set('simulation', $simulation);
                        $set('fingerprint', $simulation['fingerprint']);
                    }),
                Step::make('Resumen y aprobación')
                    ->description('Leer y aprobar con nota')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->schema([
                        Hidden::make('simulation'),
                        Hidden::make('fingerprint'),
                        Placeholder::make('result')
                            ->label('Resultado de la ejecución (calculado, todavía no guardado)')
                            ->content(fn (Get $get): HtmlString => self::simulationHtml((array) ($get('simulation') ?? [])))
                            ->columnSpanFull(),
                        Textarea::make('note')
                            ->label('Nota de aprobación')
                            ->helperText('Queda en la afiliación, en la bitácora de la inconsistencia y en la auditoría.')
                            ->required()
                            ->minLength(DateInconsistencyResolver::NOTE_MIN_LENGTH)
                            ->maxLength(2000)
                            ->rows(3)
                            ->placeholder('Ejemplo: confirmado con Negocios, la póliza se emitió el 26/08/2026 según contrato.')
                            ->columnSpanFull(),
                        Checkbox::make('confirmed')
                            ->label('Leí el resultado completo y apruebo esta corrección.')
                            ->accepted()
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ])
            ->action(function (Inconsistency $record, array $data): void {
                $simulation = (array) ($data['simulation'] ?? []);

                if (! ($simulation['ok'] ?? false) || blank($data['fingerprint'] ?? null)) {
                    $this->failed('No se reparó', implode(' ', (array) ($simulation['blockers'] ?? ['El resumen no se pudo calcular.'])));

                    return;
                }

                try {
                    $result = DateInconsistencyResolver::repair($record, (string) $data['date'], (string) ($data['note'] ?? ''), (string) $data['fingerprint'], $this->actor());
                } catch (RuntimeException $exception) {
                    $this->failed('No se reparó', $exception->getMessage());

                    return;
                } catch (Throwable $exception) {
                    report($exception);
                    $this->failed('No se reparó', 'Ocurrió un error inesperado y se deshizo todo.');

                    return;
                }

                $lines = $result['lines'];
                $body = 'Se aplicaron '.count($lines).' cambio(s)'
                    .($lines !== [] ? ': '.implode(' · ', array_slice($lines, 0, 4)).(count($lines) > 4 ? ' · y '.(count($lines) - 4).' más (ver historial)' : '') : '')
                    .'. Revisión posterior: '.$result['rescan']['resolved'].' resuelta(s), '.$result['rescan']['reopened'].' reabierta(s), '.$result['rescan']['opened'].' nueva(s).';

                Notification::make()->title('Inconsistencia reparada')->body($body)->success()->persistent()->send();
                $this->refreshRecord();
            });
    }

    private function dismissAction(): Action
    {
        return Action::make('dismiss')
            ->label('No es una inconsistencia')
            ->icon(Heroicon::OutlinedHandThumbUp)
            ->color('gray')
            ->modalHeading('Marcar como «no es una inconsistencia»')
            ->modalDescription('Sale de la lista de abiertas mientras sus datos no cambien. Si los datos cambian, el sistema la vuelve a abrir. Se puede reabrir en cualquier momento.')
            ->modalSubmitActionLabel('Marcar')
            ->visible(fn (Inconsistency $record): bool => $this->allowed() && $record->status === Inconsistency::STATUS_OPEN)
            ->authorize(fn (): bool => $this->allowed())
            ->schema([
                Textarea::make('note')
                    ->label('Nota explicativa: por qué no es una inconsistencia')
                    ->required()
                    ->minLength(DateInconsistencyResolver::NOTE_MIN_LENGTH)
                    ->maxLength(2000)
                    ->rows(4)
                    ->placeholder('Ejemplo: la emisión es correcta; el cliente pagó tarde por un acuerdo con Negocios del 15/03.'),
            ])
            ->action(function (Inconsistency $record, array $data): void {
                $this->run(fn () => DateInconsistencyResolver::dismiss($record, (string) ($data['note'] ?? ''), $this->actor()), 'Marcada como no inconsistencia', 'Ya no aparece en la lista de abiertas.');
            });
    }

    private function revertAction(): Action
    {
        return Action::make('revertRepair')
            ->label('Revertir reparación')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->modalHeading('Revertir la reparación')
            ->modalDescription('La afiliación, los avisos, la renovación y las fechas del pago vuelven a como estaban antes de reparar. Es todo o nada. La inconsistencia vuelve a quedar abierta.')
            ->modalSubmitActionLabel('Revertir ahora')
            ->modalWidth(Width::ThreeExtraLarge)
            ->visible(fn (Inconsistency $record): bool => $this->allowed() && DateInconsistencyResolver::revertibleCorrection($record) instanceof AffiliationDateCorrection)
            ->authorize(fn (): bool => $this->allowed())
            ->schema([
                Placeholder::make('rollback')
                    ->hiddenLabel()
                    ->content(function (Inconsistency $record): HtmlString {
                        $correction = DateInconsistencyResolver::revertibleCorrection($record);

                        return AffiliationDateCorrectionPreview::rollbackHtml(
                            $correction,
                            $correction instanceof AffiliationDateCorrection ? AffiliationDateCorrectionRollback::blockers($correction) : [],
                        );
                    })
                    ->columnSpanFull(),
                Textarea::make('note')
                    ->label('Motivo de la reversión')
                    ->required()
                    ->minLength(DateInconsistencyResolver::NOTE_MIN_LENGTH)
                    ->maxLength(2000)
                    ->rows(3),
                Checkbox::make('confirmed')
                    ->label('Entiendo que todo vuelve a como estaba antes de la reparación.')
                    ->accepted()
                    ->required(),
            ])
            ->action(function (Inconsistency $record, array $data): void {
                $this->run(fn () => DateInconsistencyResolver::revert($record, (string) ($data['note'] ?? ''), $this->actor()), 'Reparación revertida', 'Los datos volvieron a su estado anterior y la inconsistencia quedó abierta.');
            });
    }

    private function reopenAction(): Action
    {
        return Action::make('reopen')
            ->label('Reabrir')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('warning')
            ->modalHeading('Reabrir inconsistencia')
            ->modalDescription('Vuelve a la lista de abiertas. Deshace «no es una inconsistencia».')
            ->modalSubmitActionLabel('Reabrir')
            ->visible(fn (Inconsistency $record): bool => $this->allowed() && $record->status === Inconsistency::STATUS_DISMISSED)
            ->authorize(fn (): bool => $this->allowed())
            ->schema([
                Textarea::make('note')
                    ->label('Motivo')
                    ->required()
                    ->minLength(DateInconsistencyResolver::NOTE_MIN_LENGTH)
                    ->maxLength(2000)
                    ->rows(3),
            ])
            ->action(function (Inconsistency $record, array $data): void {
                $this->run(fn () => DateInconsistencyResolver::reopen($record, (string) ($data['note'] ?? ''), $this->actor()), 'Inconsistencia reabierta', 'Volvió a la lista de abiertas.');
            });
    }

    private function run(callable $operation, string $successTitle, string $successBody): void
    {
        try {
            $operation();
        } catch (RuntimeException $exception) {
            $this->failed('No se pudo completar', $exception->getMessage());

            return;
        } catch (Throwable $exception) {
            report($exception);
            $this->failed('No se pudo completar', 'Ocurrió un error inesperado y se deshizo todo.');

            return;
        }

        Notification::make()->title($successTitle)->body($successBody)->success()->send();
        $this->refreshRecord();
    }

    private function failed(string $title, string $body): void
    {
        Notification::make()->title($title)->body($body.' No se cambió ningún dato.')->danger()->persistent()->send();
    }

    private function refreshRecord(): void
    {
        $this->record->refresh();
        $this->record->unsetRelation('events');
    }

    private function allowed(): bool
    {
        return BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::REPAIR_DATE_INCONSISTENCIES);
    }

    private function actor(): User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : throw new RuntimeException('Sesión no válida.');
    }

    private static function suggestionsHtml(Inconsistency $record): HtmlString
    {
        $suggestions = (array) ($record->details['suggestions'] ?? []);

        if ($suggestions === []) {
            return new HtmlString('<p class="text-sm text-gray-600 dark:text-gray-400">No hay una fecha sugerida: elíjala a partir del contrato o de lo que confirme Negocios.</p>');
        }

        $html = '<ul class="list-disc pl-5 text-sm">';

        foreach ($suggestions as $index => $suggestion) {
            $html .= '<li><strong>'.e($suggestion['date']).'</strong> — '.e($suggestion['label']).($index === 0 ? ' <span class="text-gray-500">(precargada)</span>' : '').'</li>';
        }

        return new HtmlString($html.'</ul>');
    }

    /**
     * @param  array<string, mixed>  $simulation
     */
    private static function simulationHtml(array $simulation): HtmlString
    {
        if ($simulation === []) {
            return new HtmlString('<p class="text-sm text-gray-600 dark:text-gray-400">Vuelva al paso anterior y confirme la fecha para calcular el resultado.</p>');
        }

        if (! ($simulation['ok'] ?? false)) {
            $items = '';

            foreach ((array) ($simulation['blockers'] ?? []) as $blocker) {
                $items .= '<li>'.e($blocker).'</li>';
            }

            return new HtmlString('<div class="text-sm text-red-700 dark:text-red-300"><p class="font-semibold mb-1">No se puede reparar con esa fecha:</p><ul class="list-disc pl-5">'.$items.'</ul><p class="mt-2">Vuelva al paso anterior para elegir otra fecha, o resuelva primero lo indicado.</p></div>');
        }

        $html = '<div class="space-y-3 text-sm"><p>Vigencia resultante: <strong>'.e((string) $simulation['effective']).'</strong> · Próxima renovación: <strong>'.e((string) $simulation['renewal']).'</strong></p>'
            .'<p class="font-semibold">Cambios exactos que se aplicarán ('.count((array) $simulation['lines']).'):</p><ul class="list-disc pl-5 space-y-1">';

        foreach ((array) $simulation['lines'] as $line) {
            $html .= '<li>'.e($line).'</li>';
        }

        $html .= '</ul><p class="text-gray-600 dark:text-gray-400">Los montos, los avisos pagados y los pagos registrados no cambian. Si los datos cambian antes de aprobar, el sistema no aplica nada y pide recalcular.</p>';

        if (($simulation['warnings'] ?? []) !== []) {
            $html .= '<div class="text-amber-700 dark:text-amber-300"><p class="font-semibold">Advertencias:</p><ul class="list-disc pl-5">';

            foreach ((array) $simulation['warnings'] as $warning) {
                $html .= '<li>'.e($warning).'</li>';
            }

            $html .= '</ul></div>';
        }

        return new HtmlString($html.'</div>');
    }
}
