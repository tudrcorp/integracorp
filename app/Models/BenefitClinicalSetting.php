<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ClinicalQuotaScope;
use App\Enums\ClinicalServiceChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BenefitClinicalSetting extends Model
{
    protected $table = 'benefit_clinical_settings';

    protected $fillable = [
        'benefit_id',
        'applies_clinically',
        'channel',
        'telemedicine_service_list_id',
        'service_id',
        'quota_scope',
        'quota',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'applies_clinically' => 'boolean',
            'channel' => ClinicalServiceChannel::class,
            'quota_scope' => ClinicalQuotaScope::class,
            'quota' => 'integer',
        ];
    }

    public function benefit(): BelongsTo
    {
        return $this->belongsTo(Benefit::class);
    }

    public function telemedicineServiceList(): BelongsTo
    {
        return $this->belongsTo(TelemedicineServiceList::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
