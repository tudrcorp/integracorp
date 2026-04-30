<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationCoordinationClinicDocument extends Model
{
    public const CATEGORY_INGRESO = 'ingreso';

    public const CATEGORY_EGRESO = 'egreso';

    protected $fillable = [
        'operation_coordination_service_id',
        'category',
        'path',
        'original_filename',
        'mime_type',
        'size_bytes',
        'uploaded_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    public function operationCoordinationService(): BelongsTo
    {
        return $this->belongsTo(OperationCoordinationService::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
