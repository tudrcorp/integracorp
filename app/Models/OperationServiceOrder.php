<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperationServiceOrder extends Model
{
    //
    protected $table = 'operation_service_orders';

    protected $fillable = [
        'operation_coordination_service_id',
        'supplier_id',
        'telemedicine_priority_id',
        'order_number',
        'supplier_external',
        'operation_inventory_ubication_id',
        'description',
        'service_type',
        'currency',
        'tasa_bcv',
        'total_amount_usd',
        'total_amount_ves',
        'payment_method',
        'status',
        'observations',
        'created_by',
        'updated_by',
        'total_items',
        'total_items_unit',
        'files',
        'status_payment',
    ];

    protected function casts(): array
    {
        return [
            'total_items' => 'integer',
            'total_items_unit' => 'integer',
            'files' => 'array',
        ];
    }

    public function operationCoordinationService()
    {
        return $this->belongsTo(OperationCoordinationService::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function telemedicinePriority()
    {
        return $this->belongsTo(TelemedicinePriority::class);
    }

    public function operationInventoryUbication()
    {
        return $this->belongsTo(OperationInventoryUbication::class);
    }

    public function operationServiceOrderItems(): HasMany
    {
        return $this->hasMany(OperationServiceOrderItem::class);
    }
}
