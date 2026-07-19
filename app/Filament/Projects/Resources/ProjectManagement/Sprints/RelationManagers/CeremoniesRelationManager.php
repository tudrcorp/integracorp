<?php

declare(strict_types=1);

namespace App\Filament\Projects\Resources\ProjectManagement\Sprints\RelationManagers;

use App\Enums\ProjectManagement\CeremonyType;
use App\Support\Filament\ProjectManagement\ProjectManagementCollaboratorSelect;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CeremoniesRelationManager extends RelationManager
{
    protected static string $relationship = 'ceremonies';

    protected static ?string $title = 'Ceremonias';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedCalendarDays;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label('Tipo')
                    ->options(CeremonyType::options())
                    ->required()
                    ->native(false),
                DateTimePicker::make('scheduled_at')
                    ->label('Programada')
                    ->native(false)
                    ->seconds(false)
                    ->required(),
                DateTimePicker::make('ended_at')
                    ->label('Finalizada')
                    ->native(false)
                    ->seconds(false),
                Select::make('facilitator_id')
                    ->label('Facilitador')
                    ->options(fn (): array => ProjectManagementCollaboratorSelect::options())
                    ->getSearchResultsUsing(fn (string $search): array => ProjectManagementCollaboratorSelect::searchOptions($search))
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Textarea::make('notes')
                    ->label('Notas')
                    ->rows(4)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Ceremonias del sprint')
            ->description('Planning, Daily, Review y Retrospectiva.')
            ->emptyStateHeading('Sin ceremonias')
            ->emptyStateDescription('Agenda la primera ceremonia del sprint.')
            ->emptyStateIcon(Heroicon::OutlinedCalendarDays)
            ->defaultSort('scheduled_at')
            ->modifyQueryUsing(fn ($query) => $query->with('facilitator:id,fullName'))
            ->columns([
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (CeremonyType|string $state): string => $state instanceof CeremonyType
                        ? $state->label()
                        : (CeremonyType::tryFrom($state)?->label() ?? $state))
                    ->color(fn (CeremonyType|string $state): string => match ($state instanceof CeremonyType ? $state : CeremonyType::tryFrom($state)) {
                        CeremonyType::Planning => 'primary',
                        CeremonyType::Daily => 'info',
                        CeremonyType::Review => 'success',
                        CeremonyType::Retro => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('scheduled_at')
                    ->label('Programada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('ended_at')
                    ->label('Finalizada')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),
                TextColumn::make('facilitator.fullName')
                    ->label('Facilitador')
                    ->placeholder('—'),
                TextColumn::make('notes')
                    ->label('Notas')
                    ->limit(40)
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Nueva ceremonia'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
