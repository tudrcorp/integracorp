<?php

namespace App\Filament\Business\Resources\BusinessAppointments;

use App\Filament\Business\Resources\BusinessAppointments\Pages\CreateBusinessAppointments;
use App\Filament\Business\Resources\BusinessAppointments\Pages\EditBusinessAppointments;
use App\Filament\Business\Resources\BusinessAppointments\Pages\ListBusinessAppointments;
use App\Filament\Business\Resources\BusinessAppointments\Pages\ViewBusinessAppointments;
use App\Filament\Business\Resources\BusinessAppointments\Schemas\BusinessAppointmentsForm;
use App\Filament\Business\Resources\BusinessAppointments\Schemas\BusinessAppointmentsInfolist;
use App\Filament\Business\Resources\BusinessAppointments\Tables\BusinessAppointmentsTable;
use App\Models\BusinessAppointments;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BusinessAppointmentsResource extends Resource
{
    protected static ?string $model = BusinessAppointments::class;

    protected static ?string $navigationLabel = 'Citas';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar';

    protected static string | UnitEnum | null $navigationGroup = 'ESTRUCTURA COMERCIAL';

    protected static ?int $navigationSort = 7;

    public static function form(Schema $schema): Schema
    {
        return BusinessAppointmentsForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return BusinessAppointmentsInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BusinessAppointmentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBusinessAppointments::route('/'),
            'create' => CreateBusinessAppointments::route('/create'),
            'view' => ViewBusinessAppointments::route('/{record}'),
            'edit' => EditBusinessAppointments::route('/{record}/edit'),
        ];
    }
}
