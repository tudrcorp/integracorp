<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class State extends Model
{
    protected $table = 'states';

    protected $fillable = [
        'country_id',
        'region_id',
        'definition',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }

    public function region()
    {
        return $this->belongsTo(Region::class, 'region_id', 'id');
    }

    public function cities()
    {
        return $this->hasMany(City::class, 'city_id', 'id');
    }

    public function businessAppointments()
    {
        return $this->hasMany(BusinessAppointments::class);
    }

    /**
     * Obtener nombre normalizado para matching con GeoJSON
     */
    public function getNormalizedDefinitionAttribute(): string
    {
        $name = $this->definition;

        // Eliminar acentos y normalizar
        $name = Str::ascii($name);
        $name = Str::title(Str::lower($name));

        // Correcciones específicas para Venezuela
        $corrections = [
            'Bolivar' => 'Bolívar',
            'Anzoategui' => 'Anzoátegui',
            'Merida' => 'Mérida',
            'Tachira' => 'Táchira',
            'Nva Esparta' => 'Nueva Esparta',
            'Df' => 'Distrito Capital',
            'Capital' => 'Distrito Capital',
        ];

        return $corrections[$name] ?? $name;
    }
}
