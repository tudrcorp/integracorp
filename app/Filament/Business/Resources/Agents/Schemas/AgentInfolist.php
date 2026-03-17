<?php

namespace App\Filament\Business\Resources\Agents\Schemas;

use App\Models\Agent;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class AgentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos del agente')
                    ->collapsed(false)
                    ->description('Información principal e identificación del agente')
                    ->icon('heroicon-o-user-circle')
                    ->schema([
                        Fieldset::make()
                            ->schema([
                                Grid::make(5)
                                    ->schema([
                                        TextEntry::make('name')
                                            ->label('Nombre')
                                            ->weight('semibold')
                                            ->placeholder('-'),
                                        TextEntry::make('email')
                                            ->label('Correo electrónico')
                                            ->placeholder('-')
                                            ->copyable()
                                            ->copyMessage('Correo copiado'),
                                        TextEntry::make('phone')
                                            ->label('Teléfono')
                                            ->placeholder('-')
                                            ->copyable(),
                                        TextEntry::make('owner_code')
                                            ->label('Código propietario')
                                            ->placeholder('-'),
                                        TextEntry::make('status')
                                            ->label('Estado')
                                            ->badge()
                                            ->color(fn (string $state): string => match (strtoupper((string) $state)) {
                                                'ACTIVO', 'ACTIVA' => 'success',
                                                default => 'gray',
                                            })
                                            ->placeholder('-'),
                                    ])->columnSpanFull(),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Identificación y ubicación')
                    ->collapsed(true)
                    ->description('Documento, fecha de nacimiento y ubicación')
                    ->icon('heroicon-o-map-pin')
                    ->schema([
                        Fieldset::make()
                            ->schema([
                                Grid::make(5)
                                    ->schema([
                                        TextEntry::make('ci')
                                            ->label('C.I.')
                                            ->placeholder('-'),
                                        TextEntry::make('rif')
                                            ->label('R.I.F.')
                                            ->placeholder('-'),
                                        TextEntry::make('birth_date')
                                            ->label('Fecha de nacimiento')
                                            ->date('d/m/Y')
                                            ->placeholder('-'),
                                        TextEntry::make('sex')
                                            ->label('Sexo')
                                            ->placeholder('-'),
                                        TextEntry::make('marital_status')
                                            ->label('Estado civil')
                                            ->placeholder('-'),
                                        TextEntry::make('country.name')
                                            ->label('País')
                                            ->placeholder('-'),
                                        TextEntry::make('state.id')
                                            ->label('Estado')
                                            ->placeholder('-'),
                                        TextEntry::make('city.id')
                                            ->label('Ciudad')
                                            ->placeholder('-'),
                                        TextEntry::make('region')
                                            ->label('Región')
                                            ->placeholder('-'),
                                        TextEntry::make('address')
                                            ->label('Dirección')
                                            ->placeholder('-')
                                            ->columnSpanFull(),
                                    ])->columnSpanFull(),
                            ])->columnSpanFull(),
                    ])->columnSpanFull(),

                Section::make('Contacto secundario')
                    ->collapsed(true)
                    ->description('Datos de contacto alternativo')
                    ->icon('heroicon-o-phone')
                    ->schema([
                        Fieldset::make()
                            ->schema([
                                Grid::make(5)
                                    ->schema([
                                        TextEntry::make('name_contact_2')
                                            ->label('Nombre')
                                            ->placeholder('-'),
                                        TextEntry::make('email_contact_2')
                                            ->label('Correo')
                                            ->placeholder('-'),
                                        TextEntry::make('phone_contact_2')
                                            ->label('Teléfono')
                                            ->placeholder('-'),
                                        TextEntry::make('user_instagram')
                                            ->label('Instagram')
                                            ->placeholder('-'),
                                    ])->columnSpanFull(),
                            ])->columnSpanFull(),
                    ])->columnSpanFull(),

                Section::make('Datos bancarios (local)')
                    ->collapsed(true)
                    ->description('Beneficiario en moneda local')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        Fieldset::make()
                            ->schema([
                                Grid::make(5)
                                    ->schema([
                                        TextEntry::make('local_beneficiary_name')
                                            ->label('Nombre beneficiario')
                                            ->placeholder('-')
                                            ->columnSpan(1),
                                        TextEntry::make('local_beneficiary_rif')
                                            ->label('R.I.F.')
                                            ->placeholder('-')
                                            ->columnSpan(1),
                                        TextEntry::make('local_beneficiary_account_number')
                                            ->label('Nº cuenta')
                                            ->placeholder('-')
                                            ->columnSpan(1),
                                        TextEntry::make('local_beneficiary_account_bank')
                                            ->label('Banco')
                                            ->placeholder('-')
                                            ->columnSpan(1),
                                        TextEntry::make('local_beneficiary_account_type')
                                            ->label('Tipo de cuenta')
                                            ->placeholder('-')
                                            ->columnSpan(1),
                                        TextEntry::make('local_beneficiary_phone_pm')
                                            ->label('Teléfono')
                                            ->placeholder('-')
                                            ->columnSpan(1),
                                    ])->columnSpanFull(),
                            ])->columnSpanFull(),
                    ])->columnSpanFull(),

                Section::make('Datos bancarios (moneda extranjera)')
                    ->collapsed(true)
                    ->description('Beneficiario para pagos en USD u otra moneda')
                    ->icon('heroicon-o-currency-dollar')
                    ->schema([
                        Fieldset::make()
                            ->schema([
                                Grid::make(5)
                                    ->schema([
                                        TextEntry::make('extra_beneficiary_name')
                                            ->label('Nombre beneficiario')
                                            ->placeholder('-')
                                            ->columnSpan(1),
                                        TextEntry::make('extra_beneficiary_ci_rif')
                                            ->label('C.I./R.I.F.')
                                            ->placeholder('-')
                                            ->columnSpan(1),
                                        TextEntry::make('extra_beneficiary_account_number')
                                            ->label('Nº cuenta')
                                            ->placeholder('-')
                                            ->columnSpan(1),
                                        TextEntry::make('extra_beneficiary_account_bank')
                                            ->label('Banco')
                                            ->placeholder('-')
                                            ->columnSpan(1),
                                        TextEntry::make('extra_beneficiary_zelle')
                                            ->label('Zelle')
                                            ->placeholder('-')
                                            ->columnSpan(1),
                                        TextEntry::make('extra_beneficiary_route')
                                            ->label('Route')
                                            ->placeholder('-')
                                            ->columnSpan(1),
                                        TextEntry::make('extra_beneficiary_address')
                                            ->label('Dirección')
                                            ->placeholder('-')
                                            ->columnSpanFull(),
                                    ])->columnSpanFull(),
                            ])
                            ->columnSpanFull(),
                    ])->columnSpanFull(),

                Section::make('Comisiones y opciones')
                    ->collapsed(true)
                    ->description('Porcentajes de comisión por producto: Tu Dr. En Casa (TDEC) y Tu Dr. En Viajes (TDEV), para venta nueva y renovación.')
                    ->icon('heroicon-o-calculator')
                    ->schema([
                        Fieldset::make('Tu Dr. En Casa (TDEC)')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('commission_tdec')
                                            ->label('Comisión venta nueva')
                                            ->numeric(decimalPlaces: 2)
                                            ->suffix('%')
                                            ->placeholder('-')
                                            ->weight('medium'),
                                        TextEntry::make('commission_tdec_renewal')
                                            ->label('Comisión renovación')
                                            ->numeric(decimalPlaces: 2)
                                            ->suffix('%')
                                            ->placeholder('-')
                                            ->weight('medium'),
                                    ])
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),
                        Fieldset::make('Tu Dr. En Viajes (TDEV)')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('commission_tdev')
                                            ->label('Comisión venta nueva')
                                            ->numeric(decimalPlaces: 2)
                                            ->suffix('%')
                                            ->placeholder('-')
                                            ->weight('medium'),
                                        TextEntry::make('commission_tdev_renewal')
                                            ->label('Comisión renovación')
                                            ->numeric(decimalPlaces: 2)
                                            ->suffix('%')
                                            ->placeholder('-')
                                            ->weight('medium'),
                                    ])
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                    

                Section::make('Auditoría y otros')
                    ->collapsed(true)
                    ->description('Registro de creación y observaciones')
                    ->icon('heroicon-o-flag')
                    ->schema([
                        Fieldset::make()
                            ->schema([
                                Grid::make(5)
                                    ->schema([
                                        TextEntry::make('created_by')
                                            ->label('Creado por')
                                            ->placeholder('-'),
                                        TextEntry::make('created_at')
                                            ->label('Fecha de creación')
                                            ->dateTime('d/m/Y H:i')
                                            ->placeholder('-'),
                                        TextEntry::make('updated_at')
                                            ->label('Última actualización')
                                            ->dateTime('d/m/Y H:i')
                                            ->placeholder('-'),
                                        TextEntry::make('date_register')
                                            ->label('Fecha de registro')
                                            ->placeholder('-'),
                                        TextEntry::make('comments')
                                            ->label('Comentarios')
                                            ->placeholder('-')
                                            ->columnSpanFull(),
                                    ])->columnSpanFull(),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull()
                    ->collapsible(),

                Section::make('Afiliaciones asociadas')
                    ->description(fn (Agent $record): HtmlString => new HtmlString(
                        'Afiliaciones en las que el agente figura como agente asignado (agent_id). '
                            .'Total: <span class="inline-flex shrink-0 items-center justify-center rounded-full bg-primary/15 px-2.5 py-0.5 text-sm font-semibold text-primary shadow-sm ring-1 ring-primary/20">'
                            .(int) ($record->affiliations?->count() ?? 0)
                            .'</span> afiliación(es).'
                    ))
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        RepeatableEntry::make('affiliations')
                            ->formatStateUsing(fn ($state) => $state ?? collect())
                            ->label('')
                            ->placeholder('No hay afiliaciones asociadas a este agente.')
                            ->table([
                                RepeatableEntry\TableColumn::make('Nº solicitud'),
                                RepeatableEntry\TableColumn::make('Titular del plan'),
                                RepeatableEntry\TableColumn::make('Plan afiliado'),
                                RepeatableEntry\TableColumn::make('Estado'),
                                RepeatableEntry\TableColumn::make('Fecha'),
                            ])
                            ->schema([
                                TextEntry::make('code')
                                    ->badge()
                                    ->color('success'),
                                TextEntry::make('full_name_ti'),
                                TextEntry::make('plan.description')
                                    ->label('Plan afiliado')
                                    ->placeholder('-'),
                                TextEntry::make('status')
                                    ->badge()
                                    ->color(fn (string $state): string => match (strtoupper((string) $state)) {
                                        'ACTIVA', 'ACTIVO' => 'success',
                                        'PENDIENTE' => 'warning',
                                        default => 'gray',
                                    }),
                                TextEntry::make('created_at')
                                    ->dateTime('d/m/Y H:i'),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
