<?php

declare(strict_types=1);

namespace App\Support\Tdev;

use App\Models\TdevAgency;
use App\Models\TdevAgent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class TdevAgencyRegistrar
{
    public const REGISTRATION_SOURCE_PUBLIC = 'public_form';

    public const REGISTRATION_SOURCE_PANEL = 'panel';

    public static function publicLandingUrl(TdevAgency $agency): string
    {
        if (! $agency->isLevelTwo()) {
            throw new \InvalidArgumentException('Solo las agencias nivel 2 tienen página web pública.');
        }

        $token = $agency->registration_token;

        if (blank($token)) {
            $agency->update(['registration_token' => (string) Str::uuid()]);
            $token = $agency->fresh()?->registration_token;
        }

        return route('tdev-agencies.landing', ['token' => $token]);
    }

    public static function publicAgentRegistrationUrl(TdevAgency $agency): string
    {
        $token = $agency->registration_token;

        if (blank($token)) {
            $agency->update(['registration_token' => (string) Str::uuid()]);
            $token = $agency->fresh()?->registration_token;
        }

        return route('tdev-agents.register', ['token' => $token]);
    }

    /**
     * @deprecated Use publicAgentRegistrationUrl()
     */
    public static function publicRegistrationUrl(TdevAgency $agency): string
    {
        return self::publicAgentRegistrationUrl($agency);
    }

    public static function publicLevelThreeAgencyRegistrationUrl(TdevAgency $parentAgency): string
    {
        if (! $parentAgency->isLevelTwo()) {
            throw new \InvalidArgumentException('Solo las agencias nivel 2 pueden generar URL de registro de agencias nivel 3.');
        }

        $token = $parentAgency->agency_registration_token;

        if (blank($token)) {
            $parentAgency->update(['agency_registration_token' => (string) Str::uuid()]);
            $token = $parentAgency->fresh()?->agency_registration_token;
        }

        return route('tdev-agencies.register', ['token' => $token]);
    }

    /**
     * @param  array{
     *     full_name: string,
     *     position?: string|null,
     *     email?: string|null,
     *     phone?: string|null,
     *     birth_date?: string|null,
     * }  $data
     */
    public static function registerAgent(TdevAgency $agency, array $data, string $source = self::REGISTRATION_SOURCE_PUBLIC): TdevAgent
    {
        $agent = DB::transaction(function () use ($agency, $data, $source): TdevAgent {
            return $agency->agents()->create([
                'full_name' => Str::upper(trim($data['full_name'])),
                'position' => filled($data['position'] ?? null) ? Str::upper(trim((string) $data['position'])) : null,
                'email' => filled($data['email'] ?? null) ? Str::lower(trim((string) $data['email'])) : null,
                'phone' => filled($data['phone'] ?? null) ? trim((string) $data['phone']) : null,
                'birth_date' => $data['birth_date'] ?? null,
                'registered_at' => now(),
                'registration_source' => $source,
                'created_by' => $source === self::REGISTRATION_SOURCE_PUBLIC ? 'Formulario público' : null,
            ]);
        });

        if ($source === self::REGISTRATION_SOURCE_PUBLIC) {
            TdevRegistrationNotifier::notifyAgent((int) $agent->getKey());
        }

        return $agent;
    }

    /**
     * @param  array{
     *     name: string,
     *     identification_number?: string|null,
     *     email?: string|null,
     *     anniversary_date?: string|null,
     *     representative_name?: string|null,
     *     representative_birth_date?: string|null,
     *     phone?: string|null,
     *     phone_additional?: string|null,
     *     instagram_username?: string|null,
     *     country_id?: int|null,
     *     state_id?: int|null,
     *     city_id?: int|null,
     *     address?: string|null,
     *     url?: string|null,
     *     logo?: UploadedFile|null,
     * }  $data
     */
    public static function registerLevelThreeAgency(TdevAgency $parentAgency, array $data): TdevAgency
    {
        if (! $parentAgency->isLevelTwo()) {
            throw new \InvalidArgumentException('Solo una agencia nivel 2 puede registrar agencias nivel 3.');
        }

        $agency = DB::transaction(function () use ($parentAgency, $data): TdevAgency {
            $logoPath = null;

            if (($data['logo'] ?? null) instanceof UploadedFile) {
                $logoPath = $data['logo']->store('logos-agencias-tdev', 'public');
            }

            return TdevAgency::query()->create([
                'level' => TdevAgency::LEVEL_THREE,
                'parent_id' => $parentAgency->id,
                'name' => Str::upper(trim($data['name'])),
                'identification_number' => filled($data['identification_number'] ?? null) ? trim((string) $data['identification_number']) : null,
                'email' => filled($data['email'] ?? null) ? Str::lower(trim((string) $data['email'])) : null,
                'anniversary_date' => $data['anniversary_date'] ?? null,
                'representative_name' => filled($data['representative_name'] ?? null) ? Str::upper(trim((string) $data['representative_name'])) : null,
                'representative_birth_date' => $data['representative_birth_date'] ?? null,
                'phone' => filled($data['phone'] ?? null) ? trim((string) $data['phone']) : null,
                'phone_additional' => filled($data['phone_additional'] ?? null) ? trim((string) $data['phone_additional']) : null,
                'instagram_username' => filled($data['instagram_username'] ?? null) ? ltrim(trim((string) $data['instagram_username']), '@') : null,
                'country_id' => $data['country_id'] ?? null,
                'state_id' => $data['state_id'] ?? null,
                'city_id' => $data['city_id'] ?? null,
                'address' => filled($data['address'] ?? null) ? trim((string) $data['address']) : null,
                'url' => filled($data['url'] ?? null) ? trim((string) $data['url']) : null,
                'logo' => $logoPath,
                'registration_token' => (string) Str::uuid(),
                'created_by' => 'Formulario público nivel 3',
            ]);
        });

        TdevRegistrationNotifier::notifyAgency((int) $agency->getKey());

        return $agency;
    }
}
