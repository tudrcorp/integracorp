<?php

declare(strict_types=1);

namespace App\Filament\Business\Concerns;

use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\User;
use App\Support\AffiliationAffiliateBusinessContextSynchronizer;
use App\Support\Affiliations\AffiliateSpecificBusinessUnitChanger;
use App\Support\Filament\UserNavigationAccess;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use Throwable;

/**
 * Unidad de negocio específica por afiliado en las tablas de afiliados de
 * Negocios (individual y corporativa): columna, cambio con nota obligatoria
 * (una fila o varias) e historial. La regla vive en
 * {@see AffiliateSpecificBusinessUnitChanger}.
 */
trait ManagesAffiliateSpecificBusinessUnit
{
    public static function userCanChangeSpecificBusinessUnit(): bool
    {
        $user = Auth::user();

        return $user instanceof User
            && (UserNavigationAccess::isSuperAdmin($user) || UserNavigationAccess::userHasModule($user, 'NEGOCIOS'));
    }

    protected function specificBusinessUnitColumn(): TextColumn
    {
        return TextColumn::make('specific_business_unit')
            ->label('Unidad de negocio específica')
            ->icon(fn (Affiliate|AffiliateCorporate $record): Heroicon => AffiliateSpecificBusinessUnitChanger::isLocked($record)
                ? Heroicon::LockClosed
                : Heroicon::PencilSquare)
            ->color(fn (Affiliate|AffiliateCorporate $record): ?string => AffiliateSpecificBusinessUnitChanger::isLocked($record) ? 'primary' : null)
            ->description(fn (Affiliate|AffiliateCorporate $record): ?string => AffiliateSpecificBusinessUnitChanger::isLocked($record)
                ? 'Fijada a mano'
                : null)
            ->wrap()
            ->limit(28)
            ->tooltip(function (Affiliate|AffiliateCorporate $record): ?string {
                $value = filled($record->specific_business_unit) ? (string) $record->specific_business_unit : null;

                if (! AffiliateSpecificBusinessUnitChanger::isLocked($record)) {
                    return $value;
                }

                return trim(($value ?? 'Sin unidad específica')
                    .' · Fijada a mano por '.($record->specific_business_unit_locked_by ?: 'un analista')
                    .'. Las sincronizaciones con la afiliación no la cambian.');
            })
            ->placeholder('—')
            ->searchable()
            ->toggleable();
    }

    protected function changeSpecificBusinessUnitAction(): Action
    {
        return Action::make('change_specific_business_unit')
            ->label('Cambiar unidad específica')
            ->icon(Heroicon::BuildingOffice)
            ->color('primary')
            ->visible(fn (): bool => self::userCanChangeSpecificBusinessUnit())
            ->modalHeading('Cambiar unidad de negocio específica')
            ->modalDescription(fn (Affiliate|AffiliateCorporate $record): string => 'Afiliado: '.trim((string) $record->full_name)
                .(filled($record->nro_identificacion) ? ' · C.I. '.$record->nro_identificacion : ''))
            ->modalIcon(Heroicon::BuildingOffice)
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel('Guardar cambio')
            ->schema(fn (Affiliate|AffiliateCorporate $record): array => $this->specificBusinessUnitFormSchema($record, 1))
            ->action(function (Affiliate|AffiliateCorporate $record, array $data, Action $action): void {
                $this->applySpecificBusinessUnitChange([$record], $data, $action);
            });
    }

