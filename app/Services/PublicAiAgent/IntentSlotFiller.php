<?php

declare(strict_types=1);

namespace App\Services\PublicAiAgent;

use App\Models\City;
use App\Models\Country;
use App\Models\State;
use Illuminate\Support\Str;

class IntentSlotFiller
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function applyActionPreset(?string $action, array $payload): array
    {
        $presetPayload = $payload;

        return match ($action) {
            AgentConversationStateMachine::ACTION_QUOTE_INDIVIDUAL => [
                ...$presetPayload,
                'quote_type' => 'individual',
            ],
            AgentConversationStateMachine::ACTION_QUOTE_CORPORATE => [
                ...$presetPayload,
                'quote_type' => 'corporativo',
            ],
            AgentConversationStateMachine::ACTION_REGISTER_AGENCY_MASTER => [
                ...$presetPayload,
                'type' => 'agencia-corretaje',
                'classification' => 'agency-master',
            ],
            AgentConversationStateMachine::ACTION_REGISTER_AGENCY_GENERAL => [
                ...$presetPayload,
                'type' => 'agencia-corretaje',
                'classification' => 'agency-general',
            ],
            AgentConversationStateMachine::ACTION_REGISTER_AGENT => [
                ...$presetPayload,
                'type' => 'agente-corretaje',
                'classification' => 'agent',
            ],
            AgentConversationStateMachine::ACTION_REGISTER_SUBAGENT => [
                ...$presetPayload,
                'type' => 'agente-corretaje',
                'classification' => 'subagent',
            ],
            default => $presetPayload,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function mergePayloadFromMessage(?string $intent, array $payload, string $message, ?string $action = null): array
    {
        return match ($intent) {
            AgentConversationStateMachine::INTENT_PREREGISTRO => $this->mergePreregistrationPayload($payload, $message, $action),
            AgentConversationStateMachine::INTENT_COTIZACION => $this->mergeQuotePayload($payload, $message),
            default => $payload,
        };
    }

    public function isSimplifiedAgentRegistrationAction(?string $action): bool
    {
        return in_array($action, [
            AgentConversationStateMachine::ACTION_REGISTER_AGENT,
            AgentConversationStateMachine::ACTION_REGISTER_SUBAGENT,
        ], true);
    }

    public function isSimplifiedAgencyMasterRegistrationAction(?string $action): bool
    {
        return $action === AgentConversationStateMachine::ACTION_REGISTER_AGENCY_MASTER;
    }

    public function isSimplifiedAgencyGeneralRegistrationAction(?string $action): bool
    {
        return $action === AgentConversationStateMachine::ACTION_REGISTER_AGENCY_GENERAL;
    }

    public function isSimplifiedPreregistrationAction(?string $action): bool
    {
        return $this->isSimplifiedAgentRegistrationAction($action)
            || $this->isSimplifiedAgencyMasterRegistrationAction($action)
            || $this->isSimplifiedAgencyGeneralRegistrationAction($action);
    }

    public function registrationWelcomeMessage(?string $action): string
    {
        $headline = $this->registrationWelcomeHeadline($action);

        return match ($action) {
            AgentConversationStateMachine::ACTION_REGISTER_AGENCY_MASTER => $this->agencyMasterWelcomeMessage($headline),
            AgentConversationStateMachine::ACTION_REGISTER_AGENCY_GENERAL => $this->agencyGeneralWelcomeMessage($headline),
            default => $this->agentSubagentWelcomeMessage($headline),
        };
    }

    public function registrationWelcomeHeadline(?string $action): string
    {
        return match ($action) {
            AgentConversationStateMachine::ACTION_REGISTER_AGENCY_MASTER => '¡Te damos la Bienvenida al registro interactivo de tu Agencia Master! 👋',
            AgentConversationStateMachine::ACTION_REGISTER_AGENCY_GENERAL => '¡Te damos la Bienvenida al registro interactivo de tu Agencia General! 👋',
            AgentConversationStateMachine::ACTION_REGISTER_AGENT,
            AgentConversationStateMachine::ACTION_REGISTER_SUBAGENT => '¡Te damos la Bienvenida al registro interactivo de tu Agente o SubAgente! 👋',
            AgentConversationStateMachine::ACTION_QUOTE_INDIVIDUAL => '¡Te damos la Bienvenida a la cotización interactiva de tu Plan Individual! 👋',
            AgentConversationStateMachine::ACTION_QUOTE_CORPORATE => '¡Te damos la Bienvenida a la cotización interactiva de tu Plan Corporativo! 👋',
            AgentConversationStateMachine::ACTION_NUESTROS_PLANES => '¡Te damos la Bienvenida a **Nuestros Planes**! 👋',
            default => '¡Te damos la Bienvenida a Tu Dr. Group! 👋',
        };
    }

    public function isDefaultActionSelectionMessage(string $message): bool
    {
        return preg_match('/^Seleccioné:/u', trim($message)) === 1;
    }

    public function agentSubagentWelcomeMessage(string $headline): string
    {
        return $headline.<<<'TEXT'


Para completar tu preinscripción en el sistema INTEGRACORP 🤝 por favor sigue las indicaciones 📋 ¡Adelante!

Ingresa tu información en una sola línea, separando cada dato con una coma (,):

1) Nombre y apellido
2) Nro. cédula de identidad o RIF (ejemplo: v-16007868, e-12321345, j-23456789)
3) Fecha de nacimiento en formato dd/mm/yyyy (ejemplo: 05/01/1984)
4) Número de teléfono
5) Correo electrónico
6) Tipo: escribe 1 si eres Agente, o 2 si eres Subagente
7) Razón social de la agencia (si perteneces a TuDrGroup, escribe TDG)

Ejemplo:
María Pérez, v-16007868, 05/01/1984, 04141234567, maria@correo.com, 1, TDG
TEXT;
    }

    public function agencyMasterWelcomeMessage(string $headline): string
    {
        return $headline.<<<'TEXT'


Para completar tu registro como Agencia Master en INTEGRACORP 🤝 por favor sigue las indicaciones 📋 ¡Adelante!

Ingresa tu información en una sola línea, separando cada dato con una coma (,):

1) Razón social de la agencia master
2) RIF o número de cédula del representante (ejemplo: j-123456789, v-12345678 o e-12345654)
3) Número de teléfono
4) Correo electrónico

Ejemplo:
Agencia Master Ejemplo, j-123456789, 04141234567, maria@correo.com
TEXT;
    }

    public function agencyGeneralWelcomeMessage(string $headline): string
    {
        return $headline.<<<'TEXT'


Para completar tu registro como Agencia General en INTEGRACORP 🤝 por favor sigue las indicaciones 📋 ¡Adelante!

Ingresa tu información en una sola línea, separando cada dato con una coma (,):

1) Razón social de la agencia general
2) Razón social de la agencia master (si no pertenece a ninguna, escribe TDG)
3) RIF o número de cédula del representante (ejemplo: j-123456789, v-12345678 o e-12345654)
4) Número de teléfono
5) Correo electrónico

