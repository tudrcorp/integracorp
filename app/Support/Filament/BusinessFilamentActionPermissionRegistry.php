<?php

declare(strict_types=1);

namespace App\Support\Filament;

final class BusinessFilamentActionPermissionRegistry
{
    public const CREATE_CORPORATE_AFFILIATE = 'crear-afiliado-corporativo';

    public const MANAGE_CORPORATE_AFFILIATE_UPGRADES = 'gestionar-upgrades-afiliado-corporativo';

    public const MANAGE_INDIVIDUAL_AFFILIATE_UPGRADES = 'gestionar-upgrades-afiliado-individual';

    public const CHANGE_CORPORATE_PAYMENT_FREQUENCY = 'cambiar-frecuencia-pago-afiliacion-corporativa';

    public const REVERSE_CORPORATE_PAYMENT_FREQUENCY_CHANGE = 'reversar-cambio-frecuencia-pago-corporativa';

    public const CORRECT_CORPORATE_EFFECTIVE_DATE = 'corregir-vigencia-afiliacion-corporativa';

    public const CORRECT_INDIVIDUAL_EFFECTIVE_DATE = 'corregir-vigencia-afiliacion-individual';

    public const REPAIR_DATE_INCONSISTENCIES = 'reparar-inconsistencias-de-fechas';

    public const DELETE_AFFILIATIONS = 'eliminar-afiliaciones';

    public const MANAGE_DROPPED_AFFILIATION_COLLECTIONS = 'gestionar-cuotas-afiliaciones-dadas-de-baja';

    public const MANAGE_WHITE_COMPANY_NEGOTIATED_FEES = 'matriz-negociacion-empresas-aliadas';

    public const MANAGE_WHITE_COMPANY_DOCUMENT_BRAND = 'documentos-marca-empresas-aliadas';

    public const ASSIGN_WHITE_COMPANY_PLAN = 'asignar-plan-empresas-aliadas';

    public const WHITE_COMPANY_SALES_REPORT = 'reporte-ventas-empresas-aliadas';

    public const MANAGE_REFERIDOR = 'gestionar-referidor';

    public const REASSIGN_HELPDESK_TICKET = 'reasignar-tickets-helpdesk';

    public const DELETE_TELEMEDICINE_CASE = 'eliminar-caso-servicios-medicos';

    public const DELETE_PROVIDERS = 'eliminar-proveedores';

    public const REGISTER_DIRECT_MEDICAL_SERVICE = 'registrar-servicio-medico-directo';

    public const MANAGE_SUPPLIER_INTEGRACORP_PROCESSES = 'gestionar-procesos-integracorp-proveedores';

    public const FINALIZE_OPERATION_SERVICE_ORDER = 'finalizar-ordenes-servicio';

    public const EXTERNAL_MANAGE_COVERED_ITEMS = 'gestion-externa-items-cubiertos';

    public const REVERSE_FINALIZED_ITEMS = 'reversar-items-finalizados';

    public const EDIT_INDIVIDUAL_AFFILIATE_PERSONAL_DATA = 'editar-afiliados-individuales';

    public const EDIT_CORPORATE_AFFILIATE_PERSONAL_DATA = 'editar-afiliados-corporativos';

    public const ACCEPT_EARLY_RENOVATION = 'aceptar-renovacion-anticipada';

    public const CHANGE_INDIVIDUAL_AFFILIATION_PLAN = 'cambiar-plan-afiliacion-individual';

    public const MANAGE_WORKDAYS = 'gestionar-jornadas-laborales';

    public const DOWNLOAD_USER_ACTIVITY_PDF = 'descargar-pdf-actividad-usuarios';

    public const DELETE_PENDING_PAYMENT = 'eliminar-pago-pendiente';

    public const UPDATE_CORPORATE_POPULATION = 'actualizar-poblacion-corporativa';

    public const ADJUST_POPULATION_BILLING = 'aplicar-ajustes-por-poblacion';

    public const RECALCULATE_CORPORATE_TOTALS = 'recalcular-totales-afiliacion-corporativa';

    public const DESIGN_PLAN_CERTIFICATE = 'disenar-certificado-plan';

    public const SELF_OTP_POPULATION_APPLY = 'usar-clave-propia-aplicar-poblacion';

    public const SELF_OTP_POPULATION_ROLLBACK = 'usar-clave-propia-revertir-poblacion';

