<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineFollowUps;

use App\Filament\Telemedicina\Resources\TelemedicineFollowUps\Pages\CreateTelemedicineFollowUp;
use App\Filament\Telemedicina\Resources\TelemedicineFollowUps\Pages\EditTelemedicineFollowUp;
use App\Filament\Telemedicina\Resources\TelemedicineFollowUps\Pages\ListTelemedicineFollowUps;
use App\Filament\Telemedicina\Resources\TelemedicineFollowUps\Pages\ViewTelemedicineFollowUp;
use App\Filament\Telemedicina\Resources\TelemedicineFollowUps\Schemas\TelemedicineFollowUpForm;
use App\Filament\Telemedicina\Resources\TelemedicineFollowUps\Schemas\TelemedicineFollowUpInfolist;
use App\Filament\Telemedicina\Resources\TelemedicineFollowUps\Tables\TelemedicineFollowUpsTable;
use App\Models\TelemedicineFollowUp;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TelemedicineFollowUpResource extends Resource
{
    protected static ?string $model = TelemedicineFollowUp::class;

    protected static string|BackedEnum|null $navigationIcon = 'healthicons-f-i-note-action';

    protected static ?string $navigationLabel = 'Seguimiento de Casos';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return TelemedicineFollowUpForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TelemedicineFollowUpInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TelemedicineFollowUpsTable::configure($table);
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
            'index' => ListTelemedicineFollowUps::route('/'),
            'create' => CreateTelemedicineFollowUp::route('/create'),
            'view' => ViewTelemedicineFollowUp::route('/{record}'),
            'edit' => EditTelemedicineFollowUp::route('/{record}/edit'),
        ];
    }
}