<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Columnas del archivo de población de una afiliación corporativa (qué columna
 * es la cédula, los nombres, etc.). La segunda carga la reutiliza.
 */
class CorporatePopulationTemplate extends Model
{
    protected $table = 'corporate_population_templates';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'affiliation_corporate_id',
        'mapping',
        'headers',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mapping' => 'array',
            'headers' => 'array',
        ];
    }
}
