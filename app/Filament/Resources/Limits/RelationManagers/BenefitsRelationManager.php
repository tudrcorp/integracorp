<?php

namespace App\Filament\Resources\Limits\RelationManagers;

use Carbon\Carbon;
use App\Models\Limit;
use App\Models\Benefit;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Filters\Filter;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Filament\Schemas\Components\Utilities\Set;
use App\Filament\Resources\Limits\LimitResource;
use Filament\Resources\RelationManagers\RelationManager;

class BenefitsRelationManager extends RelationManager
{
    protected static string $relationship = 'benefits';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
            Section::make('BENEFICIOS')
                ->description('Formulario para el registro de los beneficios asociados a los planes. Campo Requerido(*)')
                ->icon('heroicon-s-share')
                ->schema([
                    Grid::make()->schema([
                        TextInput::make('code')
                            ->label('Código')
                            ->prefixIcon('heroicon-m-clipboard-document-check')
                            ->default(function () {
                                if (Benefit::max('id') == null) {
                                    $parte_entera = 0;
                                } else {
                                    $parte_entera = Benefit::max('id');
                                }
                                return 'TDEC-BN-000' . $parte_entera + 1;
                            })
                            ->required()
                            ->disabled()
                            ->dehydrated()
                            ->maxLength(255),
                    ])->columnSpanFull()->columns(3),
                    TextInput::make('description')
                        ->label('Definición')
                        ->prefixIcon('heroicon-m-pencil')
                        ->afterStateUpdated(function (Set $set, $state) {
                            $set('description', strtoupper($state));
                        })
                        ->live(onBlur: true)
                        ->required()
                        ->maxLength(255),
                    Select::make('limit_id')
                        ->relationship('limit', 'description')
                        ->searchable()
                        ->preload()
                        ->createOptionForm([
                            Section::make('LIMITES')
                                ->description('Formulario para el registro de los limites asociados a los beneficios de planes. Campo Requerido(*)')
                                ->icon('heroicon-c-adjustments-horizontal')
                                ->schema([
                                    Grid::make(3)->schema([
                                        TextInput::make('code')
                                            ->label('Código')
                                            ->prefixIcon('heroicon-m-clipboard-document-check')
                                            ->default(function () {
                                                if (Limit::max('id') == null) {
                                                    $parte_entera = 0;
                                                } else {
                                                    $parte_entera = Limit::max('id');
                                                }
                                                return 'TDEC-BN-000' . $parte_entera + 1;
                                            })
                                            ->required()
                                            ->disabled()
                                            ->dehydrated()
                                            ->maxLength(255),
                                    ]),
                                    TextInput::make('description')
                                        ->label('Definición')
                                        ->prefixIcon('heroicon-m-pencil')
                                        ->afterStateUpdated(function (Set $set, $state) {
                                            $set('description', strtoupper($state));
                                        })
                                        ->live(onBlur: true)
                                        ->required()
                                        ->maxLength(255),
                                    TextInput::make('status')
                                        ->label('Estatus')
                                        ->prefixIcon('heroicon-m-shield-check')
                                        ->disabled()
                                        ->dehydrated()
                                        ->maxLength(255)
                                        ->default('ACTIVO'),
                                    TextInput::make('created_by')
                                        ->label('Creado Por:')
                                        ->prefixIcon('heroicon-s-user-circle')
                                        ->disabled()
                                        ->dehydrated()
                                        ->default(Auth::user()->name)
                                        ->maxLength(255),
                                ])->columns(3),
                        ]),
                    TextInput::make('status')
                        ->label('Estatus')
                        ->prefixIcon('heroicon-m-shield-check')
                        ->disabled()
                        ->dehydrated()
                        ->maxLength(255)
                        ->default('ACTIVO'),
                    TextInput::make('created_by')
                        ->label('Creado Por:')
                        ->prefixIcon('heroicon-s-user-circle')
                        ->disabled()
                        ->dehydrated()
                        ->default(Auth::user()->name)
                        ->maxLength(255),
                ])->columnSpanFull()->columns(3),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('limit_id')
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->badge()
                    ->color('azulOscuro')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('description')
                    ->label('Definición')
                    ->badge()
                    ->color('verde')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color(function (mixed $state): string {
                        return match ($state) {
                            'ACTIVO' => 'success',
                            'INACTIVO' => 'danger',
                        };
                    })
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('created_by')
                    ->label('Creado Por:')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('created_at')
                    ->label('Fecha de Creación')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Fecha de Actualización')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            ->recordActions([
                ActionGroup::make([
                    DeleteAction::make()
                        ->label('Eliminar')
                        ->icon('heroicon-o-trash')
                        ->requiresConfirmation()
                        ->color('danger'),

                ])->icon('heroicon-c-ellipsis-vertical')->color('azulOscuro')
            ])
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}