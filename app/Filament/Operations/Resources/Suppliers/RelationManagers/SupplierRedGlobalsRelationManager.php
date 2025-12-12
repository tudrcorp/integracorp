<?php

namespace App\Filament\Operations\Resources\Suppliers\RelationManagers;

use COM;
use BackedEnum;
use Carbon\Carbon;
use App\Models\City;
use App\Models\State;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Filters\Filter;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\DissociateBulkAction;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Resources\RelationManagers\RelationManager;

class SupplierRedGlobalsRelationManager extends RelationManager
{
    protected static string $relationship = 'supplierRedGlobals';

    protected static ?string $title = 'Red de Sucursales';

    protected static string|BackedEnum|null $icon = 'heroicon-o-building-library';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Formulario de Red de Sucursales')
                ->schema([
                    Select::make('state_id')
                        ->options(State::all()->pluck('definition', 'id'))
                        ->label('Estado')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Select::make('city_id')
                        ->options(fn(Get $get) => City::where('state_id', $get('state_id'))->pluck('definition', 'id'))
                        ->label('Ciudad')
                        ->live()
                        ->searchable()
                        ->preload()
                        ->required(),
                    TextInput::make('name')
                        ->label('Nombre o Razón Social')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('email')
                        ->label('Correo Electrónico')
                        ->required()
                        ->email()
                        ->maxLength(255),
                    TextInput::make('personal_phone')
                        ->label('Teléfono Celular')
                        ->helpertext('Formato de teléfono: 04122346790, sin espacios( ), sin guiones(-).')
                        ->required()
                        ->tel()
                        ->maxLength(255),
                    TextInput::make('local_phone')
                        ->label('Teléfono Local')
                        ->helperText('Formato de teléfono: 02124357898, sin espacios( ), sin guiones(-).')
                        ->required()
                        ->tel()
                        ->maxLength(255),
                    TextInput::make('address')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('created_by')
                        ->default(Auth::User()->name)
                        ->disabled()
                        ->dehydrated()
                        ->required()
                        ->maxLength(255),
                ])->columnSpanFull()->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('supplier_id')
            ->columns([
                TextColumn::make('city.definition')
                    ->label('Ciudad')
                    ->searchable(),
                TextColumn::make('state.definition')
                    ->label('Estado')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Nombre o Razón Social')
                    ->searchable(),
                TextColumn::make('personal_phone')
                    ->label('Telofono Celular')
                    ->searchable(),
                TextColumn::make('local_phone')
                    
                    ->label('Telefono Local')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Correo Electrónico')
                    ->searchable(),
                TextColumn::make('address')
                    ->label('Dirección')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Fecha de Creación')
                    ->searchable(),
                TextColumn::make('updated_at')
                    ->label('Fecha de Actualización')
                    ->searchable(),
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
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['hasta'] ?? null,
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['desde'] ?? null) {
                            $indicators['desde'] = 'Venta desde ' . Carbon::parse($data['desde'])->toFormattedDateString();
                        }
                        if ($data['hasta'] ?? null) {
                            $indicators['hasta'] = 'Venta hasta ' . Carbon::parse($data['hasta'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
            ])
            ->filtersTriggerAction(
                fn(Action $action) => $action
                    ->button()
                    ->label('Filtros'),
            )
            ->headerActions([
                // CreateAction::make()
                //     ->modalHeading('')
                //     ->createAnother(false)
                //     ->label('Agregar Sucursal')
                //     ->icon('heroicon-o-plus'),
                // AssociateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DissociateBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}