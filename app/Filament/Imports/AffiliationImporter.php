<?php

namespace App\Filament\Imports;

use App\Models\Affiliation;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class AffiliationImporter extends Importer
{
    protected static ?string $model = Affiliation::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('individual_quote_id')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('owner_code')
                ->rules(['max:100']),
            ImportColumn::make('code')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('code_agency')
                ->requiredMapping()
                ->rules(['required', 'max:50']),
            ImportColumn::make('agent_id')
                ->numeric()
                ->rules(['integer']),
            ImportColumn::make('plan_id')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
            ImportColumn::make('coverage_id')
                ->numeric()
                ->rules(['integer']),
            ImportColumn::make('payment_frequency')
                ->rules(['max:255']),
            ImportColumn::make('code_individual_quote')
                ->requiredMapping()
                ->rules(['required', 'max:50']),
            ImportColumn::make('full_name_payer')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('nro_identificacion_payer')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('phone_payer')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('email_payer')
                ->requiredMapping()
                ->rules(['required', 'email', 'max:255']),
            ImportColumn::make('relationship_payer')
                ->rules(['max:100']),
            ImportColumn::make('full_name_ti')
                ->rules(['max:255']),
            ImportColumn::make('nro_identificacion_ti')
                ->rules(['max:255']),
            ImportColumn::make('sex_ti')
                ->rules(['max:255']),
            ImportColumn::make('birth_date_ti')
                ->rules(['max:255']),
            ImportColumn::make('adress_ti')
                ->rules(['max:255']),
            ImportColumn::make('city_id_ti')
                ->rules(['max:255']),
            ImportColumn::make('state_id_ti')
                ->rules(['max:255']),
            ImportColumn::make('country_id_ti')
                ->rules(['max:255']),
            ImportColumn::make('region_ti')
                ->rules(['max:255']),
            ImportColumn::make('phone_ti')
                ->rules(['max:255']),
            ImportColumn::make('email_ti')
                ->rules(['email', 'max:255']),
            ImportColumn::make('cuestion_1')
                ->boolean()
                ->rules(['boolean']),
            ImportColumn::make('cuestion_2')
                ->boolean()
                ->rules(['boolean']),
            ImportColumn::make('cuestion_3')
                ->boolean()
                ->rules(['boolean']),
            ImportColumn::make('cuestion_4')
                ->boolean()
                ->rules(['boolean']),
            ImportColumn::make('cuestion_5')
                ->boolean()
                ->rules(['boolean']),
            ImportColumn::make('cuestion_6')
                ->boolean()
                ->rules(['boolean']),
            ImportColumn::make('cuestion_7')
                ->boolean()
                ->rules(['boolean']),
            ImportColumn::make('cuestion_8')
                ->boolean()
                ->rules(['boolean']),
            ImportColumn::make('cuestion_9')
                ->boolean()
                ->rules(['boolean']),
            ImportColumn::make('cuestion_10')
                ->boolean()
                ->rules(['boolean']),
            ImportColumn::make('cuestion_11')
                ->boolean()
                ->rules(['boolean']),
            ImportColumn::make('cuestion_12')
                ->boolean()
                ->rules(['boolean']),
            ImportColumn::make('cuestion_13')
                ->boolean()
                ->rules(['boolean']),
            ImportColumn::make('cuestion_14')
                ->boolean()
                ->rules(['boolean']),
            ImportColumn::make('full_name_agent')
                ->rules(['max:255']),
            ImportColumn::make('code_agent')
                ->rules(['max:255']),
            ImportColumn::make('date_today')
                ->rules(['max:255']),
            ImportColumn::make('created_by')
                ->rules(['max:255']),
            ImportColumn::make('status')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('document')
                ->rules(['max:255']),
            ImportColumn::make('activated_at')
                ->rules(['max:255']),
            ImportColumn::make('family_members')
                ->rules(['max:255']),
            ImportColumn::make('vaucher_ils')
                ->rules(['max:255']),
            ImportColumn::make('date_payment_initial_ils')
                ->rules(['max:255']),
            ImportColumn::make('date_payment_final_ils')
                ->rules(['max:255']),
            ImportColumn::make('document_ils')
                ->rules(['max:255']),
            ImportColumn::make('observations_payment'),
            ImportColumn::make('fee_anual')
                ->numeric()
                ->rules(['integer']),
            ImportColumn::make('total_amount')
                ->numeric()
                ->rules(['integer']),
            ImportColumn::make('signature_agent')
                ->rules(['max:100']),
            ImportColumn::make('upload_documents'),
            ImportColumn::make('signature_ti')
                ->rules(['max:100']),
            ImportColumn::make('observations'),
            ImportColumn::make('owner_agent')
                ->rules(['max:100']),
            ImportColumn::make('activation_date')
                ->rules(['max:100']),
            ImportColumn::make('feedback')
                ->boolean()
                ->rules(['boolean']),
            ImportColumn::make('feedback_dos')
                ->boolean()
                ->rules(['boolean']),
        ];
    }

    public function resolveRecord(): Affiliation
    {
        return new Affiliation();
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your affiliation import has completed and ' . Number::format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
