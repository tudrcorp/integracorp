<?php

namespace App\Filament\Administration\Resources\RrhhColaboradors\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;

class RrhhColaboradorsTable
{
    private const IOS_PRIMARY_BUTTON_CLASS = 'ticket-btn-ios shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const IOS_DANGER_BUTTON_CLASS = 'aviso-btn-ios-danger shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    public static function configure(Table $table): Table
    {
        return $table
            ->heading('COLABORADORES')
            ->description('Gestión del talento humano con acceso rápido a datos laborales, contacto y estado activo.')
            ->defaultSort('fullName')
            ->emptyStateHeading('No hay colaboradores registrados')
            ->emptyStateDescription('Agrega un colaborador para iniciar la gestión del equipo de RRHH.')
            ->emptyStateIcon('heroicon-o-users')
            ->columns([
                ImageColumn::make('avatar')
                    ->label('')
                    ->circular()
                    ->disk('public')
                    ->visibility('public')
                    ->imageHeight(72)
                    ->imageWidth(72)
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name='.urlencode($record->fullName ?? 'N').'&background=94a3b8&color=fff&size=128'),

                TextColumn::make('fullName')
                    ->label('Nombre completo')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->icon('heroicon-m-user')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('departamento.description')
                    ->label('Departamento')
                    ->searchable(['departamento.description'])
                    ->sortable()
                    ->badge()
                    ->icon('heroicon-m-building-office-2')
                    ->color('gray'),

                TextColumn::make('cargo.description')
                    ->label('Cargo')
                    ->searchable(['cargo.description'])
                    ->sortable()
                    ->badge()
                    ->icon('heroicon-m-briefcase')
                    ->color('info'),

                TextColumn::make('cedula')
                    ->label('Cédula')
                    ->searchable()
                    ->icon('heroicon-m-identification'),

                TextColumn::make('sexo')
                    ->label('Sexo')
                    ->searchable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Masculino' => 'info',
                        'Femenino' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('fechaNacimiento')
                    ->label('F. Nacimiento')
                    ->sortable()
                    ->icon('heroicon-m-cake'),

                TextColumn::make('fechaIngreso')
                    ->label('F. Ingreso')
                    ->sortable()
                    ->icon('heroicon-m-calendar'),

                TextColumn::make('telefono')
                    ->label('Teléfono')
                    ->searchable()
                    ->icon('heroicon-m-phone'),

                TextColumn::make('telefonoCorporativo')
                    ->label('Tel. Corporativo')
                    ->searchable()
                    ->icon('heroicon-m-phone'),

                TextColumn::make('emailCorporativo')
                    ->label('Email Corporativo')
                    ->searchable()
                    ->icon('heroicon-m-envelope')
                    ->copyable(),

                TextColumn::make('emailPersonal')
                    ->label('Email Personal')
                    ->searchable()
                    ->icon('heroicon-m-envelope'),

                TextColumn::make('direccion')
                    ->label('Dirección')
                    ->searchable()
                    ->icon('heroicon-m-map-pin')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('nroHijos')
                    ->label('Hijos')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('tallaCamisa')
                    ->label('Talla')
                    ->badge()
                    ->color('success'),

                TextColumn::make('nroCta')
                    ->label('Nº Cuenta')
                    ->searchable()
                    ->icon('heroicon-m-credit-card')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('tipoCta')
                    ->label('Tipo Cuenta')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextInputColumn::make('sueldo')
                    ->label('Sueldo')
                    ->prefix('US$ ')
                    ->prefixIcon('heroicon-o-currency-dollar')
                    ->rules(['numeric'])
                    ->validationMessages([
                        'numeric' => 'El sueldo debe ser un número',
                    ])
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'activo' => 'success',
                        'inactivo' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => $state === 'activo' ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle'),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

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
                EditAction::make()
                    ->label('Editar')
                    ->icon('heroicon-m-pencil-square')
                    ->color('primary')
                    ->extraAttributes([
                        'class' => self::IOS_PRIMARY_BUTTON_CLASS,
                    ], merge: true),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionados')
                        ->icon('heroicon-m-trash')
                        ->color('danger')
                        ->extraAttributes([
                            'class' => self::IOS_DANGER_BUTTON_CLASS,
                        ], merge: true),
                ]),
            ])
            ->striped();
    }
}
