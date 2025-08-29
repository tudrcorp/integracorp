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
                ImageColumn::make('image')
                    ->label('Foto de Perfil')
                    ->circular()
                    ->imageHeight(70),
                TextColumn::make('full_name')
                    ->label('Nomnbre Completo')
                    ->description(fn ($record): string => 'V-' . $record->nro_identificacion)
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Correo Electrónico')
                    ->description(fn ($record): string => $record->phone)
                    ->searchable(),
                TextColumn::make('code_mpps')
                    ->label('Códigos')
                    ->prefix('MPPS: ')
                    ->description(fn ($record): string => 'CM: '.$record->code_cm)
                    ->searchable(),
                
                TextColumn::make('specialty')
                    ->label('Especialidad')
                    ->searchable(),

                ImageColumn::make('signature')
                    ->alignCenter()
                    ->label('Firma Digital')
                    ->imageHeight(100)
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Fecha de Registro')
                    ->dateTime()
                    ->sortable(),
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