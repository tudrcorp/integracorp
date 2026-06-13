<?php

namespace App\Filament\Administration\Resources\AffiliationCorporates\RelationManagers;

use App\Models\AffiliationCorporate;
use App\Models\StatusLogAffiliationCorporate;
use App\Support\SecurityAudit;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class StatusLogCorporateAffiliationsRelationManager extends RelationManager
{
    protected static string $relationship = 'status_log_corporate_affiliations';

    protected static ?string $title = 'Notas y/o Observaciones';

    protected static string|BackedEnum|null $icon = 'heroicon-o-clipboard-document';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Observaciones')
                    ->description('Registre notas internas o comentarios sobre el trámite de esta afiliación.')
                    ->schema([
                        Textarea::make('observation')
                            ->label('Nota u observación')
                            ->autosize()
                            ->required()
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                if ($state !== null) {
                                    $set('observation', strtoupper($state));
                                }
                            }),
                        TextInput::make('updated_by')
                            ->label('Responsable')
                            ->disabled()
                            ->dehydrated()
                            ->default(fn (): ?string => Auth::user()?->name),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Bitácora de notas y observaciones')
            ->description('Historial de notas registradas sobre esta afiliación corporativa.')
            ->recordTitleAttribute('observation')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('observation')
                    ->label('Nota u observación')
                    ->wrap()
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('updated_by')
                    ->label('Responsable')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('desde'),
                        DatePicker::make('hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['desde'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['hasta'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['desde'] ?? null) {
                            $indicators['desde'] = 'Desde '.Carbon::parse($data['desde'])->toFormattedDateString();
                        }
                        if ($data['hasta'] ?? null) {
                            $indicators['hasta'] = 'Hasta '.Carbon::parse($data['hasta'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
            ])
            ->filtersTriggerAction(
                fn (Action $action) => $action
                    ->button()
                    ->label('Filtros'),
            )
            ->headerActions([
                CreateAction::make()
                    ->createAnother(false)
                    ->label('Agregar nota')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Registrar nota u observación')
                    ->successNotificationTitle('Nota registrada')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['action'] = 'AGREGO OBSERVACION';
                        $data['updated_by'] = Auth::user()?->name ?? 'SISTEMA';

                        if (isset($data['observation'])) {
                            $data['observation'] = strtoupper((string) $data['observation']);
                        }

                        return $data;
                    })
                    ->after(function (StatusLogAffiliationCorporate $record): void {
                        /** @var AffiliationCorporate $owner */
                        $owner = $this->getOwnerRecord();
                        $authUser = Auth::user();

                        SecurityAudit::log(
                            'AUDIT_ADMIN_AFFILIATION_CORPORATE_STATUS_UPDATED',
                            'administration.affiliation-corporates.observations',
                            [
                                'panel' => 'administration',
                                'affiliation_corporate_id' => $owner->id,
                                'affiliation_corporate_code' => $owner->code,
                                'action_type' => 'observation',
                                'status' => $owner->status,
                                'description' => $record->observation,
                                'updated_by' => $authUser?->name,
                                'status_log_id' => $record->id,
                            ],
                            $authUser,
                        );
                    }),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->label('Eliminar')
                    ->successNotificationTitle('Nota eliminada'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
