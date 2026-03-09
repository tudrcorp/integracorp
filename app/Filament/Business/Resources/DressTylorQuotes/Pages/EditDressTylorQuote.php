<?php

namespace App\Filament\Business\Resources\DressTylorQuotes\Pages;

use App\Filament\Business\Resources\DressTylorQuotes\DressTylorQuoteResource;
use App\Filament\Business\Resources\DressTylorQuotes\Schemas\DressTylorQuoteForm;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditDressTylorQuote extends EditRecord
{
    protected static string $resource = DressTylorQuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->record;

        $data['full_name'] = $record->full_name ?? ($data['full_name'] ?? '');
        $data['rif_ci'] = $record->rifCi ?? ($data['rif_ci'] ?? '');
        $data['email'] = $record->email ?? ($data['email'] ?? '');
        $data['plan_name'] = $record->planName ?? ($data['plan_name'] ?? '');

        $structure = $record->quote_structure;
        if (is_array($structure) && isset($structure['_form'])) {
            $form = $structure['_form'];
            $data['benefits_repeater'] = $form['benefits_repeater'] ?? [];
            $rawUpgrade = $form['upgrade_benefits_repeater'] ?? DressTylorQuoteForm::defaultUpgradeRepeaterItems();
            $data['upgrade_benefits_repeater'] = DressTylorQuoteForm::enrichUpgradeRepeaterItemsWithDescriptions($rawUpgrade);
            $data['manual_adjustment_percent'] = $form['manual_adjustment_percent'] ?? 0;
            $data['plan_id'] = $form['plan_id'] ?? null;
            $data['title'] = $form['title'] ?? 'COTIZACIÓN';
            $data['subtitle'] = $form['subtitle'] ?? 'PLAN MAESTRO DE BENEFICIOS Y COBERTURAS';
        } else {
            $data['benefits_repeater'] = $data['benefits_repeater'] ?? [];
            $rawUpgrade = $data['upgrade_benefits_repeater'] ?? DressTylorQuoteForm::defaultUpgradeRepeaterItems();
            $data['upgrade_benefits_repeater'] = DressTylorQuoteForm::enrichUpgradeRepeaterItemsWithDescriptions($rawUpgrade);
            $data['manual_adjustment_percent'] = $data['manual_adjustment_percent'] ?? 0;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['quote_structure'] = DressTylorQuoteForm::buildQuoteStructureWithForm($data);
        $data['rifCi'] = $data['rif_ci'] ?? null;
        $data['planName'] = $data['plan_name'] ?? null;
        $data['updated_by'] = Auth::user()?->name;

        return $data;
    }
}
