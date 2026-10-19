<?php

declare(strict_types=1);

namespace App\Support\Errors;

use App\Filament\Administration\Resources\Helpdesks\HelpdeskResource as AdministrationHelpdeskResource;
use App\Filament\Business\Resources\Helpdesks\HelpdeskResource as BusinessHelpdeskResource;
use App\Filament\Marketing\Resources\Helpdesks\HelpdeskResource as MarketingHelpdeskResource;
use App\Filament\Operations\Resources\Helpdesks\HelpdeskResource as OperationsHelpdeskResource;
use App\Filament\Telemedicina\Resources\Helpdesks\HelpdeskResource as TelemedicinaHelpdeskResource;
use App\Models\HelpDesk;
use App\Models\Permission;
use App\Models\User;
use App\Models\UserPermission;
use App\Services\HelpdeskTicketAssigneeMailService;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use App\Support\HelpdeskBusinessScrumRoles;
use App\Support\HelpdeskEventRecorder;
use App\Support\HelpdeskNoteHtmlSanitizer;
use App\Support\HelpdeskSla;
use App\Support\HelpdeskTaskStatusOptions;
use App\Support\HelpdeskTicketType;
use App\Support\SecurityAudit;
use Filament\Facades\Filament;
use Filament\Pages\Page as FilamentPage;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\Page as ResourcePage;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Abre un ticket de incidente para Sistemas desde la página de error.
 *
 * - Va directo a los desarrolladores del equipo Scrum (incidente, no backlog).
 * - Un mismo error del mismo usuario en la misma pantalla no abre dos tickets
 *   en 30 minutos: se devuelve el que ya existe mientras siga abierto.
 * - Ticket, asignación y bitácora en una transacción; correo y WhatsApp salen
 *   en cola después del commit y nunca tumban el reporte si fallan.
 */
final class ErrorReportTicket
{
    public const DEDUPE_MINUTES = 30;

    public const MAX_NOTE_LENGTH = 1000;

    /** @var array<string, class-string> */
    private const HELPDESK_RESOURCES = [
        'business' => BusinessHelpdeskResource::class,
        'operations' => OperationsHelpdeskResource::class,
        'administration' => AdministrationHelpdeskResource::class,
        'marketing' => MarketingHelpdeskResource::class,
        'telemedicina' => TelemedicinaHelpdeskResource::class,
    ];

    /**
     * @param  array<string, mixed>  $context  Contexto ya abierto con ErrorReportContext::open().
     * @return array{ticket: HelpDesk, created: bool}
     */
    public static function report(User $user, array $context, ?string $note): array
    {
        $key = ErrorReportContext::dedupeKey($context);
        $lock = Cache::lock($key.':lock', 20);

        if (! $lock->get()) {
            throw new ErrorReportException('Tu reporte ya se está enviando. Espera unos segundos.');
        }

        try {
            $existing = self::openTicket(Cache::get($key));

            if ($existing !== null) {
                return ['ticket' => $existing, 'created' => false];
            }

            $ticket = self::create($user, $context, $note);
            Cache::put($key, $ticket->getKey(), now()->addMinutes(self::DEDUPE_MINUTES));
        } finally {
            $lock->release();
        }

        self::notify($ticket, $user, $context);

        return ['ticket' => $ticket, 'created' => true];
    }

