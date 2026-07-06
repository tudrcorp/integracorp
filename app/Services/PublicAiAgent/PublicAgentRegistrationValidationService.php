<?php

declare(strict_types=1);

namespace App\Services\PublicAiAgent;

use App\Models\Agency;
use App\Models\Agent;
use App\Models\ProspectAgent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class PublicAgentRegistrationValidationService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{errors: list<string>, agencies: list<array{id: int, name: string, code: string|null, label: string}>}
     */
    public function validateSimplifiedPayload(array $payload): array
    {
        $errors = $this->validateSimplifiedContactFields($payload);

        $classification = (string) ($payload['classification'] ?? '');
        if (! in_array($classification, ['agent', 'subagent'], true)) {
            $errors[] = 'Indica el tipo de perfil con 1 (Agente) o 2 (Subagente) en el quinto dato de tu línea.';
        }

        $birthDateRaw = trim((string) ($payload['birth_date'] ?? $payload['birth_date_input'] ?? ''));
        if ($birthDateRaw === '') {
            $errors[] = 'Falta la fecha de nacimiento. Escríbela como tercer dato en formato dd/mm/yyyy (ejemplo: 05/01/1984).';
        } elseif (app(IntentSlotFiller::class)->parseBirthDate($birthDateRaw) === null) {
            $errors[] = 'La fecha de nacimiento no es válida. Usa el formato dd/mm/yyyy (ejemplo: 05/01/1984).';
        }

        $identityRaw = trim((string) ($payload['identity_document'] ?? ''));
        if ($identityRaw === '') {
            $errors[] = 'Falta el número de cédula o RIF. Escríbelo como segundo dato con prefijo v-, e- o j- (ejemplo: v-16007868).';
        } else {
            $parsedIdentity = ChatAgentIdentityDocument::parse($identityRaw);

            if ($parsedIdentity === null) {
                $errors[] = 'El documento debe iniciar con v-, e- o j- seguido del número. Ejemplo: v-16007868, e-12321345, j-23456789.';
            } elseif (ChatAgentIdentityDocument::existsInAgents($parsedIdentity)) {
                $errors[] = 'Este número de cédula o RIF ya está registrado en el sistema. Verifica el dato o contacta a un asesor.';
            }
        }

        $agencyTerm = trim((string) ($payload['agency_name'] ?? ''));
        $agencies = [];

        if ($agencyTerm === '') {
            $errors[] = 'Falta la razón social de la agencia. Si perteneces a TuDrGroup, escribe TDG como último dato.';
        } else {
            $agencies = $this->findAgenciesByTerm($agencyTerm);
        }

        return [
            'errors' => $errors,
            'agencies' => $agencies,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{errors: list<string>, agencies: list<array{id: int, name: string, code: string|null, label: string}>}
     */
    public function validateSimplifiedAgencyMasterPayload(array $payload): array
    {
        $errors = $this->validateSimplifiedContactFields($payload, requireName: false);

        $corporateName = trim((string) ($payload['agency_corporate_name'] ?? ''));
        if ($corporateName === '' || mb_strlen($corporateName) < 3) {
            $errors[] = 'Falta la razón social de la agencia master. Escríbela como primer dato en tu línea.';
        }

        $taxId = trim((string) ($payload['tax_id'] ?? ''));
        if ($taxId === '' || mb_strlen($taxId) < 6) {
            $errors[] = 'Falta el RIF o número de cédula del representante. Escríbelo como segundo dato con prefijo j-, v- o e- (ejemplo: j-123456789, v-12345678 o e-12345654).';
        }

        return [
            'errors' => $errors,
            'agencies' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{errors: list<string>, agencies: list<array{id: int, name: string, code: string|null, label: string}>}
     */
    public function validateSimplifiedAgencyGeneralPayload(array $payload): array
    {
        $errors = $this->validateSimplifiedContactFields($payload, requireName: false);

        $corporateName = trim((string) ($payload['agency_corporate_name'] ?? ''));
        if ($corporateName === '' || mb_strlen($corporateName) < 3) {
            $errors[] = 'Falta la razón social de la agencia general. Escríbela como primer dato en tu línea.';
        }

        $masterAgencyTerm = trim((string) ($payload['master_agency_name'] ?? ''));
        $agencies = [];

        if ($masterAgencyTerm === '') {
            $errors[] = 'Falta la razón social de la agencia master. Si no pertenece a ninguna, escribe TDG como segundo dato.';
        } elseif (! $this->isTdgMasterTerm($masterAgencyTerm)) {
            $agencies = $this->findMasterAgenciesByTerm($masterAgencyTerm);
        }

        $taxId = trim((string) ($payload['tax_id'] ?? ''));
        if ($taxId === '' || mb_strlen($taxId) < 6) {
            $errors[] = 'Falta el RIF o número de cédula del representante. Escríbelo como tercer dato con prefijo j-, v- o e- (ejemplo: j-123456789, v-12345678 o e-12345654).';
        } elseif (ChatAgencyRepresentativeDocument::existsByRawInput($taxId)) {
            $errors[] = 'Este RIF o cédula ya está registrado en el sistema. Verifica el dato o contacta a un asesor.';
        }

        return [
            'errors' => $errors,
            'agencies' => $agencies,
        ];
    }

    public function taxIdExistsInAgencies(string $taxId): bool
    {
        return app(ChatAgencyGeneralRegistrationService::class)->taxIdExistsInAgencies($taxId);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function resolveGeneralRegistrationOwnerCode(array $payload): string
    {
        $ownerCode = trim((string) ($payload['owner_code'] ?? ''));

        if ($ownerCode !== '') {
            return $ownerCode;
        }

        if ($this->isTdgMasterTerm((string) ($payload['master_agency_name'] ?? ''))) {
            return 'TDG-100';
        }

        $masterId = (int) ($payload['selected_master_agency_id'] ?? 0);

        if ($masterId > 0 && Schema::hasTable('agencies') && Schema::hasColumn('agencies', 'code')) {
            $masterCode = Agency::query()->whereKey($masterId)->value('code');

            if (is_string($masterCode) && trim($masterCode) !== '') {
                return trim($masterCode);
            }
        }

        $label = trim((string) ($payload['selected_master_agency_label'] ?? ''));

        if ($label !== '' && str_contains($label, ' — ')) {
            $parts = explode(' — ', $label);

            return trim((string) end($parts));
        }

        return '';
    }

    public function isTdgMasterTerm(string $term): bool
    {
        $normalized = mb_strtolower(trim($term));

        return in_array($normalized, ['tdg', 'tdg-100', 'tdg100'], true);
    }

    public function isExactTdgAgencyTerm(string $term): bool
    {
        return mb_strtolower(trim($term)) === 'tdg';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function applyTdgAgency(array $payload): array
    {
        $payload['agency_name'] = 'TDG';
        $payload['selected_agency_label'] = 'TuDrGroup — TDG-100';
        $payload['owner_code'] = 'TDG-100';
        $payload['belongs_to_tudrgroup_structure'] = true;
        $payload['initial_observ'] = 'Agencia: TuDrGroup — TDG-100';
        unset($payload['selected_agency_id']);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function belongsToTudrgroupStructure(array $payload): bool
    {
        if (($payload['belongs_to_tudrgroup_structure'] ?? false) === true) {
            return true;
        }

        return $this->isExactTdgAgencyTerm((string) ($payload['agency_name'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function applyTdgMasterAgency(array $payload): array
    {
        $payload['master_agency_name'] = 'TDG';
        $payload['selected_master_agency_label'] = 'TDG-100';
        $payload['owner_code'] = 'TDG-100';
        unset($payload['selected_master_agency_id']);

        return $payload;
    }

    /**
     * @return list<array{id: int, name: string, code: string|null, label: string}>
     */
    public function findMasterAgenciesByTerm(string $term): array
    {
        return $this->findAgenciesByTerm($term, masterOnly: true);
    }

    /**
     * @return list<array{id: int, name: string, code: string|null, label: string}>
     */
    public function findAgenciesByTerm(string $term, bool $masterOnly = false): array
    {
        if (! Schema::hasTable('agencies') || ! Schema::hasColumn('agencies', 'name_corporative')) {
            return [];
        }

        $trimmed = trim($term);
        $normalized = mb_strtolower($trimmed);

        if ($normalized === '' || mb_strlen($normalized) < 2) {
            return [];
        }

        $selectColumns = $this->agencySelectColumns();
        $likeTerm = '%'.$normalized.'%';
        $startsWithTerm = $normalized.'%';

        $matches = $this->newAgencySearchQuery($selectColumns, $masterOnly)
            ->where(function (Builder $builder) use ($likeTerm): void {
                $builder->whereRaw('LOWER(name_corporative) LIKE ?', [$likeTerm]);
                $this->applyAgencyFuzzyColumnMatch($builder, $likeTerm);
            })
            ->orderByRaw(
                'CASE
                    WHEN LOWER(name_corporative) = ? THEN 0
                    WHEN LOWER(name_corporative) LIKE ? THEN 1
                    ELSE 2
                END',
                [$normalized, $startsWithTerm],
            )
            ->orderBy('name_corporative')
            ->limit(15)
            ->get();

        if ($matches->isNotEmpty()) {
            return $this->mapAgencyResults($matches);
        }

        $collapsedTerm = preg_replace('/\s+/', '', $normalized) ?? $normalized;

        if ($collapsedTerm !== $normalized && mb_strlen($collapsedTerm) >= 2) {
            $collapsedLike = '%'.$collapsedTerm.'%';

            $collapsedMatches = $this->newAgencySearchQuery($selectColumns, $masterOnly)
                ->where(function (Builder $builder) use ($collapsedLike): void {
                    $builder->whereRaw('REPLACE(LOWER(name_corporative), " ", "") LIKE ?', [$collapsedLike]);
                    $this->applyAgencyFuzzyColumnMatch($builder, $collapsedLike, stripSpaces: true);
                })
                ->orderBy('name_corporative')
                ->limit(15)
                ->get();

            if ($collapsedMatches->isNotEmpty()) {
                return $this->mapAgencyResults($collapsedMatches);
            }
        }

        $tokens = $this->searchTokensFromTerm($normalized);

        if (count($tokens) >= 2) {
            $tokenMatches = $this->newAgencySearchQuery($selectColumns, $masterOnly);

            foreach ($tokens as $token) {
                $tokenLike = '%'.$token.'%';

                $tokenMatches->where(function (Builder $builder) use ($tokenLike): void {
                    $builder->whereRaw('LOWER(name_corporative) LIKE ?', [$tokenLike]);
                    $this->applyAgencyFuzzyColumnMatch($builder, $tokenLike);
                });
            }

            $tokenResults = $tokenMatches
                ->orderBy('name_corporative')
                ->limit(15)
                ->get();

            if ($tokenResults->isNotEmpty()) {
                return $this->mapAgencyResults($tokenResults);
            }
        }

        return [];
    }

    public function masterAgencyNotFoundMessage(string $term): string
    {
        return sprintf(
            'No encontramos agencias master que contengan "%s". Escribe parte del nombre o código, o escribe TDG si no pertenece a ninguna agencia master. Puedes enviar solo el dato correcto sin repetir tus datos.',
            $term,
        );
    }

    public function masterAgencySelectionPrompt(array $candidates): string
    {
        if (count($candidates) === 1) {
            return sprintf(
                'Encontré una agencia master que coincide con tu búsqueda: %s. Responde 1 para confirmar o escribe más letras del nombre.',
                $candidates[0]['label'],
            );
        }

        $lines = [
            'Encontré varias agencias master que coinciden con tu búsqueda. Indica el número de tu agencia master:',
            '',
        ];

        foreach ($candidates as $index => $candidate) {
            $lines[] = sprintf('%d) %s', $index + 1, $candidate['label']);
        }

        $lines[] = '';
        $lines[] = 'Responde con el número de la opción (ejemplo: 1).';

        return implode("\n", $lines);
    }

    /**
     * @param  array{id: int, name: string, code: string|null, label: string}  $agency
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function applySelectedMasterAgency(array $agency, array $payload): array
    {
        $payload['selected_master_agency_id'] = $agency['id'];
        $payload['master_agency_name'] = $agency['name'];
        $payload['selected_master_agency_label'] = $agency['label'];
        $payload['owner_code'] = $agency['code'] ?? 'TDG-100';

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function validateSimplifiedContactFields(array $payload, bool $requireName = true): array
    {
        $errors = [];

        if ($requireName) {
            $name = trim((string) ($payload['name'] ?? ''));
            if ($name === '' || mb_strlen($name) < 3) {
                $errors[] = 'El nombre y apellido debe tener al menos 3 caracteres. Revisa el primer dato de tu línea.';
            }
        }

        $email = mb_strtolower(trim((string) ($payload['email'] ?? '')));
        if ($email === '') {
            $errors[] = 'No detectamos un correo electrónico válido. Usa el formato: nombre, teléfono, correo y los demás datos.';
        } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'El correo electrónico no tiene un formato válido. Ejemplo: maria@correo.com';
        } elseif ($this->emailExists($email)) {
            $errors[] = 'Este correo electrónico ya está registrado en el sistema. Usa otro correo o contacta a un asesor si crees que es un error.';
        }

        $phoneDigits = preg_replace('/\D+/', '', (string) ($payload['phone_1'] ?? '')) ?? '';
        if ($phoneDigits === '') {
            $errors[] = 'No detectamos un número de teléfono. Escribe solo números, ejemplo: 04141234567.';
        } elseif (! $this->isValidPhoneFormat($phoneDigits)) {
            $errors[] = 'El teléfono debe tener entre 10 y 13 dígitos y solo números. Ejemplo: 04141234567.';
        } elseif ($this->phoneExistsInUsers($phoneDigits)) {
            $errors[] = 'Este número de teléfono ya está registrado en el sistema. Verifica el número o contacta a un asesor.';
        }

        return $errors;
    }

    /**
     * @param  list<string>  $selectColumns
     */
    private function newAgencySearchQuery(array $selectColumns, bool $masterOnly): Builder
    {
        $query = Agency::query()->select($selectColumns);

        if ($masterOnly && $this->agencyTableHasColumn('agency_type_id')) {
            $query->where('agency_type_id', 1);
        }

        $this->applySearchableAgencyStatusFilter($query);

        return $query;
    }

    private function applySearchableAgencyStatusFilter(Builder $query): void
    {
        if (! $this->agencyTableHasColumn('status')) {
            return;
        }

        $query->where(function (Builder $builder): void {
            $builder->whereIn('status', ['ACTIVO', 'POR REVISION'])
                ->orWhereNull('status');
        });
    }

    /**
     * @return list<string>
     */
    private function searchTokensFromTerm(string $normalizedTerm): array
    {
        $tokens = preg_split('/\s+/', $normalizedTerm) ?: [];

        return array_values(array_filter(
            $tokens,
            static fn (string $token): bool => mb_strlen($token) >= 2,
        ));
    }

    /**
     * @param  array{id: int, name: string, code: string|null, label: string}  $agency
     */
    public function isExactAgencyMatch(string $term, array $agency): bool
    {
        $normalized = mb_strtolower(trim($term));

        if ($normalized === '') {
            return false;
        }

        if (mb_strtolower($agency['name']) === $normalized) {
            return true;
        }

        if ($agency['code'] !== null && mb_strtolower($agency['code']) === $normalized) {
            return true;
        }

        return false;
    }

    /**
     * @param  list<array{id: int, name: string, code: string|null, label: string}>  $candidates
     */
    public function resolveAgencySelection(string $message, array $candidates): ?array
    {
        $trimmed = trim($message);

        if (preg_match('/^\d+$/', $trimmed) === 1) {
            $index = (int) $trimmed - 1;

            if (isset($candidates[$index])) {
                return $candidates[$index];
            }
        }

        $normalizedMessage = mb_strtolower($trimmed);

        foreach ($candidates as $candidate) {
            if (mb_strtolower($candidate['name']) === $normalizedMessage) {
                return $candidate;
            }

            if ($candidate['code'] !== null && mb_strtolower($candidate['code']) === $normalizedMessage) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  list<array{id: int, name: string, code: string|null, label: string}>  $candidates
     */
    public function agencySelectionPrompt(array $candidates): string
    {
        if (count($candidates) === 1) {
            return sprintf(
                'Encontré una agencia que coincide con tu búsqueda: %s. Responde 1 para confirmar o escribe más letras de la razón social.',
                $candidates[0]['label'],
            );
        }

        $lines = [
            'Encontré varias agencias que coinciden con tu búsqueda. Indica el número de tu agencia:',
            '',
        ];

        foreach ($candidates as $index => $candidate) {
            $lines[] = sprintf('%d) %s', $index + 1, $candidate['label']);
        }

        $lines[] = '';
        $lines[] = 'Responde con el número de la opción (ejemplo: 1).';

        return implode("\n", $lines);
    }

    public function agencyNotFoundMessage(string $term): string
    {
        return sprintf(
            'No encontramos agencias que contengan "%s". Escribe parte del nombre o código (ejemplo: TDG, ABP, VMG). Puedes enviar solo la razón social o código correcto sin repetir tus datos.',
            $term,
        );
    }

    /**
     * @param  list<string>  $errors
     */
    public function formatValidationErrors(array $errors): string
    {
        if ($errors === []) {
            return 'Hay datos que debemos corregir antes de continuar.';
        }

        if (count($errors) === 1) {
            return $errors[0]."\n\nPuedes enviar solo el dato correcto sin repetir toda la información.";
        }

        $lines = ['Antes de continuar, revisa lo siguiente:', ''];

        foreach ($errors as $error) {
            $lines[] = '• '.$error;
        }

        $lines[] = '';
        $lines[] = 'Corrige el dato indicado y envíalo solo (cédula/RIF, correo, teléfono, tipo 1/2, nombre o agencia), o la línea completa con comas si prefieres.';

        return implode("\n", $lines);
    }

    /**
     * @param  array{id: int, name: string, code: string|null, label: string}  $agency
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function applySelectedAgency(array $agency, array $payload): array
    {
        $payload['selected_agency_id'] = $agency['id'];
        $payload['agency_name'] = $agency['name'];
        $payload['selected_agency_label'] = $agency['label'];
        $payload['initial_observ'] = sprintf('Agencia: %s (ID: %d)', $agency['label'], $agency['id']);

        return $payload;
    }

    /**
     * @return list<string>
     */
    private function agencySelectColumns(): array
    {
        $columns = ['id', 'name_corporative'];

        if ($this->agencyTableHasColumn('code')) {
            $columns[] = 'code';
        }

        if ($this->agencyTableHasColumn('code_agency')) {
            $columns[] = 'code_agency';
        }

        if ($this->agencyTableHasColumn('rif')) {
            $columns[] = 'rif';
        }

        return $columns;
    }

    private function agencyTableHasColumn(string $column): bool
    {
        return Schema::hasTable('agencies') && Schema::hasColumn('agencies', $column);
    }

    private function applyAgencyFuzzyColumnMatch(Builder $builder, string $likeTerm, bool $stripSpaces = false): void
    {
        if ($this->agencyTableHasColumn('code')) {
            if ($stripSpaces) {
                $builder->orWhereRaw('REPLACE(LOWER(code), " ", "") LIKE ?', [$likeTerm]);
            } else {
                $builder->orWhereRaw('LOWER(code) LIKE ?', [$likeTerm]);
            }
        }

        if ($this->agencyTableHasColumn('code_agency')) {
            if ($stripSpaces) {
                $builder->orWhereRaw('REPLACE(LOWER(code_agency), " ", "") LIKE ?', [$likeTerm]);
            } else {
                $builder->orWhereRaw('LOWER(code_agency) LIKE ?', [$likeTerm]);
            }
        }

        if ($this->agencyTableHasColumn('rif')) {
            if ($stripSpaces) {
                $builder->orWhereRaw('REPLACE(LOWER(rif), " ", "") LIKE ?', [$likeTerm]);
            } else {
                $builder->orWhereRaw('LOWER(rif) LIKE ?', [$likeTerm]);
            }
        }
    }

    /**
     * @return list<array{id: int, name: string, code: string|null, label: string}>
     */
    private function mapAgencyResults(Collection $agencies): array
    {
        return $agencies
            ->map(function (Agency $agency): array {
                $name = trim((string) $agency->name_corporative);
                $code = $this->agencyCodeFromModel($agency);

                return [
                    'id' => (int) $agency->id,
                    'name' => $name,
                    'code' => $code !== '' ? $code : null,
                    'label' => $code !== '' ? "{$name} — {$code}" : $name,
                ];
            })
            ->values()
            ->all();
    }

    private function agencyCodeFromModel(Agency $agency): string
    {
        if ($this->agencyTableHasColumn('code') && $agency->code !== null && trim((string) $agency->code) !== '') {
            return trim((string) $agency->code);
        }

        if ($this->agencyTableHasColumn('code_agency') && $agency->code_agency !== null && trim((string) $agency->code_agency) !== '') {
            return trim((string) $agency->code_agency);
        }

        return '';
    }

    private function emailExists(string $email): bool
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'email')) {
            if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
                return true;
            }
        }

        if (
            Schema::hasTable('agents')
            && Schema::hasColumn('agents', 'email')
            && Agent::query()->whereRaw('LOWER(email) = ?', [$email])->exists()
        ) {
            return true;
        }

        if (
            Schema::hasTable('prospect_agents')
            && Schema::hasColumn('prospect_agents', 'email')
            && ProspectAgent::query()->whereRaw('LOWER(email) = ?', [$email])->exists()
        ) {
            return true;
        }

        if (
            Schema::hasTable('agencies')
            && Schema::hasColumn('agencies', 'email')
            && Agency::query()->whereRaw('LOWER(email) = ?', [$email])->exists()
        ) {
            return true;
        }

        return false;
    }

    private function phoneExistsInUsers(string $phoneDigits): bool
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'phone')) {
            return false;
        }

        return User::query()
            ->where(function ($query) use ($phoneDigits): void {
                $query->where('phone', $phoneDigits)
                    ->orWhere('phone', 'like', '%'.$phoneDigits);
            })
            ->exists();
    }

    private function isValidPhoneFormat(string $phoneDigits): bool
    {
        $length = strlen($phoneDigits);

        return $length >= 10 && $length <= 13 && ctype_digit($phoneDigits);
    }
}
