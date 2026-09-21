<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Agencies\Pages;

use App\Filament\Administration\Resources\Agencies\AgencyResource;
use App\Filament\Shared\CommercialStructure\Concerns\SyncsReferidorAssignments;
use App\Models\Agency;
use App\Support\Filament\CommercialStructurePageHeader;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditAgency extends EditRecord
{
    use SyncsReferidorAssignments;

    protected static string $resource = AgencyResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var Agency $agency */
        $agency = $this->getRecord();

        return CommercialStructurePageHeader::forAgency($agency, context: 'edit');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->fillReferidorAssignmentState($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->captureReferidorAssignments($data);
    }

    protected function afterSave(): void
    {
        $this->persistCapturedReferidorAssignments();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color('warning')
                ->url(AgencyResource::getUrl('view', ['record' => $this->getRecord()]))
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('warning'),
                ]),
        ];
    }
}
