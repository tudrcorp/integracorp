<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Agents\Pages;

use App\Filament\Administration\Resources\Agents\AgentResource;
use App\Filament\Shared\CommercialStructure\Concerns\SyncsReferidorAssignments;
use App\Models\Agent;
use App\Support\Filament\CommercialStructurePageHeader;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditAgent extends EditRecord
{
    use SyncsReferidorAssignments;

    protected static string $resource = AgentResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var Agent $agent */
        $agent = $this->getRecord();

        return CommercialStructurePageHeader::forAgent($agent, context: 'edit');
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
                ->url(AgentResource::getUrl('view', ['record' => $this->getRecord()]))
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('warning'),
                ]),
        ];
    }
}