    public const SELF_OTP_CLINICAL_USAGE_ACCESS = 'usar-clave-propia-uso-clinico';

    public const SELF_OTP_CLINICAL_LIMIT_OVERRIDE = 'usar-clave-propia-fuera-de-cupo';

    public const MARK_COLLECTION_FOLLOW_UP = 'marcar-estado-gestion-cobranza';

    public const MANAGE_SALE_RECEIPT_EMAIL_TEMPLATES = 'gestionar-copys-recibo-de-pago';

    public const MANAGE_COLLECTION_NOTICE_EMAIL_TEMPLATES = 'gestionar-copys-aviso-de-cobro';

    public const EDIT_ORG_STRUCTURE = 'editar-estructura-organizacional';

    public const EXPORT_ORG_CHART = 'exportar-organigrama';

    public const EDIT_OFFICE_GUARDS = 'editar-guardia-oficinas';

    public const MANAGE_MEDICAL_GUARDS = 'gestionar-guardias-medicas';

    public const VIEW_HR_AMOUNTS = 'ver-montos-rrhh';

    public const MANAGE_INDIVIDUAL_QUALITY_CONTROL = 'gestionar-quality-control-individuales';

    public const MANAGE_CORPORATE_QUALITY_CONTROL = 'gestionar-quality-control-corporativos';

    public const NOTE_INDIVIDUAL_QUALITY_CONTROL = 'notas-quality-control-individuales';

    public const NOTE_CORPORATE_QUALITY_CONTROL = 'notas-quality-control-corporativos';

    public const DELETE_CAPITATION_PLANS = 'eliminar-planes-de-capitado';

    public const EDIT_CAPITATION_PER_AFFILIATE = 'editar-capitado-por-afiliado';

    public const ASSIGN_CAPITATION_PLAN = 'asignar-plan-capitado-afiliado';

    public const CAPITATION_BILLING_REPORT = 'prefacturacion-capitado';

    public const REVERT_DATA_CHANGES = 'revertir-cambios-auditados';

    public const REVERSE_CASH_PAYMENTS = 'reversar-pagos-de-contado';

    public const INVOICE_MEDICATIONS_FROM_INVENTORY = 'facturar-medicamentos-inventario';

    public const REGISTER_MEDICATION_INVOICE_PAYMENT = 'registrar-pago-factura-medicamentos';

    public const ANNUL_MEDICATION_INVOICE = 'anular-factura-medicamentos';

    public const MANAGE_MEDICATION_FAILURES = 'gestionar-fallas-medicamentos';

    public const REPORT_MEDICATION_INVOICES = 'reporte-facturas-medicamentos';

    public const VIEW_AFFILIATE_CLINICAL_FICHA = 'ver-ficha-clinica-afiliado';

