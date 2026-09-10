<?php

declare(strict_types=1);

namespace App\Filament\Shared\CommercialTelemedicine\RelationManagers;

use App\Filament\Shared\CommercialTelemedicine\Schemas\CommercialTelemedicineConsultationInfolist;
use App\Models\TelemedicineConsultationPatient;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CommercialTelemedicineConsultationsRelationManager extends RelationManager
{
    protected static string $relationship = 'consultations';

    protected static ?string $title = 'Consultas';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Consultas del caso')
            ->description('Seguimientos registrados. No se muestra la historia clínica.')
            ->emptyStateHeading('Sin consultas')
            ->emptyStateDescription('Aún no hay consultas registradas en este caso.')
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentList)
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'telemedicineDoctor:id,full_name',
                'telemedicineServiceList:id,name',
                'telemedicineGeneralService:id,name',
                'telemedicineServiceListDrift:id,name',
            ]))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('telemedicineServiceList.name')
                    ->label('Servicio')
                    ->badge()
                    ->color('success')
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('telemedicineDoctor.full_name')
                    ->label('Médico')
                    ->weight(FontWeight::Medium)
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'EN SEGUIMIENTO' => 'warning',
                        'CONSULTA INICIAL' => 'info',
                        'ALTA MEDICA' => 'success',
                        default => 'gray',
                    }),
            ])
            ->headerActions([])
            ->recordActions([
                ViewAction::make()
                    ->label('Ver consulta')
                    ->icon(Heroicon::OutlinedEye)
                    ->modalHeading(fn (TelemedicineConsultationPatient $record): string => 'Consulta'
                        .(filled($record->telemedicine_case_code) ? ' · '.$record->telemedicine_case_code : ''))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->infolist(fn (Schema $schema): Schema => CommercialTelemedicineConsultationInfolist::configure($schema)),
            ]);
    }
}