    protected function changeSpecificBusinessUnitBulkAction(): BulkAction
    {
        return BulkAction::make('change_specific_business_unit_bulk')
            ->label('Cambiar unidad específica')
            ->icon(Heroicon::BuildingOffice)
            ->color('primary')
            ->visible(fn (): bool => self::userCanChangeSpecificBusinessUnit())
            ->modalHeading(fn (Collection $records): string => 'Cambiar unidad específica de '.$records->count().' afiliado(s)')
            ->modalDescription('El mismo valor y la misma nota se aplican a todos los seleccionados. Queda una fila de traza por afiliado.')
            ->modalIcon(Heroicon::BuildingOffice)
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel('Guardar cambio')
            ->schema(fn (Collection $records): array => $this->specificBusinessUnitFormSchema(null, $records->count()))
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, array $data, BulkAction $action): void {
                $this->applySpecificBusinessUnitChange($records, $data, $action);
            });
    }

    protected function specificBusinessUnitHistoryAction(): Action
    {
        return Action::make('specific_business_unit_history')
            ->label('Historial de unidad específica')
            ->icon(Heroicon::Clock)
            ->color('gray')
            ->visible(fn (): bool => self::userCanChangeSpecificBusinessUnit())
            ->slideOver()
            ->modalHeading('Historial de unidad de negocio específica')
            ->modalDescription(fn (Affiliate|AffiliateCorporate $record): string => trim((string) $record->full_name).'. Los registros no se pueden editar ni borrar.')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->modalContent(fn (Affiliate|AffiliateCorporate $record): Htmlable => view('filament.business.partials.affiliate-business-unit-history', [
                'changes' => AffiliateSpecificBusinessUnitChanger::history($record),
            ]));
    }

    /**
     * @return array<int, mixed>
     */
    protected function specificBusinessUnitFormSchema(Affiliate|AffiliateCorporate|null $record, int $count): array
    {
        $ownerValue = AffiliationAffiliateBusinessContextSynchronizer::normalizeSpecificBusinessUnit($this->getOwnerRecord()->specific_business_unit);

        return [
            Placeholder::make('current_state')
                ->hiddenLabel()
                ->content(fn (): HtmlString => new HtmlString(view('filament.business.partials.affiliate-business-unit-current', [
                    'record' => $record,
                    'count' => $count,
                    'ownerValue' => $ownerValue,
                    'locked' => $record !== null && AffiliateSpecificBusinessUnitChanger::isLocked($record),
                ])->render())),
            ToggleButtons::make('mode')
                ->label('¿Qué quiere hacer?')
                ->options([
                    'set' => 'Asignar una unidad',
                    'clear' => 'Dejar sin unidad',
                    'inherit' => 'Heredar de la afiliación',
                ])
                ->icons([
                    'set' => Heroicon::PencilSquare,
                    'clear' => Heroicon::NoSymbol,
                    'inherit' => Heroicon::ArrowUturnLeft,
                ])
                ->colors([
                    'set' => 'primary',
                    'clear' => 'warning',
                    'inherit' => 'gray',
                ])
                ->default('set')
                ->inline()
                ->required()
                ->live(),
            TextInput::make('value')
                ->label('Unidad de negocio específica')
                ->placeholder('Escriba o elija una de la lista')
                ->datalist(fn (): array => AffiliateSpecificBusinessUnitChanger::knownValues())
                ->helperText('Se guarda en mayúsculas. Elija una existente para no crear variantes del mismo nombre.')
                ->maxLength(AffiliateSpecificBusinessUnitChanger::VALUE_MAX_LENGTH)
                ->required(fn (Get $get): bool => $get('mode') === 'set')
                ->visible(fn (Get $get): bool => $get('mode') === 'set'),
            Placeholder::make('inherit_hint')
                ->hiddenLabel()
                ->visible(fn (Get $get): bool => $get('mode') === 'inherit')
                ->content('Tomará «'.($ownerValue ?? 'Sin unidad específica').'», el valor de la afiliación, y volverá a seguirla en cada sincronización.'),
            Textarea::make('note')
                ->label('Nota del cambio')
                ->placeholder('Ej.: El afiliado pasó a la sede Valencia según correo de RR. HH. del 20/10/2026.')
                ->helperText('Obligatoria. Explique por qué cambia: queda en el historial con su nombre, la fecha y el valor anterior.')
                ->rows(3)
                ->required()
                ->minLength(AffiliateSpecificBusinessUnitChanger::NOTE_MIN_LENGTH)
                ->maxLength(AffiliateSpecificBusinessUnitChanger::NOTE_MAX_LENGTH)
                ->validationAttribute('nota'),
            Placeholder::make('impact')
                ->hiddenLabel()
                ->content(new HtmlString(
                    '<p class="text-xs text-gray-500 dark:text-gray-400">También se actualiza el paciente de telemedicina ligado a esta afiliación, '
                    .'para que Órdenes de servicio y Cuentas por pagar muestren el mismo valor. Un valor fijado aquí no lo cambian las sincronizaciones con la afiliación.</p>'
                )),
        ];
    }

    /**
     * @param  iterable<int, Affiliate|AffiliateCorporate>  $records
     * @param  array<string, mixed>  $data
     */
    protected function applySpecificBusinessUnitChange(iterable $records, array $data, Action $action): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User && self::userCanChangeSpecificBusinessUnit(), 403);

        $mode = (string) ($data['mode'] ?? 'set');
        if (! in_array($mode, ['set', 'clear', 'inherit'], true)) {
            $action->halt();

            return;
        }

        $ownerId = (int) $this->getOwnerRecord()->getKey();
        $ownerColumn = $this->getOwnerRecord() instanceof AffiliationCorporate ? 'affiliation_corporate_id' : 'affiliation_id';
        $records = collect($records)->filter(fn (mixed $record): bool => (int) ($record->{$ownerColumn} ?? 0) === $ownerId);

        try {
            $result = AffiliateSpecificBusinessUnitChanger::change(
                $records,
                $mode === 'set' ? ($data['value'] ?? null) : null,
                $mode === 'inherit',
                (string) ($data['note'] ?? ''),
                $user,
            );
        } catch (InvalidArgumentException $exception) {
            Notification::make()
                ->title('No se pudo cambiar la unidad específica')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            $action->halt();

            return;
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('No se pudo cambiar la unidad específica')
                ->body('No se guardó nada. Intente de nuevo; si se repite, avise a soporte con la hora del intento.')
                ->danger()
                ->send();

            $action->halt();

            return;
        }

        if ($result['changed'] === 0) {
            Notification::make()
                ->title('No había nada que cambiar')
                ->body('Los afiliados ya tenían ese valor.')
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title($result['changed'] === 1 ? 'Unidad específica actualizada' : $result['changed'].' afiliados actualizados')
            ->body(implode(' ', array_filter([
                $result['unchanged'] > 0 ? $result['unchanged'].' ya tenían ese valor.' : null,
                $result['patients'] > 0 ? $result['patients'].' paciente(s) de telemedicina actualizados.' : null,
                'Quedó registrado en el historial.',
            ])))
            ->success()
            ->send();
    }
}
