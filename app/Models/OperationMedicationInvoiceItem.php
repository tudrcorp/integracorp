<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea de una factura de medicamentos: el producto despachado, con costo, utilidad y
 * precio congelados al emitir.
 */
class OperationMedicationInvoiceItem extends Model
{
    protected $table = 'operation_medication_invoice_items';

    protected $fillable = [
        'operation_medication_invoice_id',
        'item_type',
        'telemedicine_patient_medication_id',
        'source_item_id',
        'catalog_item_id',
        'prescribed_name',
        'prescribed_indications',
        'operation_inventory_product_id',
        'operation_inventory_id',
        'operation_inventory_ubication_id',
        'product_code',
        'product_name',
        'category_name',
        'ubication_name',
        'quantity',
        'unit_cost_usd',
        'profit_percentage',
        'unit_price_usd',
        'line_total_usd',
        'restocked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost_usd' => 'decimal:2',
            'profit_percentage' => 'decimal:2',
            'unit_price_usd' => 'decimal:2',
            'line_total_usd' => 'decimal:2',
            'restocked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<OperationMedicationInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(OperationMedicationInvoice::class, 'operation_medication_invoice_id');
    }

    /**
     * @return BelongsTo<TelemedicinePatientMedications, $this>
     */
    public function medication(): BelongsTo
    {
        return $this->belongsTo(TelemedicinePatientMedications::class, 'telemedicine_patient_medication_id');
    }

    /**
     * @return BelongsTo<OperationInventoryProduct, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(OperationInventoryProduct::class, 'operation_inventory_product_id');
    }
}
