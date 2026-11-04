<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\QualityControlCorporates;

use App\Enums\QualityControlAffiliateType;
use App\Filament\Operations\Resources\QualityControlCorporates\Pages\CreateQualityControlCorporate;
use App\Filament\Operations\Resources\QualityControlCorporates\Pages\EditQualityControlCorporate;
use App\Filament\Operations\Resources\QualityControlCorporates\Pages\ListQualityControlCorporates;
use App\Filament\Operations\Resources\QualityControlCorporates\Pages\ViewQualityControlCorporate;
use App\Filament\Operations\Resources\QualityControlSurveys\QualityControlSurveyResource;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;

/**
 * Operaciones → AFILIADOS → «Quality Control Corporativos» (justo debajo de «Corporativos»).
 */
class QualityControlCorporateResource extends QualityControlSurveyResource
{
    protected static ?string $navigationLabel = 'Quality Control Corporativos';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'quality-control-corporativos';

    public static function affiliateType(): QualityControlAffiliateType
    {
        return QualityControlAffiliateType::Corporate;
    }

    public static function manageActionSlug(): string
    {
        return BusinessFilamentActionPermissionRegistry::MANAGE_CORPORATE_QUALITY_CONTROL;
    }

    public static function noteActionSlug(): string
    {
        return BusinessFilamentActionPermissionRegistry::NOTE_CORPORATE_QUALITY_CONTROL;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQualityControlCorporates::route('/'),
            'create' => CreateQualityControlCorporate::route('/create'),
            'view' => ViewQualityControlCorporate::route('/{record}'),
            'edit' => EditQualityControlCorporate::route('/{record}/edit'),
        ];
    }
}
