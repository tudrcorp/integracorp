<?php

declare(strict_types=1);

namespace App\Filament\Business\Pages;

use App\Support\Companies\CompanyAssociateInclusionQrCatalog;
use App\Support\TarjetaAfiliacionQrPlanCatalog;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class GeneradorQrPersonalizado extends Page
{
    protected static ?string $navigationLabel = 'Generador QR personalizado';

    protected static ?string $title = 'Generador QR personalizado';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static string|UnitEnum|null $navigationGroup = 'CONFIGURACIÓN';

    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.business.pages.generador-qr-personalizado';

    /**
     * @return array<int, string>
     */
    public function getIndividualQrPlanOptions(): array
    {
        return TarjetaAfiliacionQrPlanCatalog::individualSelectOptions();
    }

    /**
     * @return array<int, string>
     */
    public function getCorporateQrPlanOptions(): array
    {
        return TarjetaAfiliacionQrPlanCatalog::corporateSelectOptions();
    }

    public function getCompanyAssociateInclusionPdfUrl(): string
    {
        return CompanyAssociateInclusionQrCatalog::pdfPublicUrl();
    }

    public function getCompanyAssociateInclusionLogoUrl(): string
    {
        return CompanyAssociateInclusionQrCatalog::logoPublicUrl();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::userCanAccessPage();
    }

    public static function canAccess(): bool
    {
        return self::userCanAccessPage();
    }

    private static function userCanAccessPage(): bool
    {
        $departments = (array) (Auth::user()?->departament ?? []);

        return in_array('SUPERADMIN', $departments, true);
    }
}
