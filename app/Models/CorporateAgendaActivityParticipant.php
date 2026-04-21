<?php

namespace App\Models;

use App\Enums\CorporateAgendaInvitationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorporateAgendaActivityParticipant extends Model
{
    protected $fillable = [
        'activity_id',
        'rrhh_colaborador_id',
        'invitation_status',
        'response_note',
    ];

    protected function casts(): array
    {
        return [
            'invitation_status' => CorporateAgendaInvitationStatus::class,
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(CorporateAgendaActivity::class, 'activity_id');
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(RrhhColaborador::class, 'rrhh_colaborador_id');
    }
}
