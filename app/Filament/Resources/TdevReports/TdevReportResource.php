<?php

namespace App\Filament\Resources\TdevReports;

use App\Filament\Resources\TdevReports\Pages\CreateTdevReport;
use App\Filament\Resources\TdevReports\Pages\EditTdevReport;
use App\Filament\Resources\TdevReports\Pages\ListTdevReports;
use App\Filament\Resources\TdevReports\Pages\ViewTdevReport;
use App\Filament\Resources\TdevReports\Schemas\TdevReportForm;
use App\Filament\Resources\TdevReports\Schemas\TdevReportInfolist;
use App\Filament\Resources\TdevReports\Tables\TdevReportsTable;
use App\Models\TdevReport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TdevReportResource extends Resource
{
    protected static ?string $model = TdevReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string | UnitEnum | null $navigationGroup = 'TDEV';

    public static function form(Schema $schema): Schema
    {
        return TdevReportForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TdevReportInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TdevReportsTable::configure($table);
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
            'index' => ListTdevReports::route('/'),
            'create' => CreateTdevReport::route('/create'),
            'view' => ViewTdevReport::route('/{record}'),
            'edit' => EditTdevReport::route('/{record}/edit'),
        ];
    }
}