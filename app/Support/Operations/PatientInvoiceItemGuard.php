<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationMedicationInvoice;
use App\Models\OperationMedicationInvoiceItem;
use App\Models\TelemedicinePatientLab;
use App\Models\TelemedicinePatientMedications;
use App\Models\TelemedicinePatientSpecialty;
use App\Models\TelemedicinePatientStudy;
use Illuminate\Database\Eloquent\Model;

/**
 * ¿Este ítem recetado está en una factura al paciente vigente? Lo usan los flujos que lo
 * cancelarían o reabrirían: con factura vigente, primero se anula la factura.
 */
final class PatientInvoiceItemGuard
{
    public static function activeInvoiceNumberFor(Model $item): ?string
    {
        $query = OperationMedicationInvoiceItem::query()
            ->whereHas('invoice', fn ($invoice) => $invoice->where('status', '!=', OperationMedicationInvoice::STATUS_ANNULLED));

        $query = match (true) {
            $item instanceof TelemedicinePatientMedications => $query->where('telemedicine_patient_medication_id', $item->getKey()),
            $item instanceof TelemedicinePatientLab => $query->where('item_type', OperationMedicationInvoice::TYPE_LABORATORIES)->where('source_item_id', $item->getKey()),
            $item instanceof TelemedicinePatientStudy => $query->where('item_type', OperationMedicationInvoice::TYPE_IMAGING)->where('source_item_id', $item->getKey()),
            $item instanceof TelemedicinePatientSpecialty => $query->where('item_type', OperationMedicationInvoice::TYPE_SPECIALIST)->where('source_item_id', $item->getKey()),
            default => null,
        };

        if ($query === null) {
            return null;
        }

        return $query->with('invoice:id,invoice_number')->latest('id')->first()?->invoice?->invoice_number;
    }
}
