<?php

declare(strict_types=1);

namespace App\Support\Filament;

final class BusinessFilamentActionPermissionRegistry
{
    public const CREATE_CORPORATE_AFFILIATE = 'crear-afiliado-corporativo';

    public const MANAGE_CORPORATE_AFFILIATE_UPGRADES = 'gestionar-upgrades-afiliado-corporativo';

    public const CHANGE_CORPORATE_PAYMENT_FREQUENCY = 'cambiar-frecuencia-pago-afiliacion-corporativa';

    public const REVERSE_CORPORATE_PAYMENT_FREQUENCY_CHANGE = 'reversar-cambio-frecuencia-pago-corporativa';

    public const MANAGE_WHITE_COMPANY_NEGOTIATED_FEES = 'matriz-negociacion-empresas-aliadas';

    public const MANAGE_WHITE_COMPANY_DOCUMENT_BRAND = 'documentos-marca-empresas-aliadas';

    public const ASSIGN_WHITE_COMPANY_PLAN = 'asignar-plan-empresas-aliadas';

    public const WHITE_COMPANY_SALES_REPORT = 'reporte-ventas-empresas-aliadas';

    public const MANAGE_REFERIDOR = 'gestionar-referidor';

    public const REASSIGN_HELPDESK_TICKET = 'reasignar-tickets-helpdesk';

    public const DELETE_TELEMEDICINE_CASE = 'eliminar-caso-servicios-medicos';

    public const MANAGE_SUPPLIER_INTEGRACORP_PROCESSES = 'gestionar-procesos-integracorp-proveedores';

    /**
     * Módulo dueño de estas acciones. Es el valor por defecto de `modules`.
     */
    public const OWNER_MODULE = 'NEGOCIOS';

    /**
     * Acciones granulares y los módulos en los que pueden asignarse.
     *
     * Una acción disponible en varios módulos se asigna por separado en cada uno:
     * dar la matriz de negociación en ADMINISTRACION no la concede en NEGOCIOS.
     *
     * @return array<string, array{name: string, group: string, modules: list<string>}>
     */
    public static function all(): array
    {
        return [
            self::CREATE_CORPORATE_AFFILIATE => [
                'name' => 'Crear afiliado corporativo',
                'group' => 'AFILIACIONES',
                'modules' => [self::OWNER_MODULE],
            ],
            self::MANAGE_CORPORATE_AFFILIATE_UPGRADES => [
                'name' => 'Agregar y quitar upgrades de afiliados corporativos',
                'group' => 'AFILIACIONES',
                'modules' => [self::OWNER_MODULE],
            ],
            self::CHANGE_CORPORATE_PAYMENT_FREQUENCY => [
                'name' => 'Cambiar frecuencia de pago de afiliaciones corporativas',
                'group' => 'AFILIACIONES',
                'modules' => [self::OWNER_MODULE],
            ],
            self::REVERSE_CORPORATE_PAYMENT_FREQUENCY_CHANGE => [
                'name' => 'Revertir cambios de frecuencia de pago corporativa',
                'group' => 'COBRANZA',
                'modules' => ['ADMINISTRACION'],
            ],
            self::MANAGE_WHITE_COMPANY_NEGOTIATED_FEES => [
                'name' => 'Matriz de negociación',
                'group' => 'ESTRUCTURA COMERCIAL',
                'modules' => [self::OWNER_MODULE, 'ADMINISTRACION'],
            ],
            self::MANAGE_WHITE_COMPANY_DOCUMENT_BRAND => [
                'name' => 'Documentos de marca',
                'group' => 'ESTRUCTURA COMERCIAL',
                'modules' => [self::OWNER_MODULE],
            ],
            self::ASSIGN_WHITE_COMPANY_PLAN => [
                'name' => 'Asignar plan a empresa aliada',
                'group' => 'ESTRUCTURA COMERCIAL',
                'modules' => [self::OWNER_MODULE],
            ],
            self::WHITE_COMPANY_SALES_REPORT => [
                'name' => 'Reporte de ventas de empresa aliada',
                'group' => 'ESTRUCTURA COMERCIAL',
                'modules' => ['ADMINISTRACION'],
            ],
            self::MANAGE_REFERIDOR => [
                'name' => 'Asignación de referidor',
                'group' => 'ESTRUCTURA COMERCIAL',
                'modules' => [self::OWNER_MODULE, 'ADMINISTRACION'],
            ],
            self::REASSIGN_HELPDESK_TICKET => [
                'name' => 'Reasignar tickets',
                'group' => 'HELPDESK',
                'modules' => [self::OWNER_MODULE, 'ADMINISTRACION', 'OPERACIONES', 'MARKETING'],
            ],
            self::DELETE_TELEMEDICINE_CASE => [
                'name' => 'Eliminar caso de servicios médicos',
                'group' => 'COORDINACIÓN DE SERVICIOS',
                'modules' => ['OPERACIONES'],
            ],
            self::MANAGE_SUPPLIER_INTEGRACORP_PROCESSES => [
                'name' => 'Gestión de Procesos en Integracorp',
                'group' => 'PROVEEDORES JURÍDICOS',
                'modules' => ['OPERACIONES'],
            ],
        ];
    }

    public static function navigationGroupForSlug(string $slug): ?string
    {
        return self::all()[$slug]['group'] ?? null;
    }

    /**
     * @return list<string>
     */
    public static function modulesForSlug(string $slug): array
    {
        return self::all()[$slug]['modules'] ?? [self::OWNER_MODULE];
    }

    public static function slugIsAvailableInModule(string $slug, string $module): bool
    {
        return in_array(strtoupper($module), self::modulesForSlug($slug), true);
    }
}
