<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TelemedicinePanelEventCategory;
use App\Enums\TelemedicinePanelEventLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un evento de la bitácora del panel de Telemedicina.
 *
 * Lo escribe {@see \App\Support\Telemedicine\PanelLog\TelemedicinePanelLog} al
 * final de cada petición, nunca la pantalla del médico. Se purga sola a los
 * {@see self::RETENTION_DAYS} días (`model:prune` en el scheduler).
 */
class TelemedicinePanelEvent extends Model
{
    use MassPrunable;

    public const RETENTION_DAYS = 90;

    public $timestamps = false;

    protected $table = 'telemedicine_panel_events';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'occurred_at',
        'level',
        'category',
        'event',
        'title',
        'message',
        'user_id',
        'user_name',
        'telemedicine_doctor_id',
        'telemedicine_case_id',
        'telemedicine_case_code',
        'telemedicine_patient_id',
        'telemedicine_consultation_id',
        'telemedicine_service_list_id',
        'screen',
        'component',
        'livewire_id',
        'url',
        'action',
        'step',
        'fields',
        'context',
        'request_id',
        'ip',
        'user_agent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'fields' => 'array',
            'context' => 'array',
        ];
    }

    public function prunable(): Builder
    {
        return static::query()->where('occurred_at', '<', now()->subDays(self::RETENTION_DAYS));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function telemedicineServiceList(): BelongsTo
    {
        return $this->belongsTo(TelemedicineServiceList::class);
    }

    public function levelEnum(): ?TelemedicinePanelEventLevel
    {
        return TelemedicinePanelEventLevel::tryFrom((string) $this->level);
    }

    public function categoryEnum(): ?TelemedicinePanelEventCategory
    {
        return TelemedicinePanelEventCategory::tryFrom((string) $this->category);
    }

    public function isProblem(): bool
    {
        return $this->level !== TelemedicinePanelEventLevel::Info->value;
    }
}
