<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ClinicalQuotaScope;
use App\Enums\ClinicalServiceChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanBenefitClinicalSetting extends Model
{
    protected $table = 'plan_benefit_clinical_settings';

    protected $fillable = [
        'plan_id',
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

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
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

    public function isComplete(): bool
    {
        if (! $this->applies_clinically) {
            return true;
        }

        if (! $this->channel instanceof ClinicalServiceChannel) {
            return false;
        }

        if ($this->channel->usesTelemedicineServiceList() && $this->telemedicine_service_list_id === null) {
            return false;
        }

        $scope = $this->quota_scope instanceof ClinicalQuotaScope
            ? $this->quota_scope
            : ClinicalQuotaScope::fromStored($this->quota_scope);

        if ($scope === null) {
            return false;
        }

        if ($scope->requiresQuota() && (int) $this->quota < 1) {
            return false;
        }

        return true;
    }
}
