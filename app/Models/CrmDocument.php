<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * PDF aprobado que el analista envía desde el chat. El archivo vive en el disco privado.
 */
class CrmDocument extends Model
{
    protected $fillable = [
        'area',
        'title',
        'description',
        'path',
        'filename',
        'size',
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
            'size' => 'integer',
            'sort' => 'integer',
            'uses' => 'integer',
        ];
    }
}
