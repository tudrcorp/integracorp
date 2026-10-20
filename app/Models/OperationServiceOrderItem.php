<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationServiceOrderItem extends Model
{
    //
    protected $table = 'operation_service_order_items';

    protected $fillable = [
        'operation_service_order_id',
        'telemedicine_patient_medication_id',
        'operation_inventory_id',
        'item_name',
        'category',
        'dosage_instruction',
        'item_unit',
        'quantity',
        'amount',
        'currency',
        'created_by',
        'updated_by',
    ];

    public function operationServiceOrder()
    {
        return $this->belongsTo(OperationServiceOrder::class);
    }

    public function telemedicinePatientMedication(): BelongsTo
    {
        return $this->belongsTo(TelemedicinePatientMedications::class);
    }
}
