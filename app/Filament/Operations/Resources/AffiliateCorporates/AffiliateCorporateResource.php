<?php

namespace App\Filament\Operations\Resources\AffiliateCorporates;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Operations\Resources\AffiliateCorporates\Pages\CreateAffiliateCorporate;
use App\Filament\Operations\Resources\AffiliateCorporates\Pages\EditAffiliateCorporate;
use App\Filament\Operations\Resources\AffiliateCorporates\Pages\ListAffiliateCorporates;
use App\Filament\Operations\Resources\AffiliateCorporates\Pages\ViewAffiliateCorporate;
use App\Filament\Operations\Resources\AffiliateCorporates\Schemas\AffiliateCorporateForm;
use App\Filament\Operations\Resources\AffiliateCorporates\Schemas\AffiliateCorporateInfolist;
use App\Filament\Operations\Resources\AffiliateCorporates\Tables\AffiliateCorporatesTable;
use App\Models\AffiliateCorporate;
use App\Models\Permission;
use App\Models\UserPermission;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Filament\GlobalSearchAffiliateBusinessDetails;
use App\Support\Filament\GlobalSearchAffiliateStatusLabel;
use App\Support\Filament\GlobalSearchAffiliationCollectionExpirations;
use App\Support\Operations\SupplierAffiliateVisibility;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class AffiliateCorporateResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = AffiliateCorporate::class;

    protected static ?string $navigationLabel = 'Corporativos';

    protected static ?string $pluralModelLabel = 'Afiliados corporativos';

    protected static ?string $modelLabel = 'Afiliado corporativo';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|UnitEnum|null $navigationGroup = 'AFILIADOS';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'first_name';

    protected static int $globalSearchResultsLimit = 12;

    protected static ?int $globalSearchSort = 8;

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return [
            'first_name',
            'last_name',
            'nro_identificacion',
            'affiliationCorporate.code',
            'affiliationCorporate.name_corporate',
            'affiliationCorporate.rif',
        ];
    }

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        if ($record === null) {
            return parent::getRecordTitle(null);
        }

        if ($record instanceof AffiliateCorporate) {
            $name = trim(implode(' ', array_filter([
                $record->first_name,
                $record->last_name,
            ])));

            if ($name !== '') {
                return $name;
            }

            if (filled($record->nro_identificacion)) {
                return (string) $record->nro_identificacion;
            }
        }

        return parent::getRecordTitle($record);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Support\Htmlable|string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        if (! $record instanceof AffiliateCorporate) {
            return [];
        }

        $corp = $record->affiliationCorporate;

        return [
            'Identificación' => filled($record->nro_identificacion) ? (string) $record->nro_identificacion : '—',
            'Empresa' => filled($corp?->name_corporate) ? (string) $corp->name_corporate : '—',
            'RIF empresa' => filled($corp?->rif) ? (string) $corp->rif : '—',
            'Afiliación' => filled($corp?->code) ? (string) $corp->code : '—',
            'Fecha activación' => static::formatCorporateVigencia($record),
            'Periodo de Vigencia' => GlobalSearchAffiliationCollectionExpirations::paymentExpirationDetailsValue(
                $corp?->code,
            ),
            'Plan' => static::formatPlanDescription($record),
            'Tipo de plan' => filled($record->plan?->type) ? (string) $record->plan->type : '—',
            'Unidad de Negocio Específica' => GlobalSearchAffiliateBusinessDetails::specificBusinessUnit(
                $record->specific_business_unit,
                $corp?->specific_business_unit,
            ),
            'Proveedor(es) de Servicios' => GlobalSearchAffiliateBusinessDetails::serviceProviders(
                $corp?->service_providers,
            ),
            'Estatus' => GlobalSearchAffiliateStatusLabel::html($record->status),
        ];
    }

    /**
     * Base de la tabla, la búsqueda global y la resolución de registros (ver/editar por URL):
     * un usuario de proveedor solo alcanza a los afiliados que su proveedor atiende.
     */
    public static function getEloquentQuery(): Builder
    {
        return SupplierAffiliateVisibility::applyToAffiliateCorporates(parent::getEloquentQuery());
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with([
                'affiliationCorporate',
                'plan',
            ]);
    }

    private static function formatCorporateVigencia(AffiliateCorporate $record): string
    {
        $corp = $record->affiliationCorporate;
        if ($corp !== null) {
            $original = $corp->getRawOriginal();
            if (is_array($original) && array_key_exists('effective_date', $original) && filled($original['effective_date'])) {
                return (string) $original['effective_date'];
            }
        }

        $start = static::rawStoredColumn($record, 'date_init', 'dateInit');
        $end = static::rawStoredColumn($record, 'date_end', 'dateEnd');
        if ($start !== '—' || $end !== '—') {
            return "{$start} → {$end}";
        }

        return '—';
    }

    private static function rawStoredColumn(Model $record, string $firstKey, ?string $secondKey = null): string
    {
        foreach (array_filter([$firstKey, $secondKey]) as $key) {
            $original = $record->getRawOriginal();
            if (is_array($original) && array_key_exists($key, $original) && filled($original[$key])) {
                return (string) $original[$key];
            }
        }

        return '—';
    }

    private static function formatPlanDescription(AffiliateCorporate $record): string
    {
        $plan = $record->plan;

        if ($plan === null) {
            return '—';
        }

        return filled($plan->description) ? (string) $plan->description : '—';
    }

    /**
     * Editar datos personales exige el permiso granular «Editar datos
     * personales de afiliados corporativos» (SUPERADMIN siempre puede). Sin
     * él, el botón no aparece y la URL de edición responde 403.
     */
    public static function canEdit(Model $record): bool
    {
        return static::userCanEditPersonalData(Auth::id());
    }

    /**
     * Memorizado por petición, usuario y panel: la tabla lo evalúa en cada fila.
     */
    private static function userCanEditPersonalData(int|string|null $userId): bool
    {
        if ($userId === null) {
            return false;
        }

        $panelId = Filament::getCurrentPanel()?->getId();

        /** `once()` memoriza por las variables capturadas: usuario y panel van en `use`. */
        return once(static function () use ($userId, $panelId): bool {
            unset($userId, $panelId);

            return static::canAccess()
                && BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::EDIT_CORPORATE_AFFILIATE_PERSONAL_DATA);
        });
    }

    /**
     * Operaciones no elimina afiliados: la página de edición generada traía un
     * botón Eliminar que borraba el registro sin rastro.
     */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return AffiliateCorporateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AffiliateCorporateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AffiliateCorporatesTable::configure($table);
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
            'index' => ListAffiliateCorporates::route('/'),
            'create' => CreateAffiliateCorporate::route('/create'),
            'view' => ViewAffiliateCorporate::route('/{record}'),
            'edit' => EditAffiliateCorporate::route('/{record}/edit'),
        ];
    }

    // public static function canAccess(): bool
    // {
    //     $module = 'OPERACIONES';
    //     $permission = Permission::where('module', $module)->where('slug', 'afiliados-corporativos')->first();

    //     // si es superadmin, retornar true
    //     if (in_array('SUPERADMIN', Auth::user()->departament)) {
    //         return true;
    //     }

    //     if (in_array($module, Auth::user()->departament)) {
    //         if (UserPermission::where('user_id', Auth::user()->id)->where('permission_id', $permission->id)->exists()) {
    //             return true;
    //         }
    //     }

    //     return false;
    // }
}
