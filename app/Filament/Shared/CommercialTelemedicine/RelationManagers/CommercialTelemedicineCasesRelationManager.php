<?php

declare(strict_types=1);

namespace App\Filament\Shared\CommercialTelemedicine\RelationManagers;

use App\Filament\Shared\CommercialTelemedicine\CommercialTelemedicinePanel;
use App\Models\TelemedicineCase;
use App\Support\Filament\CommercialNetworkTelemedicineScope;
use App\Support\Telemedicine\TelemedicinePriorityFilamentBadge;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CommercialTelemedicineCasesRelationManager extends RelationManager
{
    protected static string $relationship = 'telemedicineCases';

    protected static ?string $title = 'Casos abiertos';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Casos abiertos de este paciente')
            ->description('Los casos con alta médica no se listan.')
            ->emptyStateHeading('Sin casos abiertos')
            ->emptyStateDescription('Este paciente no tiene casos en seguimiento.')
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentList)
            ->modifyQueryUsing(function (Builder $query): Builder {
                return $query
                    ->where('status', '!=', CommercialNetworkTelemedicineScope::DISCHARGED_STATUS)
                    ->with(['telemedicineDoctor:id,full_name', 'priority:id,name'])
                    ->orderByDesc('created_at');
            })
            ->columns([
                TextColumn::make('code')
                    ->label('N.º de caso')
                    ->badge()
                    ->color('primary')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'EN SEGUIMIENTO' => 'warning',
                        'CONSULTA INICIAL' => 'info',
                        'ASIGNADO' => 'primary',
                        default => 'gray',
                    }),
                TextColumn::make('priority.name')
                    ->label('Prioridad')
                    ->badge()
                    ->color(fn (?string $state): string => filled($state)
                        ? TelemedicinePriorityFilamentBadge::color($state)
                        : 'gray')
                    ->placeholder('—'),
                TextColumn::make('telemedicineDoctor.full_name')
                    ->label('Médico')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Apertura')
                    ->dateTime('d/m/Y'),
            ])
            ->headerActions([])
            ->recordActions([
                ViewAction::make()
                    ->label('Ver caso')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (TelemedicineCase $record): string => CommercialTelemedicinePanel::caseViewUrl($record)),
            ]);
    }
}
