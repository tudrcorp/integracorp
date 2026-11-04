<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\QualityControlIndividuals;

use App\Enums\QualityControlAffiliateType;
use App\Filament\Operations\Resources\QualityControlIndividuals\Pages\CreateQualityControlIndividual;
use App\Filament\Operations\Resources\QualityControlIndividuals\Pages\EditQualityControlIndividual;
use App\Filament\Operations\Resources\QualityControlIndividuals\Pages\ListQualityControlIndividuals;
use App\Filament\Operations\Resources\QualityControlIndividuals\Pages\ViewQualityControlIndividual;
use App\Filament\Operations\Resources\QualityControlSurveys\QualityControlSurveyResource;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;

/**
 * Operaciones → AFILIADOS → «Quality Control Individuales» (justo debajo de «Individuales»).
 */
class QualityControlIndividualResource extends QualityControlSurveyResource
{
    protected static ?string $navigationLabel = 'Quality Control Individuales';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'quality-control-individuales';

    public static function affiliateType(): QualityControlAffiliateType
    {
        return QualityControlAffiliateType::Individual;
    }

    public static function manageActionSlug(): string
    {
        return BusinessFilamentActionPermissionRegistry::MANAGE_INDIVIDUAL_QUALITY_CONTROL;
    }

    public static function noteActionSlug(): string
    {
        return BusinessFilamentActionPermissionRegistry::NOTE_INDIVIDUAL_QUALITY_CONTROL;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQualityControlIndividuals::route('/'),
            'create' => CreateQualityControlIndividual::route('/create'),
            'view' => ViewQualityControlIndividual::route('/{record}'),
            'edit' => EditQualityControlIndividual::route('/{record}/edit'),
        ];
    }
}