Ejemplo:
Mi Agencia General, Agencia Master Ejemplo, v-12345678, 04141234567, maria@correo.com
TEXT;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function agentSubagentValidationSummary(array $payload): string
    {
        $classification = (string) ($payload['classification'] ?? '');
        $profileLabel = match ($classification) {
            'agent' => 'Agente (1)',
            'subagent' => 'Subagente (2)',
            default => (string) ($payload['type'] ?? 'N/D'),
        };

        $agency = (string) ($payload['selected_agency_label'] ?? $payload['agency_name'] ?? '');
        if ($agency === '' && isset($payload['initial_observ'])) {
            $agency = preg_replace('/^Agencia:\s*/u', '', (string) $payload['initial_observ']) ?? '';
        }

        $tudrgroupNote = '';
        if (app(PublicAgentRegistrationValidationService::class)->belongsToTudrgroupStructure($payload)) {
            $agency = 'TuDrGroup — TDG-100';
            $tudrgroupNote = <<<'TEXT'


Importante: pertenecerás a la estructura comercial de TuDrGroup.
TEXT;
        }

        return sprintf(
            <<<'TEXT'
Perfecto, revisa que tus datos sean correctos antes de completar tu registro:

• Nombre y apellido: %s
• Cédula o RIF: %s
• Fecha de nacimiento: %s
• Teléfono: %s
• Correo electrónico: %s
• Tipo de perfil: %s
• Razón social de la agencia: %s%s

Si todo está correcto, responde si.
Si necesitas corregir un dato, envía solo el valor correcto (cédula/RIF, fecha de nacimiento, correo, teléfono, tipo 1/2 o agencia) sin repetir toda la línea.
TEXT,
            (string) ($payload['name'] ?? 'N/D'),
            (string) ($payload['identity_document_display'] ?? $payload['identity_document'] ?? 'N/D'),
            (string) ($payload['birth_date_display'] ?? $payload['birth_date'] ?? 'N/D'),
            (string) ($payload['phone_1'] ?? 'N/D'),
            (string) ($payload['email'] ?? 'N/D'),
            $profileLabel,
            $agency !== '' ? $agency : 'N/D',
            $tudrgroupNote,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function agencyMasterValidationSummary(array $payload): string
    {
        return sprintf(
            <<<'TEXT'
Perfecto, revisa que tus datos sean correctos antes de completar tu registro de Agencia Master:

• Razón social: %s
• RIF o cédula del representante: %s
• Teléfono: %s
• Correo electrónico: %s

Si todo está correcto, responde si.
Si necesitas corregir un dato, envía solo el valor correcto (razón social, RIF/cédula, teléfono o correo) sin repetir toda la línea.
TEXT,
            (string) ($payload['agency_corporate_name'] ?? 'N/D'),
            (string) ($payload['tax_id_display'] ?? $payload['tax_id'] ?? 'N/D'),
            (string) ($payload['phone_1'] ?? 'N/D'),
            (string) ($payload['email'] ?? 'N/D'),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function agencyGeneralValidationSummary(array $payload): string
    {
        $masterAgency = (string) ($payload['selected_master_agency_label'] ?? $payload['master_agency_name'] ?? 'N/D');

        return sprintf(
            <<<'TEXT'
Perfecto, revisa que tus datos sean correctos antes de completar tu registro de Agencia General:

• Razón social de la agencia general: %s
• Agencia master: %s
• RIF o cédula del representante: %s
• Teléfono: %s
• Correo electrónico: %s

Si todo está correcto, responde si.
Si necesitas corregir un dato, envía solo el valor correcto (razón social, agencia master, RIF/cédula, teléfono o correo) sin repetir toda la línea.
TEXT,
            (string) ($payload['agency_corporate_name'] ?? 'N/D'),
            $masterAgency,
            (string) ($payload['tax_id_display'] ?? $payload['tax_id'] ?? 'N/D'),
            (string) ($payload['phone_1'] ?? 'N/D'),
            (string) ($payload['email'] ?? 'N/D'),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    public function missingRequiredFields(?string $intent, array $payload, ?string $action = null): array
    {
        $required = match ($intent) {
            AgentConversationStateMachine::INTENT_PREREGISTRO => match (true) {
                $this->isSimplifiedAgentRegistrationAction($action) => ['name', 'identity_document', 'birth_date', 'email', 'phone_1', 'agency_name'],
                $this->isSimplifiedAgencyMasterRegistrationAction($action) => ['agency_corporate_name', 'tax_id', 'phone_1', 'email'],
                $this->isSimplifiedAgencyGeneralRegistrationAction($action) => ['agency_corporate_name', 'master_agency_name', 'tax_id', 'phone_1', 'email'],
                default => ['name', 'email', 'phone_1', 'country_id', 'state_id', 'city_id', 'type'],
            },
            AgentConversationStateMachine::INTENT_COTIZACION => ['plan_id', 'members'],
            default => [],
        };

        if ($intent === AgentConversationStateMachine::INTENT_COTIZACION && (int) ($payload['plan_id'] ?? 0) !== 1) {
            $required[] = 'coverage_id';
        }

        $missing = [];
        foreach ($required as $field) {
            if ($field === 'members') {
                $members = $payload['members'] ?? null;
                if (! is_array($members) || $members === []) {
                    $missing[] = $field;
                }

                continue;
            }

            if (! isset($payload[$field]) || $payload[$field] === null || $payload[$field] === '') {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    public function isConfirmation(string $message): bool
    {
        $normalized = mb_strtolower(trim($message));

        if (in_array($normalized, ['s', 'si', 'sí'], true)) {
            return true;
        }

        foreach ([
            'confirmo',
            'si confirmo',
            'sí confirmo',
            'confirmar',
            'autorizo',
            'adelante',
            'ok confirmar',
        ] as $token) {
            if (str_contains($normalized, $token)) {
                return true;
            }
        }

        return false;
    }

    public function isRejection(string $message): bool
    {
        $normalized = mb_strtolower(trim($message));

        return in_array($normalized, ['n', 'no'], true);
    }

    public function isHelpRequest(string $message): bool
    {
        $normalized = mb_strtolower(trim($message));

        return in_array($normalized, ['ayuda', 'help', 'necesito ayuda'], true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function confirmationPrompt(string $intent, array $payload, ?string $action = null): string
    {
        if ($intent === AgentConversationStateMachine::INTENT_PREREGISTRO) {
            if ($this->isSimplifiedAgentRegistrationAction($action)) {
                return $this->agentSubagentValidationSummary($payload);
            }

            if ($this->isSimplifiedAgencyMasterRegistrationAction($action)) {
                return $this->agencyMasterValidationSummary($payload);
            }

            if ($this->isSimplifiedAgencyGeneralRegistrationAction($action)) {
                return $this->agencyGeneralValidationSummary($payload);
            }

            $agency = (string) ($payload['agency_name'] ?? $payload['initial_observ'] ?? 'N/D');

            return sprintf(
                'Voy a crear el preregistro con estos datos: nombre %s, email %s, teléfono %s, tipo %s, agencia %s. Responde si para crearlo o indícame qué deseas ajustar.',
                (string) ($payload['name'] ?? 'N/D'),
                (string) ($payload['email'] ?? 'N/D'),
                (string) ($payload['phone_1'] ?? 'N/D'),
                (string) ($payload['classification'] ?? $payload['type'] ?? 'N/D'),
                $agency,
            );
        }

        $members = collect((array) ($payload['members'] ?? []))
            ->map(fn (array $member): string => sprintf('%s persona(s) de %s años', (int) ($member['quantity'] ?? 1), (int) ($member['age'] ?? 0)))
            ->implode(', ');

        return sprintf(
            'Voy a simular la cotización con plan %s, cobertura %s y miembros: %s. Responde si para calcularla o indícame ajustes.',
            (string) ($payload['plan_id'] ?? 'N/D'),
            (string) ($payload['coverage_id'] ?? 'N/D'),
            $members !== '' ? $members : 'N/D',
        );
    }

    public function questionForField(string $intent, string $field, ?string $action = null): string
    {
        return match ($intent) {
            AgentConversationStateMachine::INTENT_PREREGISTRO => match ($field) {
                'name' => '¿Cuál es tu nombre y apellido?',
                'identity_document' => 'Indica tu número de cédula o RIF con prefijo (ejemplo: v-16007868, e-12321345, j-23456789).',
                'birth_date' => 'Indica tu fecha de nacimiento en formato dd/mm/yyyy (ejemplo: 05/01/1984).',
                'email' => '¿Cuál es tu correo electrónico?',
                'phone_1' => '¿Cuál es tu teléfono principal (solo números)?',
                'agency_name' => 'Indícame la razón social de la agencia. Si perteneces a TuDrGroup, escribe TDG.',
                'agency_corporate_name' => 'Indícame la razón social de la agencia.',
                'tax_id' => 'Indícame el RIF o número de cédula del representante (ejemplo: j-123456789, v-12345678 o e-12345654).',
                'master_agency_name' => 'Indícame el nombre de la agencia master. Si no pertenece a ninguna, escribe TDG.',
                'country_id' => 'Indícame tu país (ejemplo: Venezuela).',
                'state_id' => '¿En qué estado te encuentras?',
                'city_id' => '¿Cuál es tu ciudad?',
                'type' => '¿Qué tipo de perfil eres? (agente-corretaje, agencia-corretaje, agencia-viajes, mayorista-viajes, freelance, asesor-exclusivo, cliente-individual, cliente-corporativo, ejecutivo, otro)',
                default => '¿Puedes compartir ese dato para continuar?',
            },
            AgentConversationStateMachine::INTENT_COTIZACION => match ($field) {
                'plan_id' => $action === AgentConversationStateMachine::ACTION_QUOTE_CORPORATE
                    ? '¿Qué plan corporativo deseas cotizar? (Inicial, Ideal o Especial)'
                    : '¿Qué plan deseas cotizar? (Inicial, Ideal o Especial)',
                'coverage_id' => '¿Cuál cobertura deseas usar? Puedes indicar el ID numérico de cobertura.',
                'members' => $action === AgentConversationStateMachine::ACTION_QUOTE_CORPORATE
                    ? 'Indícame la distribución por edades del grupo corporativo. Ejemplo: "10 personas de 35 años y 5 de 40".'
                    : 'Indícame las edades a cotizar. Ejemplo: "35 años, 33 años y 8 años".',
                default => 'Necesito un dato adicional para calcular la cotización.',
            },
            default => '¿Puedes darme más detalles para ayudarte?',
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function mergePreregistrationPayload(array $payload, string $message, ?string $action = null): array
    {
        if ($this->isSimplifiedAgencyMasterRegistrationAction($action)) {
            return $this->mergeSimplifiedAgencyMasterRegistrationPayload($payload, $message);
        }

        if ($this->isSimplifiedAgencyGeneralRegistrationAction($action)) {
            return $this->mergeSimplifiedAgencyGeneralRegistrationPayload($payload, $message);
        }

        if ($this->isSimplifiedAgentRegistrationAction($action)) {
            return $this->mergeSimplifiedAgentRegistrationPayload($payload, $message, $action);
        }

        $merged = $payload;
        $normalized = mb_strtolower($message);

        $merged['status'] ??= 'captación';
        $merged['reference_by'] ??= 'whatsapp-comercial';

        if (preg_match('/([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})/i', $message, $match) === 1) {
            $merged['email'] = mb_strtolower($match[1]);
        }

        if (preg_match('/(\+?\d[\d\-\s]{8,}\d)/', $message, $match) === 1) {
            $digits = preg_replace('/\D+/', '', $match[1]) ?? '';
            if ($digits !== '') {
                $merged['phone_1'] = $digits;
            }
        }

        if (! isset($merged['name'])) {
            if (preg_match('/(?:me llamo|soy|nombre(?: es)?)[\s:]+([A-Za-zÁÉÍÓÚÑáéíóúñ.\- ]{4,})/u', $message, $match) === 1) {
                $merged['name'] = trim($match[1]);
            }
        }

        if (str_contains($normalized, 'subagente') || str_contains($normalized, 'sub agente')) {
            $merged['type'] = 'agente-corretaje';
        } elseif (str_contains($normalized, 'agencia')) {
            $merged['type'] = 'agencia-corretaje';
        } elseif (str_contains($normalized, 'agente')) {
            $merged['type'] = 'agente-corretaje';
        } elseif (str_contains($normalized, 'freelance')) {
            $merged['type'] = 'freelance';
        }

        $countryName = $this->extractLabeledValue($message, 'pais');
        if ($countryName !== null) {
            $countryId = Country::query()
                ->get(['id', 'name'])
                ->first(fn (Country $country): bool => mb_strtolower((string) $country->name) === mb_strtolower($countryName))
                ?->id;
            if ($countryId !== null) {
                $merged['country_id'] = (int) $countryId;
            }
        }

        $stateName = $this->extractLabeledValue($message, 'estado');
        if ($stateName !== null) {
            $stateId = State::query()
                ->get(['id', 'definition'])
                ->first(fn (State $state): bool => mb_strtolower((string) $state->definition) === mb_strtolower($stateName))
                ?->id;
            if ($stateId !== null) {
                $merged['state_id'] = (int) $stateId;
            }
        }

        $cityName = $this->extractLabeledValue($message, 'ciudad');
        if ($cityName !== null) {
            $cityQuery = City::query()->get(['id', 'definition', 'state_id']);
            if (isset($merged['state_id'])) {
                $cityQuery = $cityQuery->where('state_id', (int) $merged['state_id']);
            }
            $cityId = $cityQuery
                ->first(fn (City $city): bool => mb_strtolower((string) $city->definition) === mb_strtolower($cityName))
                ?->id;
            if ($cityId !== null) {
                $merged['city_id'] = (int) $cityId;
            }
        }

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function mergeSimplifiedAgentRegistrationPayload(array $payload, string $message, ?string $action): array
    {
        $merged = $payload;
        $merged['status'] ??= 'captación';
        $merged['reference_by'] ??= 'whatsapp-comercial';
        $merged['type'] ??= 'agente-corretaje';

        $parts = array_map(
            static fn (string $part): string => trim($part),
            explode(',', $message),
        );

        if (count($parts) >= 7) {
            $merged['name'] = $parts[0];
            $merged = $this->applyIdentityDocumentToPayload($merged, $parts[1]);
            $merged = $this->applyBirthDateToPayload($merged, $parts[2]);

            $phoneDigits = preg_replace('/\D+/', '', $parts[3]) ?? '';
            if ($phoneDigits !== '') {
                $merged['phone_1'] = $phoneDigits;
            }

            if (preg_match('/([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})/i', $parts[4], $emailMatch) === 1) {
                $merged['email'] = mb_strtolower($emailMatch[1]);
            }

            $roleIndicator = preg_replace('/\D+/', '', $parts[5]) ?? '';
            if ($roleIndicator === '1') {
                $merged['classification'] = 'agent';
            } elseif ($roleIndicator === '2') {
                $merged['classification'] = 'subagent';
            }

            $agencyName = trim($parts[6]);
            if ($agencyName !== '') {
                $merged = $this->applyAgencySearchTerm($merged, $agencyName);
            }
        } elseif (! str_contains($message, ',')) {
            $merged = $this->mergePartialSimplifiedAgentCorrection($merged, $message);
        }

        return $this->applyDefaultVenezuelaLocation($merged);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function mergeSimplifiedAgencyMasterRegistrationPayload(array $payload, string $message): array
    {
        $merged = $payload;
        $merged['status'] ??= 'captación';
        $merged['reference_by'] ??= 'whatsapp-comercial';
        $merged['type'] ??= 'agencia-corretaje';
        $merged['classification'] ??= 'agency-master';

        $parts = array_map(
            static fn (string $part): string => trim($part),
            explode(',', $message),
        );

        if (count($parts) >= 4) {
            $corporateName = trim($parts[0]);
            if ($corporateName !== '') {
                $merged = $this->applyCorporateAgencyName($merged, $corporateName);
            }

            $taxId = trim($parts[1]);
            if ($taxId !== '') {
                $merged = $this->applyTaxIdToPayload($merged, $taxId);
            }

            $phoneDigits = preg_replace('/\D+/', '', $parts[2]) ?? '';
            if ($phoneDigits !== '') {
                $merged['phone_1'] = $phoneDigits;
            }

            if (preg_match('/([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})/i', $parts[3], $emailMatch) === 1) {
                $merged['email'] = mb_strtolower($emailMatch[1]);
            }
        } elseif (count($parts) >= 2) {
            $merged = $this->applyDetectedAgencyMasterParts($merged, $parts);
        } elseif (! str_contains($message, ',')) {
            $merged = $this->mergePartialAgencyMasterCorrection($merged, $message);
        }

        return $this->applyDefaultVenezuelaLocation($merged);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function mergeSimplifiedAgencyGeneralRegistrationPayload(array $payload, string $message): array
    {
        $merged = $payload;
        $merged['status'] ??= 'captación';
        $merged['reference_by'] ??= 'whatsapp-comercial';
        $merged['type'] ??= 'agencia-corretaje';
        $merged['classification'] ??= 'agency-general';

        $parts = array_map(
            static fn (string $part): string => trim($part),
            explode(',', $message),
        );

        if (count($parts) >= 5) {
            $corporateName = trim($parts[0]);
            if ($corporateName !== '') {
                $merged = $this->applyCorporateAgencyName($merged, $corporateName);
            }

            $masterAgencyName = trim($parts[1]);
            if ($masterAgencyName !== '') {
                $merged = $this->applyMasterAgencySearchTerm($merged, $masterAgencyName);
            }

            $taxId = trim($parts[2]);
            if ($taxId !== '') {
                $merged = $this->applyTaxIdToPayload($merged, $taxId);
            }

            $phoneDigits = preg_replace('/\D+/', '', $parts[3]) ?? '';
            if ($phoneDigits !== '') {
                $merged['phone_1'] = $phoneDigits;
            }

            if (preg_match('/([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})/i', $parts[4], $emailMatch) === 1) {
                $merged['email'] = mb_strtolower($emailMatch[1]);
            }
        } elseif (count($parts) >= 2) {
            $merged = $this->applyDetectedAgencyGeneralParts($merged, $parts);
        } elseif (! str_contains($message, ',')) {
            $merged = $this->mergePartialAgencyGeneralCorrection($merged, $message);
        }

        return $this->applyDefaultVenezuelaLocation($merged);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function hasPartialSimplifiedAgencyMasterRegistrationData(array $payload): bool
    {
        return trim((string) ($payload['agency_corporate_name'] ?? '')) !== ''
            || trim((string) ($payload['tax_id'] ?? '')) !== ''
            || trim((string) ($payload['email'] ?? '')) !== ''
            || trim((string) ($payload['phone_1'] ?? '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function hasPartialSimplifiedAgencyGeneralRegistrationData(array $payload): bool
    {
        return trim((string) ($payload['agency_corporate_name'] ?? '')) !== ''
            || trim((string) ($payload['master_agency_name'] ?? '')) !== ''
            || trim((string) ($payload['tax_id'] ?? '')) !== ''
            || trim((string) ($payload['email'] ?? '')) !== ''
            || trim((string) ($payload['phone_1'] ?? '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function mergePartialAgencyMasterCorrection(array $payload, string $message): array
    {
        $trimmed = trim($message);

        if ($trimmed === '' || $this->isDefaultActionSelectionMessage($trimmed)) {
            return $payload;
        }

        if ($this->isStandaloneEmailMessage($trimmed)) {
            $payload['email'] = mb_strtolower($trimmed);

            return $payload;
        }

        if ($this->isStandalonePhoneMessage($trimmed)) {
            $payload['phone_1'] = preg_replace('/\D+/', '', $trimmed) ?? '';

            return $payload;
        }

        if ($this->isStandaloneTaxIdMessage($trimmed)) {
            return $this->applyTaxIdToPayload($payload, $trimmed);
        }

        if (trim((string) ($payload['agency_corporate_name'] ?? '')) === '' && $this->isCorporateNameCandidate($trimmed)) {
            return $this->applyCorporateAgencyName($payload, $trimmed);
        }

        if ($this->hasPartialSimplifiedAgencyMasterRegistrationData($payload) && $this->isAgencySearchRetryMessage($trimmed)) {
            return $this->applyCorporateAgencyName($payload, $trimmed);
        }

        return $payload;
    }

    private function isStandaloneTaxIdMessage(string $message): bool
    {
        $trimmed = trim($message);

        if ($trimmed === '') {
            return false;
        }

        if (preg_match('/^[JVEGPRC]\-?\d{5,12}$/iu', $trimmed) === 1) {
            return true;
        }

        if (ChatAgencyRepresentativeDocument::parse($trimmed) !== null) {
            return true;
        }

        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        return strlen($digits) >= 6 && strlen($digits) <= 10 && ! str_contains($trimmed, '@');
    }

    /**
     * @param  list<string>  $parts
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function applyDetectedAgencyMasterParts(array $payload, array $parts): array
    {
        $corporateName = null;

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            if ($this->isStandaloneEmailMessage($part)) {
                $payload['email'] = mb_strtolower($part);

                continue;
            }

            if ($this->isStandalonePhoneMessage($part)) {
                $payload['phone_1'] = preg_replace('/\D+/', '', $part) ?? '';

                continue;
            }

            if ($this->isStandaloneTaxIdMessage($part)) {
                $payload = $this->applyTaxIdToPayload($payload, $part);

                continue;
            }

            if ($corporateName === null) {
                $corporateName = $part;
            }
        }

        if ($corporateName !== null && trim((string) ($payload['agency_corporate_name'] ?? '')) === '') {
            $payload = $this->applyCorporateAgencyName($payload, $corporateName);
        }

        return $payload;
    }

    /**
     * @param  list<string>  $parts
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function applyDetectedAgencyGeneralParts(array $payload, array $parts): array
    {
        $corporateName = null;
        $masterAgencyName = null;

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            if ($this->isStandaloneEmailMessage($part)) {
                $payload['email'] = mb_strtolower($part);

                continue;
            }

            if ($this->isStandalonePhoneMessage($part)) {
                $payload['phone_1'] = preg_replace('/\D+/', '', $part) ?? '';

                continue;
            }

            if ($this->isStandaloneTaxIdMessage($part)) {
                $payload = $this->applyTaxIdToPayload($payload, $part);

                continue;
            }

            if ($corporateName === null) {
                $corporateName = $part;

                continue;
            }

            if ($masterAgencyName === null) {
                $masterAgencyName = $part;
            }
        }

        if ($corporateName !== null && trim((string) ($payload['agency_corporate_name'] ?? '')) === '') {
            $payload = $this->applyCorporateAgencyName($payload, $corporateName);
        }

        if ($masterAgencyName !== null && trim((string) ($payload['master_agency_name'] ?? '')) === '') {
            $payload = $this->applyMasterAgencySearchTerm($payload, $masterAgencyName);
        }

        return $payload;
    }

    private function isCorporateNameCandidate(string $message): bool
    {
        if ($this->isStandaloneNameMessage($message)) {
            return true;
        }

        $trimmed = trim($message);

        if ($trimmed === '' || mb_strlen($trimmed) < 3) {
            return false;
        }

        if ($this->isConfirmation($trimmed) || $this->isRejection($trimmed)) {
            return false;
        }

        if ($this->isStandaloneEmailMessage($trimmed) || $this->isStandalonePhoneMessage($trimmed) || $this->isStandaloneTaxIdMessage($trimmed)) {
            return false;
        }

        return preg_match('/^[\p{L}][\p{L}\p{N}\s.\'-]*$/u', $trimmed) === 1;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function mergePartialAgencyGeneralCorrection(array $payload, string $message): array
    {
        if (! $this->hasPartialSimplifiedAgencyGeneralRegistrationData($payload)) {
            return $payload;
        }

        $trimmed = trim($message);

        if ($trimmed === '' || $this->isDefaultActionSelectionMessage($trimmed)) {
            return $payload;
        }

        if ($this->isStandaloneEmailMessage($trimmed)) {
            $payload['email'] = mb_strtolower($trimmed);

            return $payload;
        }

        if ($this->isStandalonePhoneMessage($trimmed)) {
            $payload['phone_1'] = preg_replace('/\D+/', '', $trimmed) ?? '';

            return $payload;
        }

        if ($this->isStandaloneTaxIdMessage($trimmed)) {
            return $this->applyTaxIdToPayload($payload, $trimmed);
        }

        if ($this->isMasterAgencyCorrectionMessage($payload, $trimmed)) {
            return $this->applyMasterAgencySearchTerm($payload, $trimmed);
        }

        if ($this->isAgencySearchRetryMessage($trimmed)) {
            if (trim((string) ($payload['agency_corporate_name'] ?? '')) === '') {
                return $this->applyCorporateAgencyName($payload, $trimmed);
            }

            return $this->applyMasterAgencySearchTerm($payload, $trimmed);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isMasterAgencyCorrectionMessage(array $payload, string $message): bool
    {
        $trimmed = trim($message);

        if ($trimmed === '' || str_contains($trimmed, ',')) {
            return false;
        }

        if ($this->isConfirmation($trimmed) || $this->isRejection($trimmed)) {
            return false;
        }

        if (
            $this->isStandaloneEmailMessage($trimmed)
            || $this->isStandalonePhoneMessage($trimmed)
            || $this->isStandaloneTaxIdMessage($trimmed)
        ) {
            return false;
        }

        if (trim((string) ($payload['agency_corporate_name'] ?? '')) === '') {
            return false;
        }

        if (mb_strtolower(trim((string) ($payload['agency_corporate_name'] ?? ''))) === mb_strtolower($trimmed)) {
            return false;
        }

        return mb_strlen($trimmed) >= 2;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function applyCorporateAgencyName(array $payload, string $term): array
    {
        $corporateName = trim($term);

        if ($corporateName === '') {
            return $payload;
        }

        $payload['agency_corporate_name'] = $corporateName;

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function applyMasterAgencySearchTerm(array $payload, string $term): array
    {
        $masterAgencyName = trim($term);

        if ($masterAgencyName === '') {
            return $payload;
        }

        $payload['master_agency_name'] = $masterAgencyName;
        unset($payload['selected_master_agency_id'], $payload['selected_master_agency_label'], $payload['owner_code']);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function buildAgencyProspectInitialObserv(array $payload, ?string $action): string
    {
        $corporateName = trim((string) ($payload['agency_corporate_name'] ?? 'N/D'));

        if ($this->isSimplifiedAgencyMasterRegistrationAction($action)) {
            return sprintf('Agencia Master: %s', $corporateName);
        }

        $masterLabel = trim((string) ($payload['selected_master_agency_label'] ?? $payload['master_agency_name'] ?? 'TDG-100'));

        return sprintf('Agencia General: %s | Agencia Master: %s', $corporateName, $masterLabel);
    }

    public function agencyProspectRegistrationSuccessMessage(int $prospectAgentId, string $status): string
    {
        return sprintf(
            "Listo, tu preregistro de agencia fue creado exitosamente con ID %s y estatus %s.\n\n%s",
            $prospectAgentId,
            $status,
            $this->chatAgentAnotherActionOfferMessage(),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function hasPartialSimplifiedAgentRegistrationData(array $payload): bool
    {
        return trim((string) ($payload['name'] ?? '')) !== ''
            || trim((string) ($payload['identity_document'] ?? '')) !== ''
            || trim((string) ($payload['birth_date'] ?? '')) !== ''
            || trim((string) ($payload['email'] ?? '')) !== ''
            || trim((string) ($payload['phone_1'] ?? '')) !== ''
            || trim((string) ($payload['classification'] ?? '')) !== ''
            || trim((string) ($payload['agency_name'] ?? '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function mergePartialSimplifiedAgentCorrection(array $payload, string $message): array
    {
        if (! $this->hasPartialSimplifiedAgentRegistrationData($payload)) {
            return $payload;
        }

        $trimmed = trim($message);

        if ($trimmed === '' || $this->isDefaultActionSelectionMessage($trimmed)) {
            return $payload;
        }

        if ($this->isStandaloneIdentityDocumentMessage($trimmed)) {
            return $this->applyIdentityDocumentToPayload($payload, $trimmed);
        }

        if ($this->isStandaloneBirthDateMessage($trimmed)) {
            return $this->applyBirthDateToPayload($payload, $trimmed);
        }

        if ($this->isStandaloneEmailMessage($trimmed)) {
            $payload['email'] = mb_strtolower($trimmed);

            return $payload;
        }

        if ($this->isStandalonePhoneMessage($trimmed)) {
            $payload['phone_1'] = preg_replace('/\D+/', '', $trimmed) ?? '';

            return $payload;
        }

        if ($this->isStandaloneClassificationMessage($trimmed)) {
            return $this->applyClassificationFromMessage($payload, $trimmed);
        }

        if ($this->isAgencySearchRetryMessage($trimmed)) {
            return $this->applyAgencySearchTerm($payload, $trimmed);
        }

        if ($this->isStandaloneNameMessage($trimmed)) {
            $payload['name'] = $trimmed;

            return $payload;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function applyTaxIdToPayload(array $payload, string $raw): array
    {
        $parsed = ChatAgencyRepresentativeDocument::parse($raw);

        if ($parsed === null) {
            $payload['tax_id'] = trim($raw);

            return $payload;
        }

        $payload['tax_id'] = $parsed['display'];
        $payload['tax_id_display'] = $parsed['display'];

        if ($parsed['kind'] === ChatAgentIdentityDocument::KIND_RIF) {
            $payload['rif'] = $parsed['number'];
            unset($payload['ci_responsable']);
        } else {
            $payload['ci_responsable'] = $parsed['number'];
            unset($payload['rif']);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function applyIdentityDocumentToPayload(array $payload, string $raw): array
    {
        $parsed = ChatAgentIdentityDocument::parse($raw);

        if ($parsed === null) {
            $payload['identity_document'] = trim($raw);

            return $payload;
        }

        $payload['identity_document'] = $parsed['display'];
        $payload['identity_document_display'] = $parsed['display'];

        if ($parsed['kind'] === ChatAgentIdentityDocument::KIND_RIF) {
            $payload['rif'] = $parsed['number'];
            unset($payload['ci']);
        } else {
            $payload['ci'] = $parsed['number'];
            unset($payload['rif']);
        }

        return $payload;
    }

    private function isStandaloneIdentityDocumentMessage(string $message): bool
    {
        return ChatAgentIdentityDocument::parse($message) !== null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function applyBirthDateToPayload(array $payload, string $raw): array
    {
        $parsed = $this->parseBirthDate($raw);

        if ($parsed === null) {
            $payload['birth_date_input'] = trim($raw);

            return $payload;
        }

        $payload['birth_date'] = $parsed['storage'];
        $payload['birth_date_display'] = $parsed['display'];
        unset($payload['birth_date_input']);

        return $payload;
    }

    /**
     * @return array{storage: string, display: string}|null
     */
    public function parseBirthDate(string $raw): ?array
    {
        $trimmed = trim($raw);

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $trimmed, $match) !== 1) {
            return null;
        }

        $day = (int) $match[1];
        $month = (int) $match[2];
        $year = (int) $match[3];

        if (! checkdate($month, $day, $year)) {
            return null;
        }

        if ($year < 1900 || $year > (int) date('Y')) {
            return null;
        }

        return [
            'storage' => sprintf('%04d-%02d-%02d', $year, $month, $day),
            'display' => sprintf('%02d/%02d/%04d', $day, $month, $year),
        ];
    }

    private function isStandaloneBirthDateMessage(string $message): bool
    {
        return $this->parseBirthDate($message) !== null;
    }

    private function isStandaloneEmailMessage(string $message): bool
    {
        return filter_var($message, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function isStandalonePhoneMessage(string $message): bool
    {
        $digits = preg_replace('/\D+/', '', $message) ?? '';

        if ($digits === '') {
            return false;
        }

        $length = strlen($digits);

        if ($length < 10 || $length > 13) {
            return false;
        }

        return preg_replace('/[\d\s+\-().]/', '', $message) === '';
    }

    private function isStandaloneClassificationMessage(string $message): bool
    {
        $normalized = mb_strtolower(trim($message));

        return in_array($normalized, ['1', '2', 'agente', 'subagente'], true);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function applyClassificationFromMessage(array $payload, string $message): array
    {
        $normalized = mb_strtolower(trim($message));

        if ($normalized === '1' || $normalized === 'agente') {
            $payload['classification'] = 'agent';
        } elseif ($normalized === '2' || $normalized === 'subagente') {
            $payload['classification'] = 'subagent';
        }

        return $payload;
    }

    private function isStandaloneNameMessage(string $message): bool
    {
        if ($this->isConfirmation($message) || $this->isRejection($message)) {
            return false;
        }

        if ($this->isStandaloneClassificationMessage($message) || $this->isStandalonePhoneMessage($message)) {
            return false;
        }

        if ($this->isStandaloneIdentityDocumentMessage($message)) {
            return false;
        }

        if (str_contains($message, '@')) {
            return false;
        }

        if (mb_strlen($message) < 3) {
            return false;
        }

        if (! str_contains($message, ' ') && preg_match('/^[A-Z0-9][A-Z0-9\-_.]{0,18}$/', $message) === 1) {
            return false;
        }

        return preg_match('/^[\p{L}][\p{L}\s.\'-]*$/u', $message) === 1;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function hasSimplifiedAgentCoreFields(array $payload): bool
    {
        return trim((string) ($payload['name'] ?? '')) !== ''
            && trim((string) ($payload['email'] ?? '')) !== ''
            && trim((string) ($payload['phone_1'] ?? '')) !== ''
            && trim((string) ($payload['classification'] ?? '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function applyAgencySearchTerm(array $payload, string $term): array
    {
        $agencyName = trim($term);

        if ($agencyName === '') {
            return $payload;
        }

        $payload['agency_name'] = $agencyName;
        $payload['initial_observ'] = 'Agencia: '.$agencyName;
        unset($payload['selected_agency_id'], $payload['selected_agency_label']);

        return $payload;
    }

    private function isAgencySearchRetryMessage(string $message): bool
    {
        $trimmed = trim($message);

        if ($trimmed === '' || $this->isDefaultActionSelectionMessage($trimmed)) {
            return false;
        }

        if ($this->isConfirmation($trimmed) || $this->isRejection($trimmed)) {
            return false;
        }

        if (str_contains($trimmed, ',')) {
            return false;
        }

        if (preg_match('/^\d+$/', $trimmed) === 1) {
            return false;
        }

        if ($this->isStandaloneNameMessage($trimmed)) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function applyDefaultVenezuelaLocation(array $payload): array
    {
        if (! isset($payload['country_id'])) {
            $countryId = Country::query()
                ->whereRaw('LOWER(name) LIKE ?', ['%venezuela%'])
                ->value('id');

            if ($countryId !== null) {
                $payload['country_id'] = (int) $countryId;
            }
        }

        if (! isset($payload['state_id']) && isset($payload['country_id'])) {
            $stateId = State::query()
                ->where('country_id', (int) $payload['country_id'])
                ->orderBy('id')
                ->value('id');

            if ($stateId !== null) {
                $payload['state_id'] = (int) $stateId;
            }
        }

        if (! isset($payload['city_id']) && isset($payload['state_id'])) {
            $cityId = City::query()
                ->where('state_id', (int) $payload['state_id'])
                ->orderBy('id')
                ->value('id');

            if ($cityId !== null) {
                $payload['city_id'] = (int) $cityId;
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function mergeQuotePayload(array $payload, string $message): array
    {
        $merged = $payload;
        $normalized = mb_strtolower($message);

        if (str_contains($normalized, 'inicial') || str_contains($normalized, 'plan 1')) {
            $merged['plan_id'] = 1;
        } elseif (str_contains($normalized, 'ideal') || str_contains($normalized, 'plan 2')) {
            $merged['plan_id'] = 2;
        } elseif (str_contains($normalized, 'especial') || str_contains($normalized, 'plan 3')) {
            $merged['plan_id'] = 3;
        }

        if (preg_match('/cobertura\s*#?\s*(\d+)/iu', $message, $coverageMatch) === 1) {
            $merged['coverage_id'] = (int) $coverageMatch[1];
        }

        $members = [];
        foreach ((array) ($merged['members'] ?? []) as $member) {
            if (is_array($member)) {
                $members[] = [
                    'age' => (int) ($member['age'] ?? 0),
                    'quantity' => (int) ($member['quantity'] ?? 1),
                ];
            }
        }

        if (preg_match_all('/(\d{1,2})\s*x\s*(\d{1,3})/u', $message, $matches, PREG_SET_ORDER) > 0) {
            foreach ($matches as $match) {
                $members[] = ['age' => (int) $match[1], 'quantity' => (int) $match[2]];
            }
        } elseif (preg_match_all('/\b(\d{1,2})\s*(?:años|anos|año|anios)\b/iu', $message, $matches) > 0) {
            foreach ($matches[1] as $age) {
                $members[] = ['age' => (int) $age, 'quantity' => 1];
            }
        }

        $members = collect($members)
            ->filter(fn (array $member): bool => $member['age'] > 0 && $member['age'] <= 120 && $member['quantity'] > 0)
            ->groupBy('age')
            ->map(fn ($group, $age): array => ['age' => (int) $age, 'quantity' => (int) collect($group)->sum('quantity')])
            ->values()
            ->all();

        if ($members !== []) {
            $merged['members'] = $members;
        }

        return $merged;
    }

    public function chatAgentRegistrationDeliveredMessage(string $email, string $phone): string
    {
        return sprintf(
            <<<'TEXT'
¡Excelente! Tu registro se realizó con éxito.

Te acabamos de enviar un mensaje por WhatsApp al %s y también al correo %s con la información de tu registro, credenciales de acceso y carta de bienvenida.

Por favor revisa tu WhatsApp y tu bandeja de entrada (incluida la carpeta de spam).

¿Recibiste la información por alguna de las dos vías? Responde si si ya la recibiste correctamente, o no si no la recibiste por ninguno de los dos canales.
TEXT,
            $phone,
            $email,
        );
    }

    public function chatAgentRegistrationDeliveryConfirmationReprompt(): string
    {
        return '¿Recibiste la información por WhatsApp o por correo electrónico? Responde si si ya la recibiste, o no si no la recibiste por ninguno de los dos canales.';
    }

    public function chatAgentRegistrationReceivedAnotherActionOfferMessage(): string
    {
        return '¡Perfecto! Nos alegra que hayas recibido la información correctamente.'."\n\n".$this->chatAgentAnotherActionOfferMessage();
    }

    public function agencyMasterRegistrationDeliveredMessage(string $email, string $phone): string
    {
        return sprintf(
            <<<'TEXT'
¡Excelente! Tu registro de Agencia Master se realizó con éxito.

Te acabamos de enviar un mensaje por WhatsApp al %s y también al correo %s con la información de tu registro, credenciales de acceso y carta de bienvenida.

Por favor revisa tu WhatsApp y tu bandeja de entrada (incluida la carpeta de spam).

¿Recibiste la información por alguna de las dos vías? Responde si si ya la recibiste correctamente, o no si no la recibiste por ninguno de los dos canales.
TEXT,
            $phone,
            $email,
        );
    }

    public function agencyGeneralRegistrationDeliveredMessage(string $email, string $phone): string
    {
        return sprintf(
            <<<'TEXT'
¡Excelente! Tu registro de Agencia General se realizó con éxito.

Te acabamos de enviar un mensaje por WhatsApp al %s y también al correo %s con la información de tu registro, credenciales de acceso y carta de bienvenida.

Por favor revisa tu WhatsApp y tu bandeja de entrada (incluida la carpeta de spam).

¿Recibiste la información por alguna de las dos vías? Responde si si ya la recibiste correctamente, o no si no la recibiste por ninguno de los dos canales.
TEXT,
            $phone,
            $email,
        );
    }

    public function agencyMasterRegistrationNotReceivedReprompt(): string
    {
        return '¿Recibiste la información por WhatsApp o por correo electrónico? Responde si si ya la recibiste, o no si no la recibiste por ninguno de los dos canales.';
    }

    public function agencyMasterRegistrationReceivedAnotherActionOfferMessage(): string
    {
        return '¡Perfecto! Nos alegra que hayas recibido la información correctamente.'."\n\n".$this->chatAgentAnotherActionOfferMessage();
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function agencyMasterRegistrationCredentialsMessage(array $credentials, ?string $welcomeLetterUrl = null): string
    {
        $loginUrl = (string) ($credentials['login_url'] ?? config('services.chat_agency_master_registration.portal_login_url'));

        $message = sprintf(
            <<<'TEXT'
Aquí tienes tu información de acceso. Guárdala en un lugar seguro:

• Usuario (correo): %s
• Contraseña: %s
• Código de agencia: %s
• Portal Agencia Master: %s

Puedes hacer clic en el enlace del portal para ir al inicio de sesión.
TEXT,
            (string) ($credentials['email'] ?? 'N/D'),
            (string) ($credentials['password'] ?? 'N/D'),
            (string) ($credentials['code_agency'] ?? 'N/D'),
            $loginUrl,
        );

        if (is_string($welcomeLetterUrl) && $welcomeLetterUrl !== '') {
            $message .= sprintf(
                "\n\nDescarga tu carta de bienvenida: [Carta de bienvenida](%s)",
                $welcomeLetterUrl,
            );
        }

        return $message;
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function agencyGeneralRegistrationCredentialsMessage(array $credentials, ?string $welcomeLetterUrl = null): string
    {
        $loginUrl = (string) ($credentials['login_url'] ?? config('services.chat_agency_general_registration.portal_login_url'));

        $message = sprintf(
            <<<'TEXT'
Aquí tienes tu información de acceso. Guárdala en un lugar seguro:

• Usuario (correo): %s
• Contraseña: %s
• Código de agencia: %s
• Agencia master: %s
• Portal Agencia General: %s

Puedes hacer clic en el enlace del portal para ir al inicio de sesión.
TEXT,
            (string) ($credentials['email'] ?? 'N/D'),
            (string) ($credentials['password'] ?? 'N/D'),
            (string) ($credentials['code_agency'] ?? 'N/D'),
            (string) ($credentials['owner_code'] ?? 'N/D'),
            $loginUrl,
        );

        if (is_string($welcomeLetterUrl) && $welcomeLetterUrl !== '') {
            $message .= sprintf(
                "\n\nDescarga tu carta de bienvenida: [Carta de bienvenida](%s)",
                $welcomeLetterUrl,
            );
        }

        return $message;
    }

    public function agencyMasterRegistrationBusinessTeamMessage(string $whatsappUrl, string $whatsappLabel): string
    {
        return sprintf(
            <<<'TEXT'
Con gusto te ayudamos. Puedes comunicarte con nuestro equipo de negocios por estos medios:

%s

%s
TEXT,
            $this->businessAdvisorsContactBlock($whatsappUrl, $whatsappLabel),
            $this->chatAgentAnotherActionOfferMessage(),
        );
    }

    public function chatAgentRegistrationCredentialsViaWhatsAppSentMessage(string $phone): string
    {
        return sprintf(
            'Perfecto. Te enviamos por WhatsApp al número %s la carta de bienvenida y tus datos de acceso (usuario, contraseña y enlace al portal de agentes). Revisa tu WhatsApp en unos momentos.'."\n\n".$this->chatAgentAnotherActionOfferMessage(),
            $phone,
        );
    }

    public function chatAgentAnotherActionOfferMessage(): string
    {
        return <<<'TEXT'
¿Deseas realizar alguna otra acción de la lista? Puedes consultar nuestros planes, registrar agencia, agente o subagente.
Responde si para elegir otra acción, o no para finalizar.
TEXT;
    }

    public function chatAgentAnotherActionOfferReprompt(): string
    {
        return 'Responde si deseas realizar otra acción del menú, o no para finalizar.';
    }

    public function chatAgentFarewellMessage(string $whatsappUrl, string $whatsappLabel): string
    {
        return sprintf(
            <<<'TEXT'
¡Gracias por usar Integracorp! Puedes volver cuando gustes; estaremos aquí para ayudarte.

%s

¡Hasta pronto!
TEXT,
            $this->businessAdvisorsContactBlock($whatsappUrl, $whatsappLabel),
        );
    }

    public function businessAdvisorsContactBlock(string $whatsappUrl, string $whatsappLabel): string
    {
        return sprintf(
            <<<'TEXT'
Si necesitas hablar con nuestros asesores de negocios de forma directa:
• WhatsApp: [%2$s](%1$s)
• Teléfono: %3$s
TEXT,
            $whatsappUrl,
            $whatsappLabel,
            $this->businessAdvisorsPhoneDisplay($whatsappLabel),
        );
    }

    public function businessAdvisorsPhoneDisplay(string $whatsappLabel): string
    {
        $digits = preg_replace('/\D+/', '', $whatsappLabel) ?? '';

        if (strlen($digits) === 10) {
            return sprintf(
                '%s %s %s',
                substr($digits, 0, 4),
                substr($digits, 4, 3),
                substr($digits, 7, 4),
            );
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '58')) {
            return sprintf(
                '0%s %s %s',
                substr($digits, 2, 3),
                substr($digits, 5, 3),
                substr($digits, 8, 4),
            );
        }

        return '0412 701 8390';
    }

    public function chatAgentRegistrationChatCredentialsOfferMessage(): string
    {
        return <<<'TEXT'
Lamentamos que no hayas recibido la información. ¿Quieres que te compartamos aquí en el chat tus datos de acceso y una vista previa descargable de tu carta de bienvenida?
Responde si para recibirla en este chat, o no para continuar.
TEXT;
    }

    public function chatAgentRegistrationChatCredentialsDeclinedMessage(): string
    {
        return 'Entendido. Esperamos que puedas recibir la información pronto.'."\n\n".$this->chatAgentAnotherActionOfferMessage();
    }

    public function publicChatGuideWelcomeMessage(): string
    {
        return <<<'TEXT'
¡Qué gusto tenerte aquí!

Soy tu GUÍA-CHAT de Integracorp. Estoy para acompañarte paso a paso con lo que necesites gestionar hoy, de forma sencilla y sin complicaciones.

En el campo de mensaje, junto al botón **Quiero!**, encontrarás una lista con las acciones disponibles: conocer **Nuestros Planes**, registrar una agencia, un agente y más. Si estás desde un dispositivo móvil, pulsa **?** para seleccionar una acción. Elige la que necesites y yo te iré guiando en cada paso del camino.

Si en algún momento no entiendes una instrucción o prefieres hablar con una persona, escribe la palabra **ayuda** y te indico cómo contactar a nuestros Asesores Comerciales por WhatsApp.

Cuando estés listo, selecciona una acción y comenzamos.
TEXT;
    }

    public function publicChatHelpMessage(string $whatsappUrl, string $whatsappLabel): string
    {
        return sprintf(
            <<<'TEXT'
Claro, con gusto te oriento.

Si necesitas una mano extra o prefieres hablar con alguien de nuestro equipo, puedes escribirle a nuestros Asesores Comerciales por WhatsApp:

%s

Cuando quieras retomar el proceso aquí en el chat, elige una acción en **Quiero!** y seguimos juntos. Si estás desde un dispositivo móvil, pulsa **?** para seleccionar otra acción.
TEXT,
            $this->commercialAdvisorsContactBlock($whatsappUrl, $whatsappLabel),
        );
    }

    public function commercialAdvisorsContactBlock(string $whatsappUrl, string $whatsappLabel): string
    {
        return sprintf(
            <<<'TEXT'
• WhatsApp: [%2$s](%1$s)
• Teléfono: %3$s
TEXT,
            $whatsappUrl,
            $whatsappLabel,
            $this->businessAdvisorsPhoneDisplay($whatsappLabel),
        );
    }

    public function publicChatRestartWithActionsMessage(): string
    {
        // Acciones de cotización deshabilitadas temporalmente (reactivar junto con el menú cuando se requiera):
        // • Cotización plan individual
        // • Cotización plan corporativo
        return <<<'TEXT'
¡Perfecto! Reiniciamos la conversación. Elige una acción del menú «¿Qué quieres hacer?» para continuar:

• Registro Agencia Master
• Registro Agencia General
• Registro de Agente
• Registro de Subagente
TEXT;
    }

    public function chatAgentRegistrationCredentialsViaWhatsAppFailedMessage(): string
    {
        return <<<'TEXT'
No pudimos enviar la información por WhatsApp en este momento.

Revisa tu correo electrónico registrado; deberías haber recibido la carta de bienvenida y los datos de acceso. Si no te llegó, responde si y lo intentaremos enviar al teléfono registrado.
TEXT;
    }

    public function chatAgentRegistrationChatCredentialsOfferReprompt(): string
    {
        return 'Responde si para recibir la información en este chat, o no para continuar.';
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function chatAgentRegistrationCredentialsMessage(array $credentials, ?string $welcomeLetterUrl = null): string
    {
        $loginUrl = (string) ($credentials['login_url'] ?? config('services.chat_agent_registration.portal_login_url'));

        $message = sprintf(
            <<<'TEXT'
Aquí tienes tu información de acceso. Guárdala en un lugar seguro:

• Usuario (correo): %s
• Contraseña: %s
• Código de agente: %s
• Portal de agentes: %s

Puedes hacer clic en el enlace del portal para ir al inicio de sesión.
TEXT,
            (string) ($credentials['email'] ?? 'N/D'),
            (string) ($credentials['password'] ?? 'N/D'),
            (string) ($credentials['code_agent'] ?? 'N/D'),
            $loginUrl,
        );

        if (is_string($welcomeLetterUrl) && $welcomeLetterUrl !== '') {
            $message .= sprintf(
                "\n\nDescarga tu carta de bienvenida: [Carta de bienvenida](%s)",
                $welcomeLetterUrl,
            );
        }

        return $message;
    }

    public function chatAgentRegistrationFailurePrompt(): string
    {
        return <<<'TEXT'
No pudimos completar tu registro en este momento.

Si gustas, puedes comunicarte con nuestro equipo de negocios para recibir atención directa y personalizada y finalizar tu registro.

¿Quieres que te redirija al chat de WhatsApp con el equipo de negocios?
Responde si para continuar por WhatsApp.
TEXT;
    }

    public function chatAgentWhatsAppRedirectMessage(string $whatsappUrl, string $whatsappLabel): string
    {
        return sprintf(
            'Perfecto. Abre WhatsApp para conversar con nuestro equipo de negocios: [%s](%s)',
            $whatsappLabel,
            $whatsappUrl,
        );
    }

    private function extractLabeledValue(string $message, string $label): ?string
    {
        $pattern = sprintf('/%s\s*[:\-]\s*([A-Za-zÁÉÍÓÚÑáéíóúñ ]+)/u', preg_quote($label, '/'));
        if (preg_match($pattern, $message, $match) !== 1) {
            return null;
        }

        return trim(Str::of($match[1])->replaceMatches('/\s+/', ' ')->toString());
    }

    public function isCotizarKeyword(string $message): bool
    {
        return mb_strtolower(trim($message)) === 'cotizar';
    }

    public function isMultipleKeyword(string $message): bool
    {
        $normalized = mb_strtolower(trim($message));

        return in_array($normalized, ['multiple', 'múltiple', 'multiplie'], true);
    }

    public function parsePlanBenefitsRequest(string $message): ?int
    {
        if (preg_match('/^(\d)\s*beneficios?$/iu', trim($message), $match) !== 1) {
            return null;
        }

        $planId = (int) $match[1];

        return in_array($planId, [1, 2, 3], true) ? $planId : null;
    }

    /**
     * @return array{plan_id: int, age: int|null, total_persons: int, format: 'full'|'compact'}|null
     */
    public function parseIndividualQuoteLine(string $message): ?array
    {
        $normalized = trim($message);

        if (preg_match('/^(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*$/u', $normalized, $match) === 1) {
            $planId = (int) $match[1];
            $age = (int) $match[2];
            $persons = (int) $match[3];

            if (! in_array($planId, [1, 2, 3], true) || $age < 0 || $age > 120 || $persons < 1) {
                return null;
            }

            return [
                'plan_id' => $planId,
                'age' => $age,
                'total_persons' => $persons,
                'format' => 'full',
            ];
        }

        if (preg_match('/^(\d+)\s*,\s*(\d+)\s*$/u', $normalized, $match) === 1) {
            $planId = (int) $match[1];
            $persons = (int) $match[2];

            if (! in_array($planId, [1, 2, 3], true) || $persons < 1) {
                return null;
            }

            return [
                'plan_id' => $planId,
                'age' => null,
                'total_persons' => $persons,
                'format' => 'compact',
            ];
        }

        return null;
    }

    public function parseQuoteAgeOnly(string $message): ?int
    {
        if (preg_match('/^\d{1,3}$/u', trim($message), $match) !== 1) {
            return null;
        }

        $age = (int) $match[0];

        return ($age >= 0 && $age <= 120) ? $age : null;
    }

    public function individualQuoteWelcomeWithCatalogMessage(string $catalogSummary): string
    {
        return $this->registrationWelcomeHeadline(AgentConversationStateMachine::ACTION_QUOTE_INDIVIDUAL)
            ."\n\n"
            .$catalogSummary;
    }

    public function ourPlansWelcomeMessage(string $plansOverview): string
    {
        return $this->registrationWelcomeHeadline(AgentConversationStateMachine::ACTION_NUESTROS_PLANES)
            ."\n\n"
            .<<<'TEXT'
Estos son los planes de salud **Tu Doctor en Casa**. Cada sección incluye rango de edad, coberturas y beneficios para que compares con claridad.
TEXT
            ."\n\n"
            .$plansOverview
            ."\n\n"
            .$this->ourPlansFollowUpHint()
            ."\n\n"
            .$this->chatAgentAnotherActionOfferMessage();
    }

    public function ourPlansFollowUpHint(): string
    {
        return <<<'TEXT'
¿Necesitas más detalle?
• Escribe «1 beneficios», «2 beneficios» o «3 beneficios» para ampliar un plan.
• Escribe **ayuda** para hablar con un Asesor Comercial por WhatsApp.
TEXT;
    }

    public function individualQuoteModeChoiceReprompt(): string
    {
        return <<<'TEXT'
¿Deseas cotizar o conocer los beneficios de nuestros planes?

• Escribe «cotizar» para iniciar una cotización.
• Escribe el ID del plan seguido de «beneficios» (ejemplo: 1 beneficios, 2 beneficios, 3 beneficios).
TEXT;
    }

    public function individualQuoteCotizarIntroMessage(): string
    {
        return <<<'TEXT'
¡Perfecto! Vamos a cotizar.

IDs de plan: 1 = Inicial, 2 = Ideal, 3 = Especial.

Para un plan con todas sus coberturas, indica ID del plan y número de personas. Ejemplo: 1, 10

Para Plan Ideal o Especial, indica ID del plan, edad y personas. El sistema asignará el rango de edad según la tabla oficial. Ejemplo: 3, 56, 1

Para varios planes, escribe primero «multiple» y luego cada plan con plan, edad, personas.

Indica tu cotización o escribe «multiple».
TEXT;
    }

    public function individualQuoteMultipleIntroMessage(): string
    {
        return <<<'TEXT'
Modo cotización múltiple activado.

Agrega cada plan con esta estructura: ID del plan, edad, número de personas.
Ejemplo: 2, 34, 1

Después de cada plan te preguntaré si deseas agregar otro. Responde no cuando hayas terminado.

Indica el primer plan.
TEXT;
    }

    public function individualQuoteAfterEntryPrompt(bool $multipleMode): string
    {
        if ($multipleMode) {
            return <<<'TEXT'
Plan agregado correctamente.

¿Deseas agregar otro plan? Indica plan, edad y número de personas (ejemplo: 2, 30, 4) o responde no para continuar con la validación.
TEXT;
        }

        return 'Plan registrado. Ahora necesito los datos del solicitante.';
    }

    public function individualQuoteAskAgeMessage(int $planId, int $totalPersons): string
    {
        return sprintf(
            'Para el plan %d con %d persona(s), indica la edad a cotizar (ejemplo: 45).',
            $planId,
            $totalPersons,
        );
    }

    public function individualQuoteInvalidLineMessage(bool $multipleMode = false): string
    {
        if ($multipleMode) {
            return <<<'TEXT'
No pude interpretar tu cotización múltiple. Usa el formato: ID del plan, edad, número de personas (ejemplo: 2, 34, 1).
TEXT;
        }

        return <<<'TEXT'
No pude interpretar tu cotización. Usa uno de estos formatos:

• Plan Inicial (ID 1): plan, personas — ejemplo: 1, 10
• Plan Ideal (ID 2) o Especial (ID 3): plan, edad, personas — ejemplo: 3, 56, 1
• Varios planes: escribe «multiple» primero y luego plan, edad, personas por cada plan.
TEXT;
    }

    public function individualQuoteContactQuestion(string $field): string
    {
        return match ($field) {
            'full_name' => 'Indica el nombre y apellido del solicitante.',
            'agent_name' => 'Indica el nombre del agente que está generando la cotización.',
            default => 'Necesito un dato adicional para generar la cotización.',
        };
    }

    /**
     * @param  list<array{plan_id: int, age: int|null, total_persons: int}>  $entries
     * @param  array<string, mixed>  $contact
     */
    public function individualQuoteConfirmationSummary(array $entries, array $contact): string
    {
        $entryLines = collect($entries)->map(function (array $entry): string {
            $planId = (int) ($entry['plan_id'] ?? 0);
            $planLabel = trim((string) ($entry['plan_label'] ?? ''));
            $planName = $planLabel !== '' ? $planLabel : "Plan {$planId}";
            $ageRangeLabel = trim((string) ($entry['age_range_label'] ?? ''));

            if ($ageRangeLabel !== '') {
                $ageLabel = $ageRangeLabel;
                if (isset($entry['age']) && $entry['age'] !== null) {
                    $ageLabel .= ' (edad indicada: '.(int) $entry['age'].' años)';
                }
            } elseif (isset($entry['age']) && $entry['age'] !== null) {
                $ageLabel = (string) $entry['age'].' años';
            } else {
                $ageLabel = 'todas las coberturas';
            }

            return sprintf(
                '• %s (ID %d) — rango %s — %d persona(s)',
                $planName,
                $planId,
                $ageLabel,
                (int) ($entry['total_persons'] ?? 0),
            );
        })->implode("\n");

        return sprintf(
            <<<'TEXT'
Resumen de tu cotización:

%s

Datos registrados:
• Solicitante: %s
• Agente: %s

Si todo está correcto, responde si para generar la cotización.
TEXT,
            $entryLines,
            (string) ($contact['full_name'] ?? 'N/D'),
            (string) ($contact['agent_name'] ?? 'N/D'),
        );
    }

    public function individualQuoteSuccessMessage(string $code): string
    {
        return sprintf(
            '¡Listo! Tu cotización individual fue generada con el código %s.',
            $code,
        );
    }
}
