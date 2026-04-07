<?php

namespace App\Filament\Business\Resources\AccountManagers\RelationManagers;

use App\Filament\Business\Resources\Agents\AgentResource;
use App\Models\Agent;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AgentsRelationManager extends RelationManager
{
    protected static string $relationship = 'agents';

    protected static ?string $title = 'Agentes de corretaje';

    protected static string|BackedEnum|null $icon = 'heroicon-o-user-group';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('ownerAccountManagers')
                    ->default(fn (): int => (int) $this->getOwnerRecord()->user_id),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['typeAgent']))
            ->defaultSort('created_at', 'desc')
            ->heading('Agentes')
            ->description('Red de agentes asignada a este account manager. Usa filtros y columnas para afinar la vista.')
            ->emptyStateHeading('Sin agentes asignados')
            ->emptyStateDescription('Aún no hay agentes vinculados a este ejecutivo.')
            ->striped()
            ->columns([
                TextColumn::make('id')
                    ->label('Código')
                    ->formatStateUsing(function ($state, Agent $record): string {
                        return 'AGT-'.str_pad((string) $record->getKey(), 4, '0', STR_PAD_LEFT);
                    })
                    ->icon('heroicon-m-hashtag')
                    ->alignCenter()
                    ->badge()
                    ->color('primary')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('typeAgent.definition')
                    ->label('Tipo')
                    ->icon('heroicon-m-tag')
                    ->badge()
                    ->color(fn (?string $state): string => match (strtoupper((string) $state)) {
                        'SUB-AGENTE', 'SUB AGENTE' => 'warning',
                        'AGENTE' => 'info',
                        default => 'gray',
                    })
                    ->placeholder('—')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Nombre / razón social')
                    ->icon('heroicon-m-user')
                    ->weight(FontWeight::SemiBold)
                    ->wrap()
                    ->searchable()
                    ->sortable()
                    ->extraCellAttributes(fn (): array => [
                        'class' => 'min-w-44 sm:min-w-56',
                    ]),
                TextColumn::make('ci')
                    ->label('CI')
                    ->icon('heroicon-m-identification')
                    ->fontFamily(FontFamily::Mono)
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('email')
                    ->label('Correo')
                    ->icon('heroicon-m-envelope')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Correo copiado')
                    ->limit(28)
                    ->tooltip(fn (Agent $record): string => (string) $record->email),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->icon('heroicon-m-phone')
                    ->fontFamily(FontFamily::Mono)
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Teléfono copiado')
                    ->toggleable(),
                TextColumn::make('address')
                    ->label('Dirección')
                    ->icon('heroicon-m-map-pin')
                    ->wrap()
                    ->lineClamp(2)
                    ->tooltip(fn (Agent $record): string => (string) $record->address)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('user_instagram')
                    ->label('Instagram')
                    ->icon('heroicon-m-chat-bubble-left-right')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->icon('heroicon-m-signal')
                    ->badge()
                    ->color(fn (mixed $state): string => match ($state) {
                        'ACTIVO' => 'success',
                        'INACTIVO' => 'danger',
                        'POR REVISION' => 'warning',
                        default => 'gray',
                    })
                    ->sortable()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Alta')
                    ->icon('heroicon-m-calendar-days')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estatus')
                    ->options([
                        'ACTIVO' => 'Activo',
                        'INACTIVO' => 'Inactivo',
                        'POR REVISION' => 'Por revisión',
                    ])
                    ->native(false),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Ver ficha')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn (Agent $record): string => AgentResource::getUrl('view', ['record' => $record], true, 'business')),
            ]);
    }
}