    /**
     * Módulo dueño de estas acciones. Es el valor por defecto de `modules`.
     */
    public const ADJUST_QUOTE_VALUES = 'ajustar-valores-cotizacion';

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
            self::MANAGE_INDIVIDUAL_AFFILIATE_UPGRADES => [
                'name' => 'Agregar y quitar upgrades de afiliados individuales',
                'group' => 'AFILIACIONES',
                'modules' => [self::OWNER_MODULE],
            ],
            self::CHANGE_INDIVIDUAL_AFFILIATION_PLAN => [
                'name' => 'Cambiar plan de afiliaciones individuales (plan, cobertura, frecuencia y rangos de edad)',
                'group' => 'AFILIACIONES',
                'modules' => [self::OWNER_MODULE],
            ],
            self::DELETE_AFFILIATIONS => [
                'name' => 'Eliminar afiliaciones individuales y corporativas (y restaurarlas)',
                'group' => 'AFILIACIONES',
                'modules' => [self::OWNER_MODULE, 'ADMINISTRACION'],
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
            self::CORRECT_CORPORATE_EFFECTIVE_DATE => [
                'name' => 'Corregir fecha de emisión y vigencia de afiliaciones corporativas (y revertirla)',
                'group' => 'COBRANZA',
                'modules' => ['ADMINISTRACION', self::OWNER_MODULE],
            ],
            self::CORRECT_INDIVIDUAL_EFFECTIVE_DATE => [
                'name' => 'Corregir fecha de emisión y vigencia de afiliaciones individuales (y revertirla)',
                'group' => 'COBRANZA',
                'modules' => ['ADMINISTRACION', self::OWNER_MODULE],
            ],
            self::REPAIR_DATE_INCONSISTENCIES => [
                'name' => 'Inconsistencias de fechas: reparar, revertir y marcar como no inconsistencia',
                'group' => 'COBRANZA',
                'modules' => ['ADMINISTRACION'],
            ],
            self::MANAGE_DROPPED_AFFILIATION_COLLECTIONS => [
                'name' => 'Cuotas de afiliaciones dadas de baja: anular, mantener como deuda y revertir',
                'group' => 'COBRANZA',
                'modules' => ['ADMINISTRACION'],
            ],
            self::ADJUST_POPULATION_BILLING => [
                'name' => 'Ajustes por población: aplicar y revertir el cobro de la diferencia cuando cambia la población corporativa',
                'group' => 'COBRANZA',
                'modules' => ['ADMINISTRACION'],
            ],
            self::MARK_COLLECTION_FOLLOW_UP => [
                'name' => 'Cobranza Por Mes: marcar y quitar el estado de gestión (seguimiento, aviso enviado, remitido, en revisión)',
                'group' => 'COBRANZA',
                'modules' => ['ADMINISTRACION'],
            ],
            self::MANAGE_SALE_RECEIPT_EMAIL_TEMPLATES => [
                'name' => 'Copys de recibos de pago: crear, editar, activar, desactivar y marcar el predeterminado',
                'group' => 'ADMINISTRACIÓN',
                'modules' => ['ADMINISTRACION'],
            ],
            self::MANAGE_COLLECTION_NOTICE_EMAIL_TEMPLATES => [
                'name' => 'Copys de avisos de cobro: crear, editar, activar, desactivar y marcar el predeterminado',
                'group' => 'ADMINISTRACIÓN',
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
            self::DELETE_PROVIDERS => [
                'name' => 'Eliminar y restaurar proveedores (jurídicos, naturales y aliados corporativos)',
                'group' => 'PROVEEDORES',
                'modules' => ['OPERACIONES'],
            ],
            self::REGISTER_DIRECT_MEDICAL_SERVICE => [
                'name' => 'Registrar servicio médico directo',
                'group' => 'COORDINACIÓN DE SERVICIOS',
                'modules' => ['OPERACIONES'],
            ],
            self::FINALIZE_OPERATION_SERVICE_ORDER => [
                'name' => 'Finalizar órdenes de servicio (cargando el documento)',
                'group' => 'COORDINACIÓN DE SERVICIOS',
                'modules' => ['OPERACIONES'],
            ],
            self::INVOICE_MEDICATIONS_FROM_INVENTORY => [
                'name' => 'Facturar al paciente lo no cubierto (medicamentos del inventario, laboratorios, estudios y especialistas)',
                'group' => 'COORDINACIÓN DE SERVICIOS',
                'modules' => ['OPERACIONES'],
            ],
            self::REGISTER_MEDICATION_INVOICE_PAYMENT => [
                'name' => 'Facturas a pacientes: registrar el pago',
                'group' => 'COORDINACIÓN DE SERVICIOS',
                'modules' => ['OPERACIONES'],
            ],
            self::ANNUL_MEDICATION_INVOICE => [
                'name' => 'Facturas a pacientes: anular (con motivo obligatorio; en medicamentos devuelve la existencia)',
                'group' => 'COORDINACIÓN DE SERVICIOS',
                'modules' => ['OPERACIONES'],
            ],
            self::REPORT_MEDICATION_INVOICES => [
                'name' => 'Facturas a pacientes: generar el reporte (PDF, Excel o CSV) en la tabla y en el Generador de reportes',
                'group' => 'COORDINACIÓN DE SERVICIOS',
                'modules' => ['OPERACIONES'],
            ],
            self::MANAGE_MEDICATION_FAILURES => [
                'name' => 'Fallas de medicamentos: marcar como adquirido o descartado',
                'group' => 'INVENTARIO DIAGNOMOVIL',
                'modules' => ['OPERACIONES'],
            ],
            self::EXTERNAL_MANAGE_COVERED_ITEMS => [
                'name' => 'Gestión externa de ítems cubiertos',
                'group' => 'COORDINACIÓN DE SERVICIOS',
                'modules' => ['OPERACIONES'],
            ],
            self::REVERSE_FINALIZED_ITEMS => [
                'name' => 'Reversar ítems finalizados',
                'group' => 'COORDINACIÓN DE SERVICIOS',
                'modules' => ['OPERACIONES'],
            ],
            self::MANAGE_SUPPLIER_INTEGRACORP_PROCESSES => [
                'name' => 'Gestión de Procesos en Integracorp',
                'group' => 'PROVEEDORES JURÍDICOS',
                'modules' => ['OPERACIONES'],
            ],
            self::EDIT_INDIVIDUAL_AFFILIATE_PERSONAL_DATA => [
                'name' => 'Editar datos personales de afiliados individuales',
                'group' => 'AFILIADOS',
                'modules' => ['OPERACIONES'],
            ],
            self::EDIT_CORPORATE_AFFILIATE_PERSONAL_DATA => [
                'name' => 'Editar datos personales de afiliados corporativos',
                'group' => 'AFILIADOS',
                'modules' => ['OPERACIONES'],
            ],
            self::MANAGE_INDIVIDUAL_QUALITY_CONTROL => [
                'name' => 'Quality Control Individuales: editar y eliminar encuestas',
                'group' => 'AFILIADOS',
                'modules' => ['OPERACIONES'],
            ],
            self::MANAGE_CORPORATE_QUALITY_CONTROL => [
                'name' => 'Quality Control Corporativos: editar y eliminar encuestas',
                'group' => 'AFILIADOS',
                'modules' => ['OPERACIONES'],
            ],
            self::NOTE_INDIVIDUAL_QUALITY_CONTROL => [
                'name' => 'Quality Control Individuales: agregar notas de seguimiento',
                'group' => 'AFILIADOS',
                'modules' => ['OPERACIONES'],
            ],
            self::NOTE_CORPORATE_QUALITY_CONTROL => [
                'name' => 'Quality Control Corporativos: agregar notas de seguimiento',
                'group' => 'AFILIADOS',
                'modules' => ['OPERACIONES'],
            ],
            self::MANAGE_WORKDAYS => [
                'name' => 'Actividad de usuarios: cambiar el tipo de jornada y registrar ausencias (vacaciones, reposos, permisos)',
                'group' => 'MONITOREO Y SEGURIDAD',
                'modules' => [self::OWNER_MODULE],
            ],
            self::DOWNLOAD_USER_ACTIVITY_PDF => [
                'name' => 'Actividad de usuarios: descargar el PDF detallado de la actividad de una persona',
                'group' => 'MONITOREO Y SEGURIDAD',
                'modules' => [self::OWNER_MODULE],
            ],
            self::UPDATE_CORPORATE_POPULATION => [
                'name' => 'Actualizar población corporativa desde el archivo del cliente (aplicar y revertir con OTP)',
                'group' => 'AFILIACIONES',
                'modules' => [self::OWNER_MODULE],
            ],
            self::DESIGN_PLAN_CERTIFICATE => [
                'name' => 'Planes: subir, restaurar y quitar el diseño HTML de la hoja del certificado (Generador de Certificado)',
                'group' => 'PLANES',
                'modules' => [self::OWNER_MODULE],
            ],
            self::RECALCULATE_CORPORATE_TOTALS => [
                'name' => 'Recalcular población, tarifa anual y total de una afiliación corporativa con sus afiliados activos',
                'group' => 'AFILIACIONES',
                'modules' => [self::OWNER_MODULE],
            ],
            self::SELF_OTP_POPULATION_APPLY => [
                'name' => 'Clave OTP: usar la clave propia para APLICAR una carga de población (sin esperar al autorizador)',
                'group' => 'CLAVES OTP',
                'modules' => [self::OWNER_MODULE],
            ],
            self::SELF_OTP_POPULATION_ROLLBACK => [
                'name' => 'Clave OTP: usar la clave propia para REVERTIR una carga de población (sin esperar al autorizador)',
                'group' => 'CLAVES OTP',
                'modules' => [self::OWNER_MODULE],
            ],
            self::SELF_OTP_CLINICAL_USAGE_ACCESS => [
                'name' => 'Clave OTP: usar la clave propia para entrar a la configuración de uso clínico (sin esperar al SUPERADMIN)',
                'group' => 'CLAVES OTP',
                'modules' => [self::OWNER_MODULE],
            ],
            self::SELF_OTP_CLINICAL_LIMIT_OVERRIDE => [
                'name' => 'Clave OTP: usar la clave propia para autorizar un servicio fuera de cupo en telemedicina',
                'group' => 'CLAVES OTP',
                'modules' => ['TELEMEDICINA'],
            ],
            self::VIEW_HR_AMOUNTS => [
                'name' => 'Ver sueldos y costo de nómina en el Escritorio',
                'group' => 'RRHH',
                'modules' => ['ADMINISTRACION'],
            ],
            self::REVERSE_CASH_PAYMENTS => [
                'name' => 'Pagos de contado: reversar el pago de contado de una factura de proveedor (con razón obligatoria)',
                'group' => 'CUENTAS POR PAGAR',
                'modules' => ['OPERACIONES', 'ADMINISTRACION'],
            ],
            self::REVERT_DATA_CHANGES => [
                'name' => 'Auditoría de cambios: revertir un cambio de afiliaciones, cotizaciones, agencias, agentes, ventas, cobranza, comisiones, planes, tarifas o Nuevos Negocios (con razón obligatoria)',
                'group' => 'SISTEMA',
                'modules' => ['ADMINISTRACION'],
            ],
            self::CAPITATION_BILLING_REPORT => [
                'name' => 'Centro de reportes: pre-facturación de capitado por proveedor Outsourcing (generar, cerrar y reabrir el mes con razón)',
                'group' => 'REPORTES',
                'modules' => ['ADMINISTRACION'],
            ],
            self::DELETE_PENDING_PAYMENT => [
                'name' => 'Eliminar pagos pendientes del historial de pagos (con nota obligatoria)',
                'group' => 'AFILIACIONES',
                'modules' => [self::OWNER_MODULE, 'ADMINISTRACION'],
            ],
            self::EDIT_ORG_STRUCTURE => [
                'name' => 'Estructura organizacional: crear y editar borradores del organigrama y enviarlos a aprobación',
                'group' => 'DEL USUARIO',
                'modules' => ['NEGOCIOS'],
            ],
            self::EXPORT_ORG_CHART => [
                'name' => 'Organigrama: exportar a PDF e imagen',
                'group' => 'DEL USUARIO',
                'modules' => ['NEGOCIOS'],
            ],
            self::ACCEPT_EARLY_RENOVATION => [
                'name' => 'Renovar antes del período de renovación (individual y corporativa)',
                'group' => 'AFILIACIONES',
                'modules' => [self::OWNER_MODULE, 'ADMINISTRACION'],
            ],
            self::EDIT_OFFICE_GUARDS => [
                'name' => 'Guardia Oficinas: crear, actualizar y eliminar',
                'group' => 'DEL USUARIO',
                'modules' => ['NEGOCIOS', 'ADMINISTRACION', 'OPERACIONES', 'MARKETING'],
            ],
            self::DELETE_CAPITATION_PLANS => [
                'name' => 'Planes de Capitado: eliminar planes (con razón obligatoria)',
                'group' => 'CONFIGURACIÓN',
                'modules' => [self::OWNER_MODULE],
            ],
            self::ASSIGN_CAPITATION_PLAN => [
                'name' => 'Capitado por afiliado: asignar, quitar o deshacer un plan de capitado directamente a uno o varios afiliados (con razón obligatoria)',
                'group' => 'AFILIADOS',
                'modules' => [self::OWNER_MODULE, 'OPERACIONES'],
            ],
            self::EDIT_CAPITATION_PER_AFFILIATE => [
                'name' => 'Capitado por afiliado: ajustar el monto negociado de uno o varios afiliados (con razón obligatoria)',
                'group' => 'AFILIADOS',
                'modules' => [self::OWNER_MODULE, 'OPERACIONES'],
            ],
            self::MANAGE_MEDICAL_GUARDS => [
                'name' => 'Guardias Médicas: cargar, editar, copiar y anular',
                'group' => 'DEL USUARIO',
                'modules' => ['OPERACIONES'],
            ],
            self::VIEW_AFFILIATE_CLINICAL_FICHA => [
                'name' => 'Ficha del afiliado: incluir la información de operaciones y telemedicina (datos clínicos)',
                'group' => 'AFILIADOS',
                'modules' => [self::OWNER_MODULE, 'ADMINISTRACION', 'OPERACIONES'],
            ],
            self::ADJUST_QUOTE_VALUES => [
                'name' => 'Cotizaciones: ajustar tarifas y personas (individual y corporativa) con efecto en la afiliación y su cobranza, y revertir el ajuste',
                'group' => 'COTIZACIONES',
                'modules' => [self::OWNER_MODULE],
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
