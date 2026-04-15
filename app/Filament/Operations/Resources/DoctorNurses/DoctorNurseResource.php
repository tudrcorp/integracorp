<?php

namespace App\Filament\Operations\Resources\DoctorNurses;

use App\Filament\Operations\Resources\DoctorNurses\Pages\CreateDoctorNurse;
use App\Filament\Operations\Resources\DoctorNurses\Pages\EditDoctorNurse;
use App\Filament\Operations\Resources\DoctorNurses\Pages\ListDoctorNurses;
use App\Filament\Operations\Resources\DoctorNurses\Pages\ViewDoctorNurse;
use App\Filament\Operations\Resources\DoctorNurses\Schemas\DoctorNurseForm;
use App\Filament\Operations\Resources\DoctorNurses\Schemas\DoctorNurseInfolist;
use App\Filament\Operations\Resources\DoctorNurses\Tables\DoctorNursesTable;
use App\Models\DoctorNurse;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DoctorNurseResource extends Resource
{
    protected static ?string $model = DoctorNurse::class;

    protected static ?string $navigationLabel = 'Proveedores Naturales';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    // sort
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return DoctorNurseForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DoctorNurseInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DoctorNursesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['supplierClasificacion']);
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
            'index' => ListDoctorNurses::route('/'),
            'create' => CreateDoctorNurse::route('/create'),
            'view' => ViewDoctorNurse::route('/{record}'),
            'edit' => EditDoctorNurse::route('/{record}/edit'),
        ];
    }
}
