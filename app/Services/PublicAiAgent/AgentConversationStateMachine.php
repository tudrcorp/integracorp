<?php

declare(strict_types=1);

namespace App\Services\PublicAiAgent;

class AgentConversationStateMachine
{
    public const STATE_SALUDO = 'saludo';

    public const STATE_CALIFICACION = 'calificacion';

    public const STATE_INTENCION_DETECTADA = 'intencion_detectada';

    public const STATE_RECOLECCION_DATOS = 'recoleccion_datos';

    public const STATE_EJECUCION_TOOL = 'ejecucion_tool';

    public const STATE_CONFIRMACION = 'confirmacion';

    public const STATE_HANDOFF_HUMANO = 'handoff_humano';

    public const INTENT_PREREGISTRO = 'preregistro_agente_subagente';

    public const INTENT_COTIZACION = 'cotizacion_planes_salud';

    public const ACTION_QUOTE_INDIVIDUAL = 'cotizacion_individual';

    public const ACTION_QUOTE_CORPORATE = 'cotizacion_corporativa';

    public const ACTION_REGISTER_AGENCY_MASTER = 'registro_agencia_master';

    public const ACTION_REGISTER_AGENCY_GENERAL = 'registro_agencia_general';

    public const ACTION_REGISTER_AGENT = 'registro_agente';

    public const ACTION_REGISTER_SUBAGENT = 'registro_subagente';

    public function detectIntentFromText(string $message): ?string
    {
        $normalized = mb_strtolower($message);

        if (
            str_contains($normalized, 'preregistro')
            || str_contains($normalized, 'pre registro')
            || str_contains($normalized, 'sub agente')
            || str_contains($normalized, 'subagente')
            || str_contains($normalized, 'registrar agente')
            || str_contains($normalized, 'registro agencia')
            || str_contains($normalized, 'agencia master')
            || str_contains($normalized, 'agencia general')
            || str_contains($normalized, 'quiero ser agente')
        ) {
            return self::INTENT_PREREGISTRO;
        }

        if (
            str_contains($normalized, 'cotizacion')
            || str_contains($normalized, 'cotizar')
            || str_contains($normalized, 'cotizacion corporativa')
            || str_contains($normalized, 'cotizacion individual')
            || str_contains($normalized, 'plan de salud')
            || str_contains($normalized, 'precio')
            || str_contains($normalized, 'cobertura')
        ) {
            return self::INTENT_COTIZACION;
        }

        return null;
    }

    public function detectIntentFromTool(string $toolName): ?string
    {
        return match ($toolName) {
            'create_prospect_preregistration' => self::INTENT_PREREGISTRO,
            'simulate_health_quote', 'get_plan_catalog', 'get_plan_benefits' => self::INTENT_COTIZACION,
            default => null,
        };
    }

    public function intentFromAction(?string $action): ?string
    {
        return match ($action) {
            self::ACTION_QUOTE_INDIVIDUAL, self::ACTION_QUOTE_CORPORATE => self::INTENT_COTIZACION,
            self::ACTION_REGISTER_AGENCY_MASTER,
            self::ACTION_REGISTER_AGENCY_GENERAL,
            self::ACTION_REGISTER_AGENT,
            self::ACTION_REGISTER_SUBAGENT => self::INTENT_PREREGISTRO,
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public function actionKeys(): array
    {
        return [
            self::ACTION_QUOTE_INDIVIDUAL,
            self::ACTION_QUOTE_CORPORATE,
            self::ACTION_REGISTER_AGENCY_MASTER,
            self::ACTION_REGISTER_AGENCY_GENERAL,
            self::ACTION_REGISTER_AGENT,
            self::ACTION_REGISTER_SUBAGENT,
        ];
    }

    public function isValidAction(?string $action): bool
    {
        return is_string($action) && in_array($action, $this->actionKeys(), true);
    }

    public function isAgentOrSubagentRegistrationAction(?string $action): bool
    {
        return in_array($action, [self::ACTION_REGISTER_AGENT, self::ACTION_REGISTER_SUBAGENT], true);
    }

    public function isAgencyRegistrationAction(?string $action): bool
    {
        return in_array($action, [self::ACTION_REGISTER_AGENCY_MASTER, self::ACTION_REGISTER_AGENCY_GENERAL], true);
    }

    public function isIndividualQuoteAction(?string $action): bool
    {
        return $action === self::ACTION_QUOTE_INDIVIDUAL;
    }

    public function isSimplifiedAgencyMasterRegistrationAction(?string $action): bool
    {
        return $action === self::ACTION_REGISTER_AGENCY_MASTER;
    }

    public function isSimplifiedAgencyGeneralRegistrationAction(?string $action): bool
    {
        return $action === self::ACTION_REGISTER_AGENCY_GENERAL;
    }

    public function isSimplifiedPreregistrationAction(?string $action): bool
    {
        return $this->isAgentOrSubagentRegistrationAction($action)
            || $this->isAgencyRegistrationAction($action);
    }

    public function isRegistrationWithCredentialsFlow(?string $action): bool
    {
        return $this->isAgentOrSubagentRegistrationAction($action);
    }

    public function isAgencyMasterRegistrationWithDeliveryFlow(?string $action): bool
    {
        return $this->isSimplifiedAgencyMasterRegistrationAction($action)
            || $this->isSimplifiedAgencyGeneralRegistrationAction($action);
    }

    public function resolveNextState(
        string $currentState,
        ?string $intent,
        bool $toolWasExecuted,
        bool $handoffRequested,
    ): string {
        if ($handoffRequested) {
            return self::STATE_HANDOFF_HUMANO;
        }

        if ($toolWasExecuted) {
            return self::STATE_CONFIRMACION;
        }

        if ($intent !== null && $currentState === self::STATE_SALUDO) {
            return self::STATE_INTENCION_DETECTADA;
        }

        if ($intent !== null && $currentState === self::STATE_INTENCION_DETECTADA) {
            return self::STATE_RECOLECCION_DATOS;
        }

        if ($intent !== null && $currentState === self::STATE_RECOLECCION_DATOS) {
            return self::STATE_EJECUCION_TOOL;
        }

        if ($currentState === self::STATE_SALUDO) {
            return self::STATE_CALIFICACION;
        }

        return $currentState;
    }

    /**
     * @return list<string>
     */
    public function requiredFieldsForIntent(?string $intent): array
    {
        return match ($intent) {
            self::INTENT_PREREGISTRO => [
                'name',
                'email',
                'phone_1',
                'country_id',
                'state_id',
                'city_id',
                'type',
            ],
            self::INTENT_COTIZACION => [
                'plan_id',
                'members',
            ],
            default => [],
        };
    }
}