    /**
     * Enlace al ticket en el helpdesk del panel donde ocurrió el error, si el
     * usuario puede verlo ahí; si no, nulo (no se le manda a otro 403).
     *
     * @param  array<string, mixed>  $context
     */
    public static function ticketUrl(HelpDesk $ticket, array $context): ?string
    {
        $panelId = (string) ($context['panel'] ?? '');
        $resource = self::HELPDESK_RESOURCES[$panelId] ?? null;

        if ($resource === null) {
            return null;
        }

        try {
            Filament::setCurrentPanel(Filament::getPanel($panelId));

            if (! $resource::canViewAny()) {
                return null;
            }

            return $resource::getUrl('view', ['record' => $ticket], panel: $panelId);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function create(User $user, array $context, ?string $note): HelpDesk
    {
        $status = (int) $context['status'];
        $note = mb_substr(trim((string) $note), 0, self::MAX_NOTE_LENGTH);
        $insight = self::insight($context, $user);

        $description = HelpdeskNoteHtmlSanitizer::sanitize(ErrorReportDescription::html($context, [
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'id' => (int) $user->getKey(),
            'departments' => self::departments($user),
        ], $note, $insight));

        $developerIds = HelpdeskBusinessScrumRoles::developerIds();

        if ($developerIds === []) {
            Log::warning('Reporte de error: no hay desarrolladores Scrum en RRHH; el ticket queda sin asignar.', ['user_id' => $user->getKey()]);
        }

        return DB::transaction(static function () use ($user, $context, $status, $description, $developerIds, $note): HelpDesk {
            $ticket = new HelpDesk([
                'description' => $description,
                'priority' => $status >= 500 ? 'ALTA' : 'MEDIA',
                'status' => HelpdeskTaskStatusOptions::STATUS_PENDING,
                'ticket_type' => HelpdeskTicketType::INCIDENT,
                'created_by' => (string) $user->name,
                'created_by_user_id' => $user->getKey(),
                'updated_by' => (string) $user->name,
            ]);

            HelpdeskSla::applyOnCreate($ticket);
            $ticket->save();

            if ($developerIds !== []) {
                $ticket->rrhhColaboradores()->sync($developerIds);
            }

            HelpdeskEventRecorder::record(
                $ticket,
                HelpdeskEventRecorder::TYPE_SYSTEM,
                '<p>Ticket creado automáticamente desde la página de error '.$status.' y asignado a los desarrolladores de Sistemas.</p>',
                $user,
                [
                    'source' => 'error_page',
                    'status' => $status,
                    'route' => $context['route'] ?? null,
                    'url' => $context['url'] ?? null,
                    'fingerprint' => $context['exception']['fingerprint'] ?? null,
                    'with_note' => $note !== '',
                ],
            );

            return $ticket;
        });
    }

    /**
     * Fuera de la transacción: ni el correo ni el WhatsApp pueden deshacer el ticket.
     *
     * @param  array<string, mixed>  $context
     */
    private static function notify(HelpDesk $ticket, User $user, array $context): void
    {
        $details = [
            'panel' => $context['panel'] ?? null,
            'helpdesk_id' => $ticket->getKey(),
            'status' => $context['status'],
            'route' => $context['route'] ?? null,
            'url' => $context['url'] ?? null,
            'fingerprint' => $context['exception']['fingerprint'] ?? null,
        ];

        SecurityAudit::log('AUDIT_HELPDESK_ERROR_REPORTED', 'error-page.report', $details, $user);

        try {
            $mail = HelpdeskTicketAssigneeMailService::sendToEachAssigneeWithReport($ticket, 'business');
            $whatsApp = HelpdeskTicketAssigneeWhatsAppService::dispatchToEachAssigneeWithReport($ticket, (int) $user->getKey(), 'business');

            SecurityAudit::log('AUDIT_HELPDESK_TICKET_NOTIFICATIONS_PROCESSED', 'error-page.report.notifications', [
                ...$details,
                'mail_sent_count' => $mail['sent'],
                'mail_failed_count' => $mail['failed'],
                'whatsapp_dispatched_count' => $whatsApp['dispatched'],
                'whatsapp_failed_count' => $whatsApp['failed'],
            ], $user);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private static function openTicket(mixed $ticketId): ?HelpDesk
    {
        if (! is_numeric($ticketId)) {
            return null;
        }

        return HelpDesk::query()
            ->whereKey((int) $ticketId)
            ->whereNotIn('status', [HelpdeskTaskStatusOptions::STATUS_DONE, HelpdeskTaskStatusOptions::STATUS_CANCELLED])
            ->first();
    }

    /**
     * Qué pantalla falló y, en un 403, qué permiso exige y si el usuario lo tiene.
     *
     * @param  array<string, mixed>  $context
     * @return array{screen: string|null, record: string|null, permission: array{names: list<string>, slugs: list<string>, module: string|null, has: bool, super_admin_only: bool}|null}
     */
    private static function insight(array $context, User $user): array
    {
        $insight = ['screen' => null, 'record' => null, 'permission' => null];
        $class = (string) ($context['action'] ?? '');
        $record = $context['params']['record'] ?? null;

        if ($class === '' || ! class_exists($class)) {
            return $insight;
        }

        try {
            $permissionClass = null;

            if (is_subclass_of($class, ResourcePage::class)) {
                $resource = $class::getResource();
                $kind = match (true) {
                    is_subclass_of($class, ListRecords::class) => 'listado',
                    is_subclass_of($class, ViewRecord::class) => 'ver registro',
                    is_subclass_of($class, EditRecord::class) => 'editar registro',
                    is_subclass_of($class, CreateRecord::class) => 'crear registro',
                    default => null,
                };

                /** La etiqueta del menú es la que el usuario reconoce (muchos recursos no traducen el modelo). */
                $insight['screen'] = Str::ucfirst((string) ($resource::getNavigationLabel() ?: $resource::getPluralModelLabel())).($kind !== null ? ' · '.$kind : '');
                $insight['record'] = is_string($record) && $record !== '' ? '#'.$record : null;
                $permissionClass = $resource;
            } elseif (is_subclass_of($class, FilamentPage::class)) {
                $insight['screen'] = (string) $class::getNavigationLabel();
                $permissionClass = $class;
            }

            if ((int) $context['status'] === 403 && $permissionClass !== null) {
                $insight['permission'] = self::permission($permissionClass, $user);
            }
        } catch (Throwable $exception) {
            Log::info('Reporte de error: no se pudo describir la pantalla.', ['class' => $class, 'error' => $exception->getMessage()]);
        }

        return $insight;
    }

    /**
     * @return array{names: list<string>, slugs: list<string>, module: string|null, has: bool, super_admin_only: bool}|null
     */
    private static function permission(string $class, User $user): ?array
    {
        $slugs = DepartmentNavigationPermissionRegistry::slugsFor($class);
        $superAdminOnly = DepartmentNavigationPermissionRegistry::isSuperAdminOnly($class);

        if ($slugs === [] && ! $superAdminOnly) {
            return null;
        }

        $module = DepartmentNavigationPermissionRegistry::moduleFor($class);
        $permissions = $slugs === [] ? collect() : Permission::query()->whereIn('slug', $slugs)->get(['id', 'name', 'slug', 'module']);
        $inModule = $module !== null ? $permissions->where('module', $module) : collect();
        $permissions = $inModule->isNotEmpty() ? $inModule : $permissions;

        $has = $permissions->isNotEmpty() && UserPermission::query()
            ->where('user_id', $user->getKey())
            ->whereIn('permission_id', $permissions->modelKeys())
            ->exists();

        return [
            'names' => $permissions->pluck('name')->filter()->unique()->values()->all(),
            'slugs' => $slugs,
            'module' => $module,
            'has' => $has,
            'super_admin_only' => $superAdminOnly,
        ];
    }

    /**
     * @return list<string>
     */
    private static function departments(User $user): array
    {
        $departments = $user->departament;

        if (is_string($departments)) {
            $departments = json_decode($departments, true);
        }

        return is_array($departments)
            ? array_values(array_filter(array_map(static fn (mixed $value): string => trim((string) $value), $departments)))
            : [];
    }
}
