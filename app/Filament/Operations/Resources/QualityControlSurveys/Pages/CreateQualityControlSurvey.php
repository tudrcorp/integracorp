<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\QualityControlSurveys\Pages;

use App\Filament\Operations\Resources\QualityControlSurveys\QualityControlSurveyResource;
use App\Support\Operations\QualityControl\QualityControlSurveyWriter;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

abstract class CreateQualityControlSurvey extends CreateRecord
{
    protected static bool $canCreateAnother = true;

    public function getTitle(): string|Htmlable
    {
        /** @var class-string<QualityControlSurveyResource> $resource */
        $resource = static::getResource();

        return 'Nueva encuesta · Quality Control '.$resource::affiliateType()->label();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var class-string<QualityControlSurveyResource> $resource */
        $resource = static::getResource();
        abort_unless($resource::canCreate(), 403);

        return QualityControlSurveyWriter::create($resource::affiliateType(), $data, Auth::id());
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Encuesta registrada';
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
