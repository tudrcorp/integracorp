<?php

namespace App\Filament\Operations\Resources\Affiliates;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Operations\Resources\Affiliates\Pages\CreateAffiliate;
use App\Filament\Operations\Resources\Affiliates\Pages\EditAffiliate;
use App\Filament\Operations\Resources\Affiliates\Pages\ListAffiliates;
use App\Filament\Operations\Resources\Affiliates\Pages\ViewAffiliate;
use App\Filament\Operations\Resources\Affiliates\Schemas\AffiliateForm;
use App\Filament\Operations\Resources\Affiliates\Schemas\AffiliateInfolist;
use App\Filament\Operations\Resources\Affiliates\Tables\AffiliatesTable;
use App\Models\Affiliate;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\UserPermission;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Filament\GlobalSearchAffiliateBusinessDetails;
use App\Support\Filament\GlobalSearchAffiliateStatusLabel;
use App\Support\Filament\GlobalSearchAffiliationCollectionExpirations;
use App\Support\Operations\SupplierAffiliateVisibility;
use BackedEnum;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class AffiliateResource extends Resource
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $model = Affiliate::class;

    protected static ?string $navigationLabel = 'Individuales';

    protected static ?string $pluralModelLabel = 'Afiliados individuales';

    protected static ?string $modelLabel = 'Afiliado individual';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user';

    protected static string|UnitEnum|null $navigationGroup = 'AFILIADOS';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'full_name';

    protected static int $globalSearchResultsLimit = 12;

    protected static ?int $globalSearchSort = 5;

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return [
            'full_name',
            'nro_identificacion',
            'document',
            'affiliation.code',
        ];
    }

    /**
     * @return array<string, \Illuminate\Contracts\Support\Htmlable|string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        if (! $record instanceof Affiliate) {
            return [];
        }

        return [
            'Identificación' => filled($record->nro_identificacion) ? (string) $record->nro_identificacion : '—',
            'Afiliación' => filled($record->affiliation?->code) ? (string) $record->affiliation->code : '—',
            'Fecha activación' => static::formatAffiliateVigencia($record),
            'Periodo de Vigencia' => GlobalSearchAffiliationCollectionExpirations::paymentExpirationDetailsValue(
                $record->affiliation?->code,
            ),
            'Plan' => static::formatPlanLabel($record->plan),
            'Tipo de plan' => filled($record->plan?->type) ? (string) $record->plan->type : '—',
            'Unidad de Negocio Específica' => GlobalSearchAffiliateBusinessDetails::specificBusinessUnit(
                $record->specific_business_unit,
                $record->affiliation?->specific_business_unit,
            ),
            'Proveedor(es) de Servicios' => GlobalSearchAffiliateBusinessDetails::serviceProviders(
                $record->affiliation?->service_providers,
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
        return SupplierAffiliateVisibility::applyToAffiliates(parent::getEloquentQuery());
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with([
                'affiliation',
                'plan',
            ]);
    }

    private static function formatAffiliateVigencia(Affiliate $record): string
    {
        $affiliation = $record->affiliation;
        if ($affiliation !== null) {
            $original = $affiliation->getRawOriginal();
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

    private static function formatPlanLabel(?Plan $plan): string
    {
        if ($plan === null) {
            return '—';
        }

        $label = filled($plan->description) ? (string) $plan->description : null;

        return $label ?? '—';
    }

    /**
     * Muestra un badge con la palabra NEW y el conteo de afiliados
     * con estatus 'ACTIVA' registrados el día de hoy.
     */
    public static function getNavigationBadge(): ?string
    {
        $todayCount = static::getEloquentQuery()
            ->where('status', 'ACTIVO')
            ->whereDate('created_at', Carbon::today())
            ->count();

        return $todayCount > 0 ? "NUEVO {$todayCount}" : null;
    }

    /**
     * Color personalizado para el badge (Verde iOS).
     */
    public static function getNavigationBadgeColor(): ?string
    {
        return 'verdeApple';
    }

    /**
     * Editar datos personales exige el permiso granular «Editar datos
     * personales de afiliados individuales» (SUPERADMIN siempre puede). Sin él,
     * el botón no aparece y la URL de edición responde 403.
     */
    public static function canEdit(Model $record): bool
    {
        return static::userCanEditPersonalData(Auth::id());
    }

    /**
     * Memorizado por petición y usuario: la tabla lo evalúa en cada fila.
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
                && BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::EDIT_INDIVIDUAL_AFFILIATE_PERSONAL_DATA);
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
        return AffiliateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AffiliateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AffiliatesTable::configure($table);
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
            'index' => ListAffiliates::route('/'),
            'create' => CreateAffiliate::route('/create'),
            'view' => ViewAffiliate::route('/{record}'),
            'edit' => EditAffiliate::route('/{record}/edit'),
        ];
    }

    // public static function canAccess(): bool
    // {
    //     $module = 'OPERACIONES';
    //     $permission = Permission::where('module', $module)->where('slug', 'afiliados-individuales')->first();

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
