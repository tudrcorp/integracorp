<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelemedicineGeneralService extends Model
{
    protected $table = 'telemedicine_general_services';

    protected $fillable = [
        'name',
        'description',
        'status',
        'created_by',
        'updated_by',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'ACTIVO');
    }

    public function consultations(): HasMany
    {
        return $this->hasMany(TelemedicineConsultationPatient::class, 'telemedicine_general_service_id');
    }
}
