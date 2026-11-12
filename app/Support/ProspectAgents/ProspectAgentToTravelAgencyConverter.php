<?php

declare(strict_types=1);

namespace App\Support\ProspectAgents;

use App\Filament\Business\Resources\ProspectAgents\ProspectAgentLabels;
use App\Models\ProspectAgent;
use App\Models\ProspectAgentContact;
use App\Models\TravelAgency;
use App\Models\TravelAgent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cuando un prospecto de Captación pasa a «Aliado activo», crea (o reutiliza)
 * su ficha en Agencias de viaje. Idempotente: no duplica si ya existe el vínculo.
 */
final class ProspectAgentToTravelAgencyConverter
{
    public static function convertIfEligible(ProspectAgent $prospect): ?TravelAgency
    {
        if (! self::isEligible($prospect)) {
            return null;
        }

        if (! Schema::hasTable('travel_agencies') || ! Schema::hasColumn('travel_agencies', 'prospect_agent_id')) {
            return null;
        }

        return DB::transaction(function () use ($prospect): ?TravelAgency {
            $locked = ProspectAgent::query()
                ->with(['prospectAgentContacts' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
                ->lockForUpdate()
                ->find($prospect->getKey());

            if (! $locked instanceof ProspectAgent || ! self::isEligible($locked)) {
                return self::existingAgency($prospect);
            }

            $existing = self::existingAgency($locked);

            if ($existing instanceof TravelAgency) {
                self::syncReverseLink($locked, $existing);

                return $existing;
            }

            $agency = TravelAgency::query()->create(self::agencyAttributes($locked));

            self::syncReverseLink($locked, $agency);
            self::copyContactsAsTravelAgents($locked, $agency);

            return $agency->fresh() ?? $agency;
        });
    }

    public static function isEligible(ProspectAgent $prospect): bool
    {
        if ($prospect->trashed()) {
            return false;
        }

        $status = ProspectAgentLabels::normalizeStatus(
            is_string($prospect->status) ? $prospect->status : null
        );

        return $status === ProspectAgentLabels::STATUS_ALIADO_ACTIVO;
    }

    public static function existingAgency(ProspectAgent $prospect): ?TravelAgency
    {
        if (Schema::hasColumn('travel_agencies', 'prospect_agent_id')) {
            $byProspect = TravelAgency::query()
                ->where('prospect_agent_id', $prospect->getKey())
                ->first();

            if ($byProspect instanceof TravelAgency) {
                return $byProspect;
            }
        }

        if (
            Schema::hasColumn('prospect_agents', 'travel_agency_id')
            && filled($prospect->travel_agency_id)
        ) {
            $byLink = TravelAgency::query()->find($prospect->travel_agency_id);

            if ($byLink instanceof TravelAgency) {
                return $byLink;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function agencyAttributes(ProspectAgent $prospect): array
    {
        $firstContact = $prospect->prospectAgentContacts->first(
            fn (ProspectAgentContact $contact): bool => filled(trim((string) $contact->name))
        );

        $attributes = [
            'name' => self::upper($prospect->name) ?? 'PROSPECTO #'.$prospect->getKey(),
            'email' => self::email($prospect->email),
            'phone' => self::plain($prospect->phone_1),
            'phoneAdditional' => self::plain($prospect->phone_2),
            'address' => self::plain($prospect->address),
            'userInstagram' => self::instagram($prospect->instagram),
            'country_id' => $prospect->country_id,
            'state_id' => $prospect->state_id,
            'city_id' => $prospect->city_id,
            'classification' => self::classificationFromType(
                is_string($prospect->type) ? $prospect->type : null
            ),
            'status' => 'Activo',
            'fechaIngreso' => now()->format('d/m/Y'),
            'nivel' => '2',
            'agenciaPpalNivel1' => 'TDEV',
            'representante' => self::upper($firstContact?->name),
            'nameSecundario' => self::upper($firstContact?->name),
            'emailSecundario' => self::email($firstContact?->email),
            'phoneSecundario' => self::plain($firstContact?->phone ?? $firstContact?->local_phone),
            'created_by' => self::plain($prospect->created_by) ?? 'Captación → Aliado activo',
            'updated_by' => self::plain($prospect->updated_by),
        ];

        if (Schema::hasColumn('travel_agencies', 'prospect_agent_id')) {
            $attributes['prospect_agent_id'] = $prospect->getKey();
        }

        return $attributes;
    }

    private static function syncReverseLink(ProspectAgent $prospect, TravelAgency $agency): void
    {
        if (! Schema::hasColumn('prospect_agents', 'travel_agency_id')) {
            return;
        }

        if ((int) $prospect->travel_agency_id === (int) $agency->getKey()) {
            return;
        }

        $prospect->forceFill([
            'travel_agency_id' => $agency->getKey(),
        ])->saveQuietly();
    }

    private static function copyContactsAsTravelAgents(ProspectAgent $prospect, TravelAgency $agency): void
    {
        if (! Schema::hasTable('travel_agents')) {
            return;
        }

        foreach ($prospect->prospectAgentContacts as $contact) {
            $name = self::upper($contact->name);

            if ($name === null) {
                continue;
            }

            TravelAgent::query()->create([
                'travel_agency_id' => $agency->getKey(),
                'name' => $name,
                'cargo' => self::upper($contact->position),
                'email' => self::email($contact->email),
                'phone' => self::plain($contact->phone ?? $contact->local_phone),
                'created_by' => 'Captación → Aliado activo',
            ]);
        }
    }

    public static function classificationFromType(?string $type): string
    {
        $normalized = mb_strtolower(trim((string) $type));

        return match ($normalized) {
            'mayorista-viajes' => 'MAYORISTA',
            'freelance' => 'FREELANCE',
            'agente-corretaje', 'agencia-corretaje' => 'AGENTE DE CORRETAJE',
            'asesor-exclusivo', 'ejecutivo' => 'AGENTE',
            default => 'AGENCIA DE VIAJES',
        };
    }

    private static function upper(mixed $value): ?string
    {
        $value = self::plain($value);

        return $value === null ? null : mb_strtoupper($value);
    }

    private static function email(mixed $value): ?string
    {
        $value = self::plain($value);

        return $value === null ? null : mb_strtolower($value);
    }

    private static function instagram(mixed $value): ?string
    {
        $value = self::plain($value);

        if ($value === null) {
            return null;
        }

        return ltrim($value, '@');
    }

    private static function plain(mixed $value): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');

        return $value === '' ? null : $value;
    }
}
