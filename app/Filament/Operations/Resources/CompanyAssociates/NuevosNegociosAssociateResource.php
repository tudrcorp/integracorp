<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\CompanyAssociates;

use App\Filament\Business\Resources\CompanyAssociates\Schemas\CompanyAssociateInfolist;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Operations\Resources\CompanyAssociates\Pages\ListCompanyAssociates;
use App\Filament\Operations\Resources\CompanyAssociates\Pages\ViewCompanyAssociate;
use App\Filament\Operations\Resources\CompanyAssociates\Tables\CompanyAssociatesTable;
use App\Models\CompanyAssociate;
use BackedEnum;
use Carbon\Carbon;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class NuevosNegociosAssociateResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = CompanyAssociate::class;

    protected static ?string $slug = 'nuevos-negocios-associates';

    protected static ?string $navigationLabel = 'Nuevos Negocios';

    protected static ?string $pluralModelLabel = 'Asociados de nuevos negocios';

    protected static ?string $modelLabel = 'Asociado de nuevos negocios';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static string|UnitEnum|null $navigationGroup = 'AFILIADOS';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'full_name';

    protected static int $globalSearchResultsLimit = 12;

    protected static ?int $globalSearchSort = 9;

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return [
            'full_name',
            'identity_card',
            'email',
            'phone',
            'vaucher_ils',
            'company.name',
            'company.rif',
            'responsible.full_name',
        ];
    }

    /**
     * @return array<string, \Illuminate\Contracts\Support\Htmlable|string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        if (! $record instanceof CompanyAssociate) {
            return [];
        }

        return [
            'Identificación' => filled($record->identity_card) ? (string) $record->identity_card : '—',
            'Empresa' => filled($record->company?->name) ? (string) $record->company->name : '—',
            'RIF empresa' => filled($record->company?->rif) ? (string) $record->company->rif : '—',
            'Voucher ILS' => filled($record->vaucher_ils) ? (string) $record->vaucher_ils : '—',
            'Vigencia' => static::formatVoucherVigencia($record),
            'Email' => filled($record->email) ? (string) $record->email : '—',
            'Teléfono' => filled($record->phone) ? (string) $record->phone : '—',
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with([
                'company',
                'responsible',
            ]);
    }

    private static function formatVoucherVigencia(CompanyAssociate $record): string
    {
        $start = filled($record->date_init) ? (string) $record->date_init : '—';
        $end = filled($record->date_end) ? (string) $record->date_end : '—';

        if ($start === '—' && $end === '—') {
            return '—';
        }

        return "{$start} → {$end}";
    }

    public static function getNavigationBadge(): ?string
    {
        $todayCount = static::getModel()::query()
            ->whereDate('created_at', Carbon::today())
            ->count();

        return $todayCount > 0 ? "NUEVO {$todayCount}" : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'verdeApple';
    }

    public static function infolist(Schema $schema): Schema
    {
        return CompanyAssociateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CompanyAssociatesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'company',
                'responsible',
                'state',
                'city',
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompanyAssociates::route('/'),
            'view' => ViewCompanyAssociate::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
