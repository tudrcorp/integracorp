<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Coverage extends Model
{
    protected $table = 'coverages';

    protected $fillable = [
        'price',
        'plan_id',
        'status',
        'created_by',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id', 'id');
    }

    public function beneficios(): BelongsToMany
    {
        return $this->belongsToMany(Benefit::class, 'beneficio_cobertura_limites')
            ->withPivot('limite_uso'); // ⬅️ IMPORTANTE: Carga el campo extra
    }

}