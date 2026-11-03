<?php

declare(strict_types=1);

namespace App\Support\ProspectAgents;

use App\Filament\Business\Resources\Helpdesks\HelpdeskResource;
use App\Filament\Business\Resources\ProspectAgents\ProspectAgentResource;
use App\Filament\Concerns\DispatchesHelpdeskCreateNotifications;
use App\Models\HelpDesk;
use App\Models\ProspectAgent;
use App\Models\RrhhColaborador;
use App\Models\User;
use App\Support\HelpdeskEventRecorder;
use App\Support\HelpdeskSla;
use App\Support\HelpdeskTaskStatusOptions;
use App\Support\HelpdeskTicketType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * «Ticket de trabajo» de Captación: crea un ticket del Helpdesk (Solicitud de servicio) con
 * las indicaciones y la lista de prospectos, asignado a los colaboradores elegidos, y lo
 * vincula a cada prospecto. Se gestiona desde el Helpdesk como cualquier otro ticket.
 */
final class ProspectHelpdeskTicketCreator
{
    use DispatchesHelpdeskCreateNotifications;

    public const MIN_INSTRUCTIONS_LENGTH = 15;

    public const MAX_INSTRUCTIONS_LENGTH = 5000;

    public const MAX_PROSPECTS = 500;

    public const DEFAULT_PRIORITY = 'MEDIA';

    /**
     * @return array<string, string>
     */
    public static function priorityOptions(): array
    {
        return [
            'BAJA' => 'Baja — puede esperar',
            'MEDIA' => 'Media — atención estándar',
            'ALTA' => 'Alta — requiere pronta atención',
        ];
    }

    /**
     * @param  iterable<int|string>  $prospectIds
     * @param  iterable<int|string>  $colaboradorIds
     * @param  bool  $notify  false solo para migrar un ticket ya avisado por otra vía.
     */
    public static function create(
        iterable $prospectIds,
        iterable $colaboradorIds,
        string $instructions,
        string $priority,
        User $actor,
        bool $notify = true,
        ?Carbon $createdAt = null,
        ?string $originNote = null,
    ): HelpDesk {
        $instructions = self::normalizeInstructions($instructions);
        $priority = strtoupper(trim($priority));

        if (! array_key_exists($priority, self::priorityOptions())) {
            throw new InvalidArgumentException('Elige una prioridad válida.');
        }

        $prospects = ProspectAgent::query()
            ->whereKey(self::ids($prospectIds))
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($prospects->isEmpty()) {
            throw new InvalidArgumentException('Selecciona al menos un prospecto que no esté eliminado.');
        }

        if ($prospects->count() > self::MAX_PROSPECTS) {
            throw new InvalidArgumentException('Un ticket admite hasta '.self::MAX_PROSPECTS.' prospectos.');
        }

        $colaboradorIds = RrhhColaborador::query()->whereKey(self::ids($colaboradorIds))->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        if ($colaboradorIds === []) {
            throw new InvalidArgumentException('Asigna el ticket al menos a un colaborador.');
        }

        $ticket = DB::transaction(function () use ($instructions, $priority, $actor, $prospects, $colaboradorIds, $createdAt, $originNote): HelpDesk {
            $ticket = new HelpDesk([
                'description' => self::descriptionHtml($instructions, $prospects->all()),
                'priority' => $priority,
                'status' => HelpdeskTaskStatusOptions::STATUS_PENDING,
                'ticket_type' => HelpdeskTicketType::SERVICE_REQUEST,
                'created_by' => (string) $actor->name,
                'created_by_user_id' => $actor->getKey(),
                'updated_by' => (string) $actor->name,
            ]);

            HelpdeskSla::applyOnCreate($ticket, $createdAt?->copy());

            if ($createdAt !== null) {
                $ticket->created_at = $createdAt;
                $ticket->updated_at = $createdAt;
            }

            $ticket->save();
            $ticket->rrhhColaboradores()->sync($colaboradorIds);
            $ticket->prospectAgents()->sync($prospects->modelKeys());

            $count = $prospects->count();
            HelpdeskEventRecorder::record(
                $ticket,
                HelpdeskEventRecorder::TYPE_SYSTEM,
                '<p>Ticket de trabajo creado desde Captación sobre '.$count.' '.($count === 1 ? 'prospecto' : 'prospectos').'.</p>'
                    .($originNote !== null ? '<p>'.e($originNote).'</p>' : ''),
                $actor,
                ['source' => 'prospect_work_ticket', 'prospect_ids' => $prospects->modelKeys()],
                $createdAt,
            );

            return $ticket;
        });

        if ($notify) {
            $ticket->load('rrhhColaboradores');
            (new self)->dispatchHelpdeskCreateNotifications($ticket, 'business');
        }

        return $ticket;
    }

    public static function ticketUrl(HelpDesk $ticket): ?string
    {
        try {
            return HelpdeskResource::getUrl('view', ['record' => $ticket], panel: 'business');
        } catch (Throwable) {
            return null;
        }
    }

    public static function normalizeInstructions(string $instructions): string
    {
        $instructions = trim(preg_replace("/[ \t]+/u", ' ', $instructions) ?? '');

        if (mb_strlen($instructions) < self::MIN_INSTRUCTIONS_LENGTH) {
            throw new InvalidArgumentException('Las indicaciones deben tener al menos '.self::MIN_INSTRUCTIONS_LENGTH.' caracteres.');
        }

        return mb_substr($instructions, 0, self::MAX_INSTRUCTIONS_LENGTH);
    }

    /**
     * Descripción del ticket: indicaciones y la lista de prospectos, cada uno enlazado a su ficha.
     *
     * @param  list<ProspectAgent>  $prospects
     */
    public static function descriptionHtml(string $instructions, array $prospects): string
    {
        $items = '';

        foreach ($prospects as $prospect) {
            $name = e(trim((string) $prospect->name) ?: 'Prospecto #'.$prospect->getKey());
            $url = self::prospectUrl((int) $prospect->getKey());
            $items .= '<li>'.($url !== null ? '<a href="'.e($url).'">'.$name.'</a>' : $name).'</li>';
        }

        $count = count($prospects);

        return '<p><strong>Ticket de trabajo de Captación</strong></p>'
            .'<p><strong>Indicaciones de la actividad:</strong></p>'
            .'<p>'.nl2br(e($instructions), false).'</p>'
            .'<p><strong>Prospectos ('.$count.'):</strong></p>'
            .'<ul>'.$items.'</ul>';
    }

    private static function prospectUrl(int $prospectId): ?string
    {
        try {
            return ProspectAgentResource::getUrl('view', ['record' => $prospectId], panel: 'business');
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  iterable<int|string>  $ids
     * @return list<int>
     */
    private static function ids(iterable $ids): array
    {
        $clean = [];

        foreach ($ids as $id) {
            if (is_numeric($id) && (int) $id > 0) {
                $clean[(int) $id] = (int) $id;
            }
        }

        return array_values($clean);
    }
}
