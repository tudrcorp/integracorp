<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Respuesta rápida del CRM. Sin área sirve en todas las bandejas.
 */
class CrmQuickReply extends Model
{
    protected $fillable = [
        'area',
        'label',
        'body',
        'sort',
        'active',
        'uses',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'sort' => 'integer',
            'uses' => 'integer',
        ];
    }
}
