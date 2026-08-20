<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Filament\Administration\Pages\AgendaCorporativa as AdministrationAgendaCorporativa;
use App\Filament\Administration\Pages\CalendariosTdg as AdministrationCalendariosTdg;
use App\Filament\Administration\Pages\CompensacionVaucher;
use App\Filament\Administration\Resources\AffiliationCorporateRenovationHistories\AffiliationCorporateRenovationHistoryResource as AdministrationAffiliationCorporateRenovationHistoryResource;
use App\Filament\Administration\Resources\AffiliationCorporates\AffiliationCorporateResource as AdministrationAffiliationCorporateResource;
use App\Filament\Administration\Resources\AffiliationRenovationHistories\AffiliationRenovationHistoryResource as AdministrationAffiliationRenovationHistoryResource;
use App\Filament\Administration\Resources\Affiliations\AffiliationResource as AdministrationAffiliationResource;
use App\Filament\Administration\Resources\Agencies\AgencyResource as AdministrationAgencyResource;
use App\Filament\Administration\Resources\Agents\AgentResource as AdministrationAgentResource;
use App\Filament\Administration\Resources\AnnualCollections\AnnualCollectionResource;
use App\Filament\Administration\Resources\Collections\CollectionResource;
use App\Filament\Administration\Resources\CommissionPayrolls\CommissionPayrollResource;
use App\Filament\Administration\Resources\Commissions\CommissionResource;
use App\Filament\Administration\Resources\CompanyPaidMemberships\CompanyPaidMembershipResource;
use App\Filament\Administration\Resources\CreditReconciliations\CreditReconciliationResource as AdministrationCreditReconciliationResource;
use App\Filament\Administration\Resources\DownloadZones\DownloadZoneResource as AdministrationDownloadZoneResource;
use App\Filament\Administration\Resources\Helpdesks\HelpdeskResource as AdministrationHelpdeskResource;
use App\Filament\Administration\Resources\RenovationCorporates\RenovationCorporateResource as AdministrationRenovationCorporateResource;
use App\Filament\Administration\Resources\Renovations\RenovationResource as AdministrationRenovationResource;
use App\Filament\Administration\Resources\RrhhAsignacions\RrhhAsignacionResource;
use App\Filament\Administration\Resources\RrhhCargos\RrhhCargoResource;
use App\Filament\Administration\Resources\RrhhColaboradors\RrhhColaboradorResource;
use App\Filament\Administration\Resources\RrhhDeduccions\RrhhDeduccionResource;
use App\Filament\Administration\Resources\RrhhDepartamentos\RrhhDepartamentoResource;
use App\Filament\Administration\Resources\RrhhNominas\RrhhNominaResource;
use App\Filament\Administration\Resources\RrhhPrestamos\RrhhPrestamoResource;
use App\Filament\Administration\Resources\Sales\SaleResource;
use App\Filament\Administration\Resources\TdevReports\TdevReportResource;
use App\Filament\Administration\Resources\TravelAgencies\TravelAgencyResource as AdministrationTravelAgencyResource;
use App\Filament\Administration\Resources\WhiteCompanies\WhiteCompanyResource as AdministrationWhiteCompanyResource;
use App\Filament\Business\Clusters\NuevosNegocios\NuevosNegociosCluster;
use App\Filament\Business\Pages\AgendaCorporativa;
use App\Filament\Business\Pages\CalendariosTdg;
use App\Filament\Business\Resources\AccountManagers\AccountManagerResource;
use App\Filament\Business\Resources\AffiliationCorporateRenovationHistories\AffiliationCorporateRenovationHistoryResource;
use App\Filament\Business\Resources\AffiliationCorporates\AffiliationCorporateResource;
use App\Filament\Business\Resources\AffiliationRenovationHistories\AffiliationRenovationHistoryResource;
use App\Filament\Business\Resources\Affiliations\AffiliationResource;
use App\Filament\Business\Resources\Agencies\AgencyResource;
use App\Filament\Business\Resources\Agents\AgentResource;
use App\Filament\Business\Resources\AgeRanges\AgeRangeResource;
use App\Filament\Business\Resources\BenefitCoverages\BenefitCoverageResource;
use App\Filament\Business\Resources\Benefits\BenefitResource;
use App\Filament\Business\Resources\BusinessAppointments\BusinessAppointmentsResource;
use App\Filament\Business\Resources\BusinessLines\BusinessLineResource;
use App\Filament\Business\Resources\BusinessUnits\BusinessUnitResource;
use App\Filament\Business\Resources\Cities\CityResource;
use App\Filament\Business\Resources\Companies\CompanyResource;
use App\Filament\Business\Resources\CompanyAssociates\CompanyAssociateResource;
use App\Filament\Business\Resources\ConfigCostoBenefits\ConfigCostoBenefitResource;
use App\Filament\Business\Resources\CorporateQuoteRequests\CorporateQuoteRequestResource;
use App\Filament\Business\Resources\CorporateQuotes\CorporateQuoteResource;
use App\Filament\Business\Resources\Coverages\CoverageResource;
use App\Filament\Business\Resources\CreditReconciliations\CreditReconciliationResource;
use App\Filament\Business\Resources\DownloadZones\DownloadZoneResource;
use App\Filament\Business\Resources\DressTylorQuotes\DressTylorQuoteResource;
use App\Filament\Business\Resources\Fees\FeeResource;
use App\Filament\Business\Resources\GuiaChatFeedbacks\GuiaChatFeedbackResource;
use App\Filament\Business\Resources\Helpdesks\HelpdeskResource as BusinessHelpdeskResource;
use App\Filament\Business\Resources\IndividualQuotes\IndividualQuoteResource;
use App\Filament\Business\Resources\PlanGeneratorImages\PlanGeneratorImageResource;
use App\Filament\Business\Resources\PlanGenerators\PlanGeneratorResource;
use App\Filament\Business\Resources\Plans\PlanResource;
use App\Filament\Business\Resources\ProspectAgents\ProspectAgentResource;
use App\Filament\Business\Resources\Regions\RegionResource;
use App\Filament\Business\Resources\RenovationCorporates\RenovationCorporateResource;
use App\Filament\Business\Resources\Renovations\RenovationResource;
use App\Filament\Business\Resources\States\StateResource;
use App\Filament\Business\Resources\SystemAuditTraces\SystemAuditTraceResource;
use App\Filament\Business\Resources\TdevAgencies\TdevAgencyResource;
use App\Filament\Business\Resources\TravelAgencies\TravelAgencyResource;
use App\Filament\Business\Resources\TravelAgents\TravelAgentResource;
use App\Filament\Business\Resources\Users\UserResource;
use App\Filament\Business\Resources\WhiteCompanies\WhiteCompanyResource;
use App\Filament\Business\Resources\Zones\ZoneResource;
use App\Filament\Marketing\Pages\AgendaCorporativa as MarketingAgendaCorporativa;
use App\Filament\Marketing\Pages\CalendariosTdg as MarketingCalendariosTdg;
use App\Filament\Marketing\Resources\AffiliationCorporates\AffiliationCorporateResource as MarketingAffiliationCorporateResource;
use App\Filament\Marketing\Resources\Affiliations\AffiliationResource as MarketingAffiliationResource;
use App\Filament\Marketing\Resources\Agencies\AgencyResource as MarketingAgencyResource;
use App\Filament\Marketing\Resources\Agents\AgentResource as MarketingAgentResource;
use App\Filament\Marketing\Resources\BirthdayNotifications\BirthdayNotificationResource;
use App\Filament\Marketing\Resources\Capemiacs\CapemiacResource;
use App\Filament\Marketing\Resources\CollaboratorAnniversaries\CollaboratorAnniversaryResource;
use App\Filament\Marketing\Resources\ContactLists\ContactListResource;
use App\Filament\Marketing\Resources\DataNotifications\DataNotificationResource;
use App\Filament\Marketing\Resources\DownloadZones\DownloadZoneResource as MarketingDownloadZoneResource;
use App\Filament\Marketing\Resources\Events\EventResource;
use App\Filament\Marketing\Resources\Helpdesks\HelpdeskResource as MarketingHelpdeskResource;
use App\Filament\Marketing\Resources\InfoFrees\InfoFreeResource;
use App\Filament\Marketing\Resources\MassNotifications\MassNotificationResource;
use App\Filament\Marketing\Resources\RrhhColaboradors\RrhhColaboradorResource as MarketingRrhhColaboradorResource;
use App\Filament\Marketing\Resources\TravelAgencies\TravelAgencyResource as MarketingTravelAgencyResource;
use App\Filament\Marketing\Resources\Zones\ZoneResource as MarketingZoneResource;
use App\Filament\Metrics\Clusters\Negocios\CorretajeCluster;
use App\Filament\Metrics\Clusters\Negocios\ViajesCluster;
use App\Filament\Metrics\Pages\Administracion as MetricsAdministracion;
use App\Filament\Metrics\Pages\Afiliaciones as MetricsAfiliaciones;
use App\Filament\Metrics\Pages\Cotizaciones as MetricsCotizaciones;
use App\Filament\Metrics\Pages\Negocios\Corretaje\CorretajeAgencies;
use App\Filament\Metrics\Pages\Negocios\Corretaje\CorretajeAgents;
use App\Filament\Metrics\Pages\Negocios\Viajes\ViajesAgencies;
use App\Filament\Metrics\Pages\Negocios\Viajes\ViajesAgents;
use App\Filament\Metrics\Pages\Operaciones as MetricsOperaciones;
use App\Filament\Metrics\Pages\Proveedores as MetricsProveedores;
use App\Filament\Metrics\Pages\Proyectos as MetricsProyectos;
use App\Filament\Operations\Pages\AgendaCorporativa as OperationsAgendaCorporativa;
use App\Filament\Operations\Pages\CalendariosTdg as OperationsCalendariosTdg;
use App\Filament\Operations\Pages\DashboardOperaciones;
use App\Filament\Operations\Pages\ManageOperationInventoryParameters;
use App\Filament\Operations\Resources\AccountsPayables\AccountsPayableResource;
use App\Filament\Operations\Resources\AccountsReceivables\AccountsReceivableResource;
use App\Filament\Operations\Resources\AffiliateCorporates\AffiliateCorporateResource;
use App\Filament\Operations\Resources\Affiliates\AffiliateResource;
use App\Filament\Operations\Resources\CompanyAssociates\NuevosNegociosAssociateResource;
use App\Filament\Operations\Resources\CorporateAllies\CorporateAllyResource;
use App\Filament\Operations\Resources\DoctorNurses\DoctorNurseResource;
use App\Filament\Operations\Resources\DownloadZones\DownloadZoneResource as OperationsDownloadZoneResource;
use App\Filament\Operations\Resources\Helpdesks\HelpdeskResource as OperationsHelpdeskResource;
use App\Filament\Operations\Resources\IndicadoresDeDesempeno\IndicadoresDeDesempenoResource;
use App\Filament\Operations\Resources\OperationCoordinationServices\OperationCoordinationServiceResource;
use App\Filament\Operations\Resources\OperationInventories\OperationInventoryResource;
use App\Filament\Operations\Resources\OperationInventoryEntries\OperationInventoryEntryResource;
use App\Filament\Operations\Resources\OperationInventoryMovements\OperationInventoryMovementResource;
use App\Filament\Operations\Resources\OperationInventoryOutflows\OperationInventoryOutflowResource;
use App\Filament\Operations\Resources\OperationInventoryProductCategories\OperationInventoryProductCategoryResource;
use App\Filament\Operations\Resources\OperationInventoryProducts\OperationInventoryProductResource;
use App\Filament\Operations\Resources\OperationInventoryUbications\OperationInventoryUbicationResource;
use App\Filament\Operations\Resources\OperationMedicalAppointments\OperationMedicalAppointmentResource;
use App\Filament\Operations\Resources\OperationOnCallUsers\OperationOnCallUserResource;
use App\Filament\Operations\Resources\OperationServiceOrders\OperationServiceOrderResource;
use App\Filament\Operations\Resources\OperationStatusServices\OperationStatusServiceResource;
use App\Filament\Operations\Resources\OperationTypeNegotiations\OperationTypeNegotiationResource;
use App\Filament\Operations\Resources\OperationTypeServices\OperationTypeServiceResource;
use App\Filament\Operations\Resources\PortalHelpContacts\PortalHelpContactResource;
use App\Filament\Operations\Resources\Suppliers\SupplierResource;
use App\Filament\Operations\Resources\TelemedicineCases\TelemedicineCaseResource;
use App\Filament\Operations\Resources\TelemedicineDoctors\TelemedicineDoctorResource;
use App\Filament\Operations\Resources\TelemedicineGeneralServices\TelemedicineGeneralServiceResource;
use App\Filament\Operations\Resources\TelemedicineHistoryPatients\TelemedicineHistoryPatientResource;
use App\Filament\Operations\Resources\TelemedicinePatients\TelemedicinePatientResource;
use App\Filament\Projects\Pages\Backlog;
use App\Filament\Projects\Pages\Help;
use App\Filament\Projects\Pages\Kanban;
use App\Filament\Projects\Resources\ProjectManagement\Activities\ActivityResource;
use App\Filament\Projects\Resources\ProjectManagement\Departments\DepartmentResource as ProjectDepartmentResource;
use App\Filament\Projects\Resources\ProjectManagement\Epics\EpicResource;
use App\Filament\Projects\Resources\ProjectManagement\Groups\GroupResource;
use App\Filament\Projects\Resources\ProjectManagement\Projects\ProjectResource;
use App\Filament\Projects\Resources\ProjectManagement\Sprints\SprintResource;
use App\Filament\Projects\Resources\ProjectManagement\Subprojects\SubprojectResource;

