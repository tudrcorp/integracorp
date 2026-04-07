<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\AffiliateCorporates\Schemas;

use App\Models\AffiliateCorporate;
use Carbon\Carbon;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class AffiliateCorporateInfolist
{
    private const IOS_SECTION_CLASS = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-2xl border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    private static function affiliateFullName(AffiliateCorporate $record): string
    {
        return trim((string) ($record->first_name ?? '').' '.(string) ($record->last_name ?? '')) ?: '—';
    }

    private static function statusColor(?string $state): string
    {
        return match (strtoupper((string) $state)) {
            'ACTIVO', 'ACTIVA' => 'success',
            'PENDIENTE' => 'warning',
            'EXCLUIDO', 'INACTIVO' => 'danger',
            default => 'gray',
        };
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Afiliado corporativo')
                    ->description(fn (AffiliateCorporate $record): string => self::affiliateFullName($record).' · '.$record->age.' años · '.(string) ($record->sex ?? '—'))
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make(1)
                            ->extraAttributes([
                                'class' => self::IOS_INNER_CLASS,
                            ])
                            ->schema([
                                TextEntry::make('first_name')
                                    ->label('Nombre')
                                    ->size('lg')
                                    ->weight('semibold')
                                    ->color('gray')
                                    ->placeholder('—'),
                                Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                                    ->schema([
                                        TextEntry::make('last_name')
                                            ->label('Apellido')
                                            ->weight('medium')
                                            ->placeholder('—'),
                                        TextEntry::make('nro_identificacion')
                                            ->label('Identificación')
                                            ->prefix('V-')
                                            ->icon(Heroicon::OutlinedIdentification)
                                            ->copyable()
                                            ->badge()
                                            ->color('success'),
                                        TextEntry::make('birth_date')
                                            ->label('Fecha de nacimiento')
                                            ->icon(Heroicon::OutlinedCalendarDays)
                                            ->formatStateUsing(function (mixed $state): ?string {
                                                if (blank($state)) {
                                                    return null;
                                                }
                                                try {
                                                    return Carbon::parse($state)->format('d/m/Y');
                                                } catch (\Throwable) {
                                                    return (string) $state;
                                                }
                                            })
                                            ->placeholder('—'),
                                        TextEntry::make('age')
                                            ->label('Edad')
                                            ->suffix(' años')
                                            ->alignCenter(),
                                        TextEntry::make('sex')
                                            ->label('Sexo')
                                            ->badge()
                                            ->color('gray'),
                                        TextEntry::make('phone')
                                            ->label('Teléfono')
                                            ->icon(Heroicon::OutlinedPhone)
                                            ->copyable(),
                                        TextEntry::make('email')
                                            ->label('Correo')
                                            ->icon(Heroicon::OutlinedEnvelope)
                                            ->copyable()
                                            ->wrap(),
                                        TextEntry::make('address')
                                            ->label('Dirección')
                                            ->icon(Heroicon::OutlinedMapPin)
                                            ->columnSpan(['default' => 1, 'lg' => 2])
                                            ->wrap(),
                                        TextEntry::make('created_at')
                                            ->label('Registro')
                                            ->icon(Heroicon::OutlinedClock)
                                            ->dateTime('d/m/Y H:i'),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Emergencia')
                    ->icon(Heroicon::OutlinedExclamationTriangle)
                    ->collapsed()
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])
                            ->extraAttributes([
                                'class' => self::IOS_INNER_CLASS,
                            ])
                            ->schema([
                                TextEntry::make('full_name_emergency')
                                    ->label('Contacto')
                                    ->icon(Heroicon::OutlinedUser)
                                    ->placeholder('—'),
                                TextEntry::make('phone_emergency')
                                    ->label('Teléfono')
                                    ->icon(Heroicon::OutlinedPhone)
                                    ->copyable()
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Corporativo')
                    ->description('Empresa contratante.')
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                            ->extraAttributes([
                                'class' => self::IOS_INNER_CLASS,
                            ])
                            ->schema([
                                TextEntry::make('affiliationCorporate.name_corporate')
                                    ->label('Razón social')
                                    ->icon(Heroicon::OutlinedBuildingLibrary)
                                    ->weight('medium')
                                    ->badge()
                                    ->color('primary')
                                    ->placeholder('—'),
                                TextEntry::make('affiliationCorporate.rif')
                                    ->label('RIF')
                                    ->icon(Heroicon::OutlinedIdentification)
                                    ->copyable()
                                    ->badge()
                                    ->color('info')
                                    ->placeholder('—'),
                                TextEntry::make('affiliationCorporate.phone')
                                    ->label('Teléfono')
                                    ->icon(Heroicon::OutlinedPhone)
                                    ->copyable()
                                    ->placeholder('—'),
                                TextEntry::make('affiliationCorporate.email')
                                    ->label('Correo')
                                    ->icon(Heroicon::OutlinedEnvelope)
                                    ->copyable()
                                    ->placeholder('—')
                                    ->columnSpan(['default' => 1, 'lg' => 2]),
                                TextEntry::make('affiliationCorporate.address')
                                    ->label('Dirección')
                                    ->icon(Heroicon::OutlinedMapPin)
                                    ->columnSpanFull()
                                    ->wrap(),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Afiliación y plan')
                    ->icon(Heroicon::OutlinedRectangleStack)
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                            ->extraAttributes([
                                'class' => self::IOS_INNER_CLASS,
                            ])
                            ->schema([
                                TextEntry::make('affiliationCorporate.code')
                                    ->label('Código afiliación')
                                    ->icon(Heroicon::OutlinedHashtag)
                                    ->badge()
                                    ->color('primary'),
                                TextEntry::make('plan.description')
                                    ->label('Plan')
                                    ->badge()
                                    ->color('primary')
                                    ->wrap(),
                                TextEntry::make('plan.businessUnit.definition')
                                    ->label('Unidad de negocio')
                                    ->badge()
                                    ->color('gray'),
                                TextEntry::make('coverage.price')
                                    ->label('Cobertura')
                                    ->money('USD'),
                                TextEntry::make('affiliationCorporate.effective_date')
                                    ->label('Vigencia')
                                    ->icon(Heroicon::OutlinedCalendarDays)
                                    ->placeholder('—'),
                                TextEntry::make('affiliationCorporate.service_providers')
                                    ->label('Proveedores')
                                    ->formatStateUsing(function (mixed $state): ?string {
                                        if (blank($state)) {
                                            return null;
                                        }
                                        if (is_array($state)) {
                                            return implode(', ', array_filter(array_map('strval', $state)));
                                        }

                                        return (string) $state;
                                    })
                                    ->placeholder('—')
                                    ->columnSpan(['default' => 1, 'lg' => 3])
                                    ->wrap(),
                                TextEntry::make('status')
                                    ->label('Estatus afiliado')
                                    ->badge()
                                    ->color(fn (?string $state): string => self::statusColor($state)),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Contacto en empresa')
                    ->icon(Heroicon::OutlinedPhone)
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                            ->extraAttributes([
                                'class' => self::IOS_INNER_CLASS,
                            ])
                            ->schema([
                                TextEntry::make('affiliationCorporate.full_name_contact')
                                    ->label('Nombre')
                                    ->icon(Heroicon::OutlinedUser)
                                    ->placeholder('—'),
                                TextEntry::make('affiliationCorporate.nro_identificacion_contact')
                                    ->label('Identificación')
                                    ->copyable()
                                    ->placeholder('—'),
                                TextEntry::make('affiliationCorporate.email_contact')
                                    ->label('Correo')
                                    ->icon(Heroicon::OutlinedEnvelope)
                                    ->copyable()
                                    ->placeholder('—'),
                                TextEntry::make('affiliationCorporate.phone_contact')
                                    ->label('Teléfono')
                                    ->icon(Heroicon::OutlinedPhone)
                                    ->copyable()
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Beneficios del plan')
                    ->icon(Heroicon::OutlinedQueueList)
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'lg' => 2])
                            ->extraAttributes([
                                'class' => self::IOS_INNER_CLASS,
                            ])
                            ->schema([
                                TextEntry::make('plan.benefitPlans.description')
                                    ->label('Beneficios')
                                    ->badge()
                                    ->color('success')
                                    ->listWithLineBreaks()
                                    ->columnSpan(1),
                                TextEntry::make('plan.benefitPlans.limit.description')
                                    ->label('Límites por beneficio')
                                    ->badge()
                                    ->color('gray')
                                    ->listWithLineBreaks()
                                    ->columnSpan(1),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
