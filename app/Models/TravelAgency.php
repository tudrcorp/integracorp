<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TravelAgency extends Model
{
    //
    protected $table = 'travel_agencies';

    protected $fillable = [
        'status',
        'fechaIngreso',
        'representante',
        'idRepresentante',
        'FechaNacimientoRepresentante',
        'name',
        'id_agencia',
        'id_de_agente',
        'typeIdentification',
        'numberIdentification',
        'userPortalWeb',
        'aniversary',
        'country_id',
        'state_id',
        'city_id',
        'address',
        'phone',
        'phoneAdditional',
        'email',
        'userInstagram',
        'classification',
        'comision',
        'montoCreditoAprobado',
        'nivel',
        'agenteSuperiorNivel3',
        'agenciaSuperiorNivel2',
        'agenciaPpalNivel1',
        'created_by',
        'updated_by',
        'parent_id',
        'registration_token',
        'agency_registration_token',

        'logo',
        'nameSecundario',
        'emailSecundario',
        'phoneSecundario',
        'fechaNacimientoSecundario',

        // datos bancarios moneda local
        'local_beneficiary_name',
        'local_beneficiary_rif',
        'local_beneficiary_account_number',
        'local_beneficiary_account_bank',
        'local_beneficiary_account_type',
        'local_beneficiary_phone_pm',
        'local_beneficiary_account_number_mon_inter',
        'local_beneficiary_account_bank_mon_inter',
        'local_beneficiary_account_type_mon_inter',

        // datos bancarios moneda extrangera
        'extra_beneficiary_name',
        'extra_beneficiary_ci_rif',
        'extra_beneficiary_account_number',
        'extra_beneficiary_account_bank',
        'extra_beneficiary_account_type',
        'extra_beneficiary_route',
        'extra_beneficiary_zelle',
        'extra_beneficiary_ach',
        'extra_beneficiary_swift',
        'extra_beneficiary_aba',
        'extra_beneficiary_address',

    ];

    protected static function booted(): void
    {
        static::creating(function (TravelAgency $agency): void {
            if (blank($agency->registration_token)) {
                $agency->registration_token = (string) Str::uuid();
            }

            if (blank($agency->agency_registration_token)) {
                $agency->agency_registration_token = (string) Str::uuid();
            }
        });
    }

    public function travelAgents(): HasMany
    {
        return $this->hasMany(TravelAgent::class)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function parentAgency(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function childAgencies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function logoUrl(): ?string
    {
        if (blank($this->logo)) {
            return null;
        }

        return asset('storage/'.$this->logo);
    }

    public function faviconUrl(): string
    {
        return $this->logoUrl() ?? asset('image/logo-tdev.png');
    }

    /**
     * @return list<string>
     */
    public function registrationHierarchyLines(): array
    {
        $lines = [];
        $nivel = trim((string) $this->nivel);

        if ($nivel !== '') {
            $lines[] = preg_match('/nivel/i', $nivel) === 1 ? $nivel : 'Nivel '.$nivel;
        }

        if (filled($this->agenciaPpalNivel1)) {
            $lines[] = 'Agencia principal: '.mb_strtoupper(trim((string) $this->agenciaPpalNivel1));
        }

        if (filled($this->agenciaSuperiorNivel2)) {
            $lines[] = 'Agencia superior: '.mb_strtoupper(trim((string) $this->agenciaSuperiorNivel2));
        }

        if (filled($this->agenteSuperiorNivel3)) {
            $lines[] = 'Agente superior: '.mb_strtoupper(trim((string) $this->agenteSuperiorNivel3));
        }

        $parentName = trim((string) $this->parentAgency?->name);

        if ($parentName !== '') {
            $lines[] = 'Registrada bajo: '.$parentName;
        }

        return $lines;
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function observationCommercialStructures()
    {
        return $this->hasMany(ObservationCommercialStructure::class)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id_agencia' => 'integer',
        ];
    }
}
