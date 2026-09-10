<?php

declare(strict_types=1);

namespace App\Filament\Shared\CommercialTelemedicine\Tables;

use App\Filament\Shared\CommercialTelemedicine\CommercialTelemedicinePanel;
use App\Models\TelemedicinePatient;
use App\Support\Filament\CommercialNetworkTelemedicineScope;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class CommercialTelemedicinePatientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Pacientes de sus afiliados')
            ->description('Solo consulta. Se muestran pacientes de telemedicina vinculados a las afiliaciones de su red.')
            ->emptyStateHeading('Sin pacientes de telemedicina')
            ->emptyStateDescription('Cuando un afiliado de su red se registre como paciente, aparecerá aquí.')
            ->emptyStateIcon(Heroicon::OutlinedUser)
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(function (Builder $query): Builder {
                $user = CommercialTelemedicinePanel::authenticatedUser();
                CommercialNetworkTelemedicineScope::applyToPatients($query, $user);

                $affiliationId = (int) request()->query('affiliation', 0);
                $corporateId = (int) request()->query('affiliation_corporate', 0);

                if ($affiliationId > 0) {
                    $query->where('afilliation_id', $affiliationId);
                }

                if ($corporateId > 0) {
                    $query->where('afilliation_corporate_id', $corporateId);
                }

                return $query->with([
                    'plan:id,description',
                    'coverage:id,price',
                    'afilliation:id,code,agent_id,code_agency,owner_code',
                    'afilliationCorporate:id,code,agent_id,code_agency,owner_code,name_corporate',
                ]);
            })
            ->columns([
                TextColumn::make('full_name')
                    ->label('Paciente')
                    ->icon(Heroicon::OutlinedUser)
                    ->formatStateUsing(fn (?string $state): string => $state ? mb_strtoupper($state) : '—')
                    ->description(fn (TelemedicinePatient $record): string => filled($record->nro_identificacion)
                        ? 'V-'.$record->nro_identificacion
                        : 'Sin identificación')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::SemiBold)
                    ->wrap(),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->icon(Heroicon::OutlinedPhone)
                    ->searchable()
                    ->placeholder('—')
                    ->copyable()
                    ->copyMessage('Teléfono copiado'),
                TextColumn::make('code_affiliation')
                    ->label('Afiliación')
                    ->badge()
                    ->color('success')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('plan.description')
                    ->label('Plan')
                    ->badge()
                    ->color('primary')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('status_affiliation')
                    ->label('Estatus afiliación')
                    ->badge()
                    ->color(fn (?string $state): string => match (mb_strtoupper((string) $state)) {
                        'ACTIVO', 'ACTIVA' => 'success',
                        'SUSPENDIDO', 'SUSPENDIDA' => 'warning',
                        'INACTIVO', 'INACTIVA', 'CANCELADO', 'CANCELADA' => 'danger',
                        default => 'gray',
                    })
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('type_affiliation')
                    ->label('Tipo')
                    ->badge()
                    ->color('info')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Registrado')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type_affiliation')
                    ->label('Tipo de afiliación')
                    ->options([
                        'INDIVIDUAL' => 'Individual',
                        'CORPORATIVO' => 'Corporativo',
                    ]),
                SelectFilter::make('status_affiliation')
                    ->label('Estatus')
                    ->options([
                        'ACTIVO' => 'Activo',
                        'ACTIVA' => 'Activa',
                        'SUSPENDIDO' => 'Suspendido',
                    ]),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Ver ficha')
                    ->icon(Heroicon::OutlinedEye),
            ]);
    }
}
