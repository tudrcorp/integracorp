<?php

declare(strict_types=1);

namespace App\Filament\Shared\CommercialTelemedicine\Tables;

use App\Filament\Shared\CommercialTelemedicine\CommercialTelemedicinePanel;
use App\Models\TelemedicineCase;
use App\Support\Filament\CommercialNetworkTelemedicineScope;
use App\Support\Telemedicine\TelemedicinePriorityFilamentBadge;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class CommercialTelemedicineCasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Casos abiertos de su red')
            ->description('Solo consulta. Los casos con alta médica no se muestran, igual que en Operaciones.')
            ->emptyStateHeading('Sin casos abiertos')
            ->emptyStateDescription('Cuando un paciente de sus afiliados tenga un caso en seguimiento, aparecerá aquí.')
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentList)
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(function (Builder $query): Builder {
                $user = CommercialTelemedicinePanel::authenticatedUser();
                CommercialNetworkTelemedicineScope::applyToCases($query, $user, excludeDischarged: true);

                return $query->with([
                    CommercialNetworkTelemedicineScope::patientCaseRelationEagerLoad(),
                    'telemedicineDoctor:id,full_name',
                    'priority:id,name',
                ]);
            })
            ->columns([
                TextColumn::make('code')
                    ->label('N.º de caso')
                    ->badge()
                    ->color('primary')
                    ->weight(FontWeight::SemiBold)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('telemedicinePatient.full_name')
                    ->label('Paciente')
                    ->description(fn (TelemedicineCase $record): string => filled($record->telemedicineDoctor?->full_name)
                        ? 'Dr(a). '.$record->telemedicineDoctor->full_name
                        : 'Sin médico asignado')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'EN SEGUIMIENTO' => 'warning',
                        'CONSULTA INICIAL' => 'info',
                        'ASIGNADO' => 'primary',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('priority.name')
                    ->label('Prioridad')
                    ->badge()
                    ->color(fn (?string $state): string => filled($state)
                        ? TelemedicinePriorityFilamentBadge::color($state)
                        : 'gray')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Apertura')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estatus')
                    ->options([
                        'ASIGNADO' => 'Asignado',
                        'CONSULTA INICIAL' => 'Consulta inicial',
                        'EN SEGUIMIENTO' => 'En seguimiento',
                    ]),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Ver caso')
                    ->icon(Heroicon::OutlinedEye),
                Action::make('view_patient')
                    ->label('Ver paciente')
                    ->icon(Heroicon::OutlinedUser)
                    ->color('gray')
                    ->visible(fn (): bool => CommercialTelemedicinePanel::canViewPatients())
                    ->url(fn (TelemedicineCase $record): ?string => $record->telemedicine_patient_id
                        ? CommercialTelemedicinePanel::patientViewUrl((int) $record->telemedicine_patient_id)
                        : null),
            ]);
    }
}
