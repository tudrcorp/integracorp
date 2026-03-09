<?php

namespace App\Filament\Marketing\Resources\CollaboratorAnniversaries\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CollaboratorAnniversariesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('rrhhColaborador'))
            ->columns([
                ImageColumn::make('image')
                    ->label('')
                    ->disk('public')
                    ->visibility('public')
                    ->imageHeight('auto')
                    ->imageWidth('25%')
                    ->defaultImageUrl(fn ($record): string => 'https://ui-avatars.com/api/?name='.urlencode($record->rrhhColaborador?->fullName ?? 'N').'&background=0D8ABC&color=fff&size=112'),
                TextColumn::make('rrhhColaborador.fullName')
                    ->label('Colaborador')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->badge()
                    ->color('primary')
                    ->icon('heroicon-m-user'),
                TextColumn::make('created_by')
                    ->label('Creado por')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Creado el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
