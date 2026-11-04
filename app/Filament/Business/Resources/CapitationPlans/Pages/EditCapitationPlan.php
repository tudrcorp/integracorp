<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\CapitationPlans\Pages;

use App\Filament\Business\Resources\CapitationPlans\Actions\CapitationPlanActions;
use App\Filament\Business\Resources\CapitationPlans\CapitationPlanResource;
use App\Models\CapitationPlan;
use App\Models\User;
use App\Support\Business\CapitationPlans\CapitationPlanWriter;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EditCapitationPlan extends EditRecord
{
    protected static string $resource = CapitationPlanResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Editar plan · '.$this->getRecord()->getAttribute('name');
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->label('Ver plan'),
            CapitationPlanActions::pdf(),
            CapitationPlanActions::send(),
            CapitationPlanActions::delete(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var CapitationPlan $plan */
        $plan = $this->getRecord();

        return [
            ...$data,
            'provider_key' => $plan->providerKey(),
            'payment_frequency' => $plan->payment_frequency->value,
            'covered_plan_ids' => $plan->coveredPlans()->pluck('plans.id')->map(fn (mixed $id): int => (int) $id)->all(),
            'benefits' => $plan->benefits()->get()->map(fn ($benefit): array => [
                'benefit_id' => $benefit->benefit_id,
                'custom_name' => $benefit->benefit_id === null ? $benefit->name : null,
                'note' => $benefit->note,
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $user = Auth::user();

        return CapitationPlanWriter::save($record instanceof CapitationPlan ? $record : null, $data, $user instanceof User ? $user : null);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Plan de capitado actualizado';
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
