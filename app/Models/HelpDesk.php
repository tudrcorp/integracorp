<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class HelpDesk extends Model
{
    protected $table = 'help_desks';

    protected $fillable = [
        'description',
        'image',
        'priority',
        'status',
        'created_by',
        'updated_by',
        'observation',
        'cc_colaboradores',
    ];

    protected $casts = [
        'cc_colaboradores' => 'array',
    ];

    public function help_desk_category(): BelongsTo
    {
        return $this->belongsTo(HelpDeskCategory::class);
    }

    /**
     * Colaboradores a los que se asignó el ticket (uno o varios).
     */
    public function rrhhColaboradores(): BelongsToMany
    {
        return $this->belongsToMany(RrhhColaborador::class, 'help_desk_rrhh_colaborador')
            ->withTimestamps();
    }
}
