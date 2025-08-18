<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineDoctors\Tables;

use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use App\Models\TelemedicineDoctor;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;

class TelemedicineDoctorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(TelemedicineDoctor::query()->where('id', Auth::user()->doctor_id))
            ->columns([
                TextColumn::make('first_name')
                    ->searchable(),
                TextColumn::make('last_name')
                    ->searchable(),
                TextColumn::make('nro_identificacion')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('code_cm')
                    ->searchable(),
                TextColumn::make('code_mpps')
                    ->searchable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('specialty')
                    ->searchable(),
                TextColumn::make('address')
                    ->searchable(),
                ImageColumn::make('image'),
                TextColumn::make('signature')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}