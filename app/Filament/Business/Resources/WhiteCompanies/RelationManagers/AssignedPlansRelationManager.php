<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\WhiteCompanies\RelationManagers;

use App\Models\Fee;
use App\Models\WhiteCompany;
use App\Models\WhiteCompanyPlan;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Filament\FilamentIosButton;
use App\Support\WhiteCompanies\WhiteCompanyPlanAssignment;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Planes que la empresa aliada puede cotizar.
 *
 * Primer paso del circuito: acá el analista habilita el plan, y recién después
 * aparece en la matriz de negociación para pactarle venta y neta. Un plan sin
 * beneficios no se puede habilitar.
 */
class AssignedPlansRelationManager extends RelationManager
{
    protected static string $relationship = 'assignedPlans';

    protected static ?string $title = 'Planes asignados';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if (! parent::canViewForRecord($ownerRecord, $pageClass)) {
            return false;
        }

        return BusinessFilamentActionAccess::userCan(
            BusinessFilamentActionPermissionRegistry::ASSIGN_WHITE_COMPANY_PLAN,
        );
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('PLANES ASIGNADOS')
            ->description('Planes que esta empresa aliada puede cotizar. Después de asignarlos, cargue el precio de venta y la neta de sus tarifas en la matriz de negociación.')
            ->emptyStateHeading('Sin planes asignados')
            ->emptyStateDescription('Asigne un plan para que la empresa aliada pueda cotizarlo y para poder pactarle netas.')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('plan'))
            ->striped()
            ->columns([
                TextColumn::make('plan.description')
                    ->label('Plan')
                    ->badge()
                    ->color('success')
                    ->placeholder('—')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('plan_id')
                    ->label('Tarifas del catálogo')
                    ->badge()
                    ->color('gray')
                    ->state(fn (WhiteCompanyPlan $record): int => Fee::query()
                        ->forPlan((int) $record->plan_id)
                        ->count()),
                TextColumn::make('netas_pactadas')
                    ->label('Netas pactadas')
                    ->badge()
                    ->color(fn (mixed $state): string => (int) $state > 0 ? 'success' : 'warning')
                    ->state(fn (WhiteCompanyPlan $record): int => $this->negotiatedCountFor($record))
                    ->tooltip('Mientras no tenga netas pactadas, la empresa aliada no puede cotizar este plan.'),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Asignado')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(),
            ])
            ->headerActions([
                Action::make('assignPlans')
                    ->label('Asignar plan')
                    ->modalHeading('Asignar planes a la empresa aliada')
                    ->modalDescription('Solo se listan los planes que tienen beneficios cargados: sin beneficios no se puede describir la cobertura en la propuesta al cliente.')
                    ->modalSubmitActionLabel('Asignar')
                    ->modalWidth(Width::TwoExtraLarge)
                    ->icon(Heroicon::OutlinedSquares2x2)
                    ->color('success')
                    ->extraAttributes([
                        'class' => FilamentIosButton::extraClassForFilamentColor('success'),
                    ])
                    ->form([
                        Select::make('plan_ids')
                            ->label('Planes')
                            ->options(fn (): array => WhiteCompanyPlanAssignment::assignablePlans($this->ownerCompany()))
                            ->helperText('Cualquier plan con beneficios puede asignarse a cualquier empresa aliada. Los que ya están asignados no aparecen.')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->required()
                            ->validationMessages([
                                'required' => 'Debe seleccionar al menos un plan.',
                            ]),
                    ])
                    ->action(function (array $data): void {
                        $resumen = WhiteCompanyPlanAssignment::assign(
                            $this->ownerCompany(),
                            is_array($data['plan_ids'] ?? null) ? $data['plan_ids'] : [],
                            Auth::user()?->name,
                        );

                        Notification::make()
                            ->success()
                            ->title('Planes asignados')
                            ->body(sprintf(
                                '%d asignados, %d ya estaban. Cargue sus netas en la matriz de negociación.',
                                $resumen['asignados'],
                                $resumen['ya_estaban'],
                            ))
                            ->send();
                    }),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->label('Quitar')
                    ->modalHeading('Quitar plan de la empresa aliada')
                    ->modalDescription('Se retirarán también las netas pactadas de las tarifas de este plan, y la empresa aliada dejará de cotizarlo.')
                    ->requiresConfirmation()
                    ->using(function (WhiteCompanyPlan $record): void {
                        $resumen = WhiteCompanyPlanAssignment::unassign(
                            $this->ownerCompany(),
                            (int) $record->plan_id,
                        );

                        Notification::make()
                            ->success()
                            ->title('Plan retirado')
                            ->body(sprintf('Se retiraron %d netas pactadas.', $resumen['netas_retiradas']))
                            ->send();
                    }),
            ]);
    }

    private function negotiatedCountFor(WhiteCompanyPlan $record): int
    {
        $feeIds = Fee::query()->forPlan((int) $record->plan_id)->pluck('id');

        return $this->ownerCompany()
            ->negotiatedFees()
            ->whereIn('fee_id', $feeIds)
            ->count();
    }

    private function ownerCompany(): WhiteCompany
    {
        $company = $this->getOwnerRecord();

        return $company instanceof WhiteCompany ? $company : WhiteCompany::query()->findOrFail($company->getKey());
    }
}
