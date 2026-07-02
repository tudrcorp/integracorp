<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\CompanyAssociates\Schemas;

use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class CompanyAssociateInfolist
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const IOS_SECTION_CLASS = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('companyAssociateInfolistTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->extraAttributes(['class' => self::TABS_CONTAINER])
                    ->tabs([
                        Tab::make('Asociado')
                            ->icon(Heroicon::OutlinedUser)
                            ->schema([self::associateSection()]),
                        Tab::make('Relaciones')
                            ->icon(Heroicon::OutlinedBuildingOffice2)
                            ->schema([self::relationsSection()]),
                        Tab::make('Contacto')
                            ->icon(Heroicon::OutlinedPhone)
                            ->schema([self::contactSection()]),
                        Tab::make('Documento')
                            ->icon(Heroicon::OutlinedIdentification)
                            ->schema([self::documentSection()]),
                        Tab::make('Voucher ILS')
                            ->icon(Heroicon::OutlinedTicket)
                            ->schema([self::voucherIlsSection()]),
                        Tab::make('Registro')
                            ->icon(Heroicon::OutlinedClock)
                            ->schema([self::registrationSection()]),
                    ]),
            ]);
    }

    private static function associateSection(): Section
    {
        return Section::make('Datos del asociado')
            ->icon(Heroicon::OutlinedUser)
            ->extraAttributes(['class' => self::IOS_SECTION_CLASS])
            ->schema([
                Grid::make(1)->extraAttributes(['class' => self::IOS_INNER_CLASS])->schema([
                    Grid::make()->columns(['default' => 1, 'lg' => 3])->schema([
                        TextEntry::make('full_name')->label('Nombre y Apellido')->weight('semibold')->columnSpan(['default' => 1, 'lg' => 2]),
                        TextEntry::make('identity_card')->label('Cédula')->badge()->color('gray')->copyable(),
                        TextEntry::make('birth_date')->label('Fecha de nacimiento')->date('d/m/Y'),
                        TextEntry::make('age')->label('Edad')->suffix(' años')->badge()->color('info'),
                        TextEntry::make('sex')->label('Sexo')->badge()->color('gray'),
                        TextEntry::make('email')->label('Correo')->icon(Heroicon::OutlinedEnvelope)->copyable()->placeholder('—'),
                        TextEntry::make('phone')->label('Teléfono')->icon(Heroicon::OutlinedPhone)->copyable()->placeholder('—'),
                    ]),
                ]),
            ]);
    }

    private static function relationsSection(): Section
    {
        return Section::make('Empresa y responsable')
            ->icon(Heroicon::OutlinedBuildingOffice2)
            ->extraAttributes(['class' => self::IOS_SECTION_CLASS])
            ->schema([
                Grid::make(1)->extraAttributes(['class' => self::IOS_INNER_CLASS])->schema([
                    Grid::make()->columns(['default' => 1, 'lg' => 2])->schema([
                        TextEntry::make('company.name')->label('Empresa')->weight('semibold'),
                        TextEntry::make('company.rif')->label('RIF empresa')->badge()->color('gray'),
                        TextEntry::make('responsible.full_name')->label('Responsable')->icon(Heroicon::OutlinedUserCircle),
                        TextEntry::make('responsible.identity_card')->label('Cédula responsable')->badge()->color('gray'),
                    ]),
                ]),
            ]);
    }

    private static function contactSection(): Section
    {
        return Section::make('Contacto de emergencia')
            ->icon(Heroicon::OutlinedPhoneArrowUpRight)
            ->extraAttributes(['class' => self::IOS_SECTION_CLASS])
            ->schema([
                Grid::make(1)->extraAttributes(['class' => self::IOS_INNER_CLASS])->schema([
                    Grid::make()->columns(['default' => 1, 'lg' => 3])->schema([
                        TextEntry::make('contact_full_name')->label('Nombre y Apellido'),
                        TextEntry::make('contact_phone')->label('Teléfono')->copyable()->placeholder('—'),
                        TextEntry::make('contact_email')->label('Correo')->copyable()->placeholder('—'),
                    ]),
                ]),
            ]);
    }

    private static function documentSection(): Section
    {
        return Section::make('Documento de identidad')
            ->icon(Heroicon::OutlinedIdentification)
            ->extraAttributes(['class' => self::IOS_SECTION_CLASS])
            ->schema([
                Grid::make(1)->extraAttributes(['class' => self::IOS_INNER_CLASS])->schema([
                    ImageEntry::make('identity_document')
                        ->label('Imagen cargada')
                        ->disk('public')
                        ->height(320),
                ]),
            ]);
    }

    private static function voucherIlsSection(): Section
    {
        return Section::make('Voucher ILS')
            ->description('Cobertura ILS asignada al asociado.')
            ->icon(Heroicon::OutlinedTicket)
            ->extraAttributes(['class' => self::IOS_SECTION_CLASS])
            ->schema([
                Grid::make(1)->extraAttributes(['class' => self::IOS_INNER_CLASS])->schema([
                    Grid::make()->columns(['default' => 1, 'lg' => 3])->schema([
                        TextEntry::make('vaucher_ils')
                            ->label('Código')
                            ->badge()
                            ->color('info')
                            ->placeholder('Sin voucher asignado'),
                        TextEntry::make('date_init')
                            ->label('Fecha de inicio')
                            ->placeholder('—'),
                        TextEntry::make('date_end')
                            ->label('Fecha fin')
                            ->placeholder('—'),
                    ]),
                    ImageEntry::make('document_ils')
                        ->label('Imagen del voucher')
                        ->disk('public')
                        ->height(320)
                        ->visible(fn ($record): bool => filled($record->document_ils)),
                ]),
            ]);
    }

    private static function registrationSection(): Section
    {
        return Section::make('Trazabilidad del registro')
            ->description('Fecha y hora exactas capturadas al momento del registro público.')
            ->icon(Heroicon::OutlinedClock)
            ->extraAttributes(['class' => self::IOS_SECTION_CLASS])
            ->schema([
                Grid::make(1)->extraAttributes(['class' => self::IOS_INNER_CLASS])->schema([
                    Grid::make()->columns(['default' => 1, 'lg' => 2])->schema([
                        TextEntry::make('registered_at')
                            ->label('Registrado el')
                            ->dateTime('d/m/Y H:i:s')
                            ->badge()
                            ->color('success'),
                        TextEntry::make('created_at')
                            ->label('Creado en sistema')
                            ->dateTime('d/m/Y H:i:s'),
                    ]),
                ]),
            ]);
    }
}