final class DepartmentNavigationPermissionRegistry
{
    /**
     * Ítems de menú restringidos exclusivamente a SUPERADMIN (no se asignan vía permisos granulares).
     *
     * @var list<class-string>
     */
    private const SUPER_ADMIN_ONLY = [
        UserResource::class,
        SystemAuditTraceResource::class,
        AccountManagerResource::class,
    ];

    /**
     * @var array<class-string, list<string>>
     */
    private const CLASS_TO_SLUGS = [
        // NEGOCIOS
        IndividualQuoteResource::class => ['cotizador-individual'],
        CorporateQuoteResource::class => ['cotizador-corporativo'],
        DressTylorQuoteResource::class => ['cotizador-dress-tylor'],
        CorporateQuoteRequestResource::class => ['solicitudes-dress-tylor'],
        AffiliationResource::class => ['afiliaciones-individuales'],
        AffiliationCorporateResource::class => ['afiliaciones-corporativas'],
        RenovationResource::class => ['renovaciones-individuales'],
        RenovationCorporateResource::class => ['renovaciones-corporativas'],
        AffiliationRenovationHistoryResource::class => ['historico-renovaciones'],
        AffiliationCorporateRenovationHistoryResource::class => ['historico-renovaciones-corporativas'],
        AgencyResource::class => ['agencias-de-corretaje'],
        AgentResource::class => ['agentes-de-corretaje'],
        TravelAgencyResource::class => ['agencias-de-viaje'],
        TravelAgentResource::class => ['agentes-de-viaje'],
        TdevAgencyResource::class => ['agencias-tdev'],
        WhiteCompanyResource::class => ['empresas-aliadas'],
        CreditReconciliationResource::class => ['conciliacion-de-credito'],
        ProspectAgentResource::class => ['capacitacion'],
        BusinessAppointmentsResource::class => ['citas'],
        PlanGeneratorResource::class => ['generador-de-planes'],
        PlanGeneratorImageResource::class => ['galeria-imagenes'],
        CompanyResource::class => ['empresas'],
        CompanyAssociateResource::class => ['asociados'],
        BusinessHelpdeskResource::class => ['helpdesks'],
        DownloadZoneResource::class => ['zona-de-descarga'],
        ZoneResource::class => ['gestion-de-carpetas'],
        NuevosNegociosCluster::class => ['nuevos-negocios'],
        AgendaCorporativa::class => ['agenda-corporativa'],
        CalendariosTdg::class => ['calendarios-tdg'],
        GuiaChatFeedbackResource::class => ['guia-chat'],
        ConfigCostoBenefitResource::class => ['porcentajes-costos'],
        PlanResource::class => ['planes'],
        BenefitResource::class => ['beneficios'],
        BenefitCoverageResource::class => ['beneficios-coberturas'],
        FeeResource::class => ['tarifas-costos'],
        RegionResource::class => ['regiones'],
        StateResource::class => ['estados'],
        CityResource::class => ['ciudades'],
        BusinessLineResource::class => ['lineas-de-servicio'],
        BusinessUnitResource::class => ['unidad-de-negocios'],
        CoverageResource::class => ['coberturas'],
        AgeRangeResource::class => ['rango-edades'],

        // ADMINISTRACION
        AdministrationAgendaCorporativa::class => ['agenda-corporativa'],
        AdministrationCalendariosTdg::class => ['calendarios-tdg'],
        AdministrationAffiliationResource::class => ['afiliaciones-individuales'],
        AdministrationAffiliationCorporateResource::class => ['afiliaciones-corporativas'],
        AdministrationRenovationResource::class => ['renovaciones-individuales'],
        AdministrationRenovationCorporateResource::class => ['renovaciones-corporativas'],
        AdministrationAffiliationRenovationHistoryResource::class => ['historico-renovaciones'],
        AdministrationAffiliationCorporateRenovationHistoryResource::class => ['historico-renovaciones-corporativas'],
        AdministrationAgencyResource::class => ['agencias-de-corretaje'],
        AdministrationAgentResource::class => ['agentes-de-corretaje'],
        AdministrationTravelAgencyResource::class => ['agencias-de-viaje'],
        AdministrationWhiteCompanyResource::class => ['empresas-aliadas'],
        SaleResource::class => ['ventas'],
        CompanyPaidMembershipResource::class => ['ventas', 'comprobantes-nuevos-negocios'],
        CollectionResource::class => ['gestion-de-cobranza'],
        AdministrationCreditReconciliationResource::class => ['conciliacion-de-credito'],
        AnnualCollectionResource::class => ['cobranza-por-mes'],
        CommissionResource::class => ['detallado-de-comisiones'],
        CommissionPayrollResource::class => ['reporte-de-comisiones'],
        TdevReportResource::class => ['reporte-tdev'],
        CompensacionVaucher::class => ['compensacion-vaucher'],
        RrhhDepartamentoResource::class => ['departamentos'],
        RrhhCargoResource::class => ['cargo-por-departamento'],
        RrhhColaboradorResource::class => ['colaboradores'],
        RrhhDeduccionResource::class => ['deducciones'],
        RrhhPrestamoResource::class => ['prestamos'],
        RrhhAsignacionResource::class => ['asignaciones'],
        RrhhNominaResource::class => ['calculo-de-nomina'],
        AdministrationDownloadZoneResource::class => ['zona-de-descarga'],
        AdministrationHelpdeskResource::class => ['helpdesk'],

        // MARKETING
        MarketingAgendaCorporativa::class => ['agenda-corporativa'],
        MarketingCalendariosTdg::class => ['calendarios-tdg'],
        MarketingAffiliationResource::class => ['afiliaciones-individuales'],
        MarketingAffiliationCorporateResource::class => ['afiliaciones-corporativas'],
        MarketingAgencyResource::class => ['agencias-de-corretaje'],
        MarketingAgentResource::class => ['agentes-de-corretaje'],
        MarketingTravelAgencyResource::class => ['agencias-de-viaje'],
        MarketingRrhhColaboradorResource::class => ['colaboradores'],
        CollaboratorAnniversaryResource::class => ['colaborador-aniversario'],
        EventResource::class => ['eventos'],
        DataNotificationResource::class => ['destinatarios'],
        MassNotificationResource::class => ['notificaciones-masivas'],
        BirthdayNotificationResource::class => ['notificaciones-cumpleaños'],
        ContactListResource::class => ['contactos'],
        CapemiacResource::class => ['capemiac'],
        InfoFreeResource::class => ['data-externa'],
        MarketingZoneResource::class => ['gestion-de-carpetas'],
        MarketingDownloadZoneResource::class => ['documentos'],
        MarketingHelpdeskResource::class => ['helpdesks'],

        // OPERACIONES
        OperationsAgendaCorporativa::class => ['agenda-corporativa'],
        OperationsCalendariosTdg::class => ['calendarios-tdg'],
        AffiliateResource::class => ['afiliados-individuales'],
        AffiliateCorporateResource::class => ['afiliados-corporativos'],
        NuevosNegociosAssociateResource::class => ['afiliados-nuevos-negocios'],
        OperationInventoryResource::class => ['inventario-general'],
        OperationInventoryEntryResource::class => ['entradas-inventario'],
        OperationInventoryOutflowResource::class => ['salidas-inventario'],
        OperationInventoryMovementResource::class => ['movimientos-inventario'],
        OperationInventoryUbicationResource::class => ['almacenes'],
        OperationInventoryProductResource::class => ['productos'],
        OperationInventoryProductCategoryResource::class => ['categorias'],
        ManageOperationInventoryParameters::class => ['parametros-inventario'],
        TelemedicineDoctorResource::class => ['doctores'],
        TelemedicinePatientResource::class => ['pacientes'],
        TelemedicineCaseResource::class => ['gestion-casos'],
        TelemedicineHistoryPatientResource::class => ['historia-clinica'],
        OperationCoordinationServiceResource::class => ['servicios-medicos'],
        OperationServiceOrderResource::class => ['ordenes-servicios'],
        OperationMedicalAppointmentResource::class => ['citas-medicas'],
        AccountsReceivableResource::class => ['cuentas-por-cobrar'],
        AccountsPayableResource::class => ['cuentas-por-pagar'],
        OperationTypeServiceResource::class => ['tipos-servicios'],
        TelemedicineGeneralServiceResource::class => ['servicios-consulta-general'],
        PortalHelpContactResource::class => ['contactos-ayuda-portal'],
        OperationTypeNegotiationResource::class => ['tipos-negociacion'],
        OperationStatusServiceResource::class => ['estados-servicio'],
        OperationOnCallUserResource::class => ['roles-de-guardia'],
        SupplierResource::class => ['proveedores-juridicos'],
        DoctorNurseResource::class => ['proveedores-naturales'],
        OperationsDownloadZoneResource::class => ['documentos'],
        OperationsHelpdeskResource::class => ['helpdesks'],
        IndicadoresDeDesempenoResource::class => ['indicadores-desempeno'],
        CorporateAllyResource::class => ['aliados-corporativos'],
        DashboardOperaciones::class => ['dashboard-operaciones'],

        // PROYECTOS
        ProjectResource::class => ['proyectos'],
        EpicResource::class => ['epicas'],
        SubprojectResource::class => ['subproyectos'],
        SprintResource::class => ['sprints'],
        Backlog::class => ['backlog'],
        GroupResource::class => ['equipos'],
        ProjectDepartmentResource::class => ['departamentos-pm'],
        ActivityResource::class => ['actividades'],
        Kanban::class => ['kanban'],
        Help::class => ['ayuda-proyectos'],

        // METRICAS / KPI
        CorretajeCluster::class => ['metricas-corretaje'],
        ViajesCluster::class => ['metricas-viajes'],
        CorretajeAgents::class => ['metricas-corretaje-agentes'],
        CorretajeAgencies::class => ['metricas-corretaje-agencias'],
        ViajesAgencies::class => ['metricas-viajes-agencias'],
        ViajesAgents::class => ['metricas-viajes-agentes'],
        MetricsCotizaciones::class => ['metricas-cotizaciones'],
        MetricsAfiliaciones::class => ['metricas-afiliaciones'],
        MetricsAdministracion::class => ['metricas-administracion'],
        MetricsProveedores::class => ['metricas-proveedores'],
        MetricsOperaciones::class => ['metricas-operaciones'],
        MetricsProyectos::class => ['metricas-proyectos'],
    ];

    /**
     * @return list<string>
     */
    public static function slugsFor(string $class): array
    {
        return self::CLASS_TO_SLUGS[$class] ?? [];
    }

    public static function moduleFor(string $class): ?string
    {
        return InternalPanelDepartmentMap::moduleForClass($class);
    }

    public static function isSuperAdminOnly(string $class): bool
    {
        return in_array($class, self::SUPER_ADMIN_ONLY, true);
    }

    /**
     * @return array<class-string, list<string>>
     */
    public static function allMappings(): array
    {
        return self::CLASS_TO_SLUGS;
    }
}
