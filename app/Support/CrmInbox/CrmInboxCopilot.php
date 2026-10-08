<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use App\Models\CrmHandoffEnvelope;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que SolIA y la conversación ya dicen del cliente, puesto en datos que el analista usa con un clic.
 *
 * No llama a ningún modelo de lenguaje: lee el sobre que mandó SolIA y busca patrones en los mensajes
 * del cliente. Cada dato dice de dónde salió para que el analista lo verifique antes de usarlo.
 */
final class CrmInboxCopilot
{
    public const FEEDBACK_LIMIT = 50;

    /**
     * @var list<string>
     */
    public const SUGGESTIONS = ['quote', 'ask_ages', 'reply', 'ask_document', 'follow_up'];

    /**
     * Minutos sin respuesta del cliente tras enviar la propuesta antes de sugerir un seguimiento.
     */
    public const FOLLOW_UP_MINUTES = 30;

    /**
     * @param  array<string, mixed>  $case  Detalle de {@see CrmInboxCase::detail()}.
     * @param  bool|null  $hasRecord  Si el número o la cédula ya tienen ficha. Null mientras se busca.
     * @return array{
     *     summary: ?string,
     *     need: ?string,
     *     notes: ?string,
     *     facts: list<array{key: string, label: string, value: string, source: string, anchor: ?string}>,
     *     missing: list<array{key: string, label: string}>,
     *     quote: ?array{holder: string, ages: string, plan: string, coverage: string},
     *     suggestion: ?array{key: string, title: string, reason: string, action: string, text: ?string},
     *     handover: ?array{label: string, time: string},
     *     ready: int,
     *     asks: array<string, string>,
     *     client: array{since: ?string, conversations: int},
     *     proposals: list<array{by: string, mine: bool, control: string, detail: string, total: string}>
     * }
     */
    public static function brief(CrmHandoffEnvelope $envelope, array $case, ?int $viewerId, ?bool $hasRecord, int $warnSeconds, CarbonInterface $now): array
    {
        $payload = is_array($envelope->payload) ? $envelope->payload : [];
        $lead = self::map($payload['lead'] ?? null);
        $session = self::map($payload['sesion'] ?? null);
        $quote = self::solQuote($payload);
        $said = self::customerLines($case);

        $holder = self::holderFact($payload, $quote);
        $ages = self::agesFact($said, $quote, $lead);
        $plan = self::planFact($said, $quote);
        $coverage = $plan !== null ? self::coverageFact($said, $plan['raw']) : null;
        $document = self::documentFact($said);

        $facts = array_values(array_filter([$holder, $ages, $plan, $coverage, $document]));
        $missing = [];

        if ($ages === null) {
            $missing[] = ['key' => 'ages', 'label' => 'Edades'];
        }

        if ($plan === null) {
            $missing[] = ['key' => 'plan', 'label' => 'Plan de interés'];
        }

        if ($document === null && $hasRecord !== true) {
            $missing[] = ['key' => 'document', 'label' => 'Cédula'];
        }

        $prefill = $ages !== null || $plan !== null ? [
            'holder' => $holder['value'] ?? '',
            'ages' => $ages['value'] ?? '',
            'plan' => $plan['raw'] ?? 'inicial',
            'coverage' => $coverage['raw'] ?? '',
        ] : null;

        $taken = (bool) ($case['taken'] ?? false);
        $mine = $taken && $viewerId !== null && ($case['taken_by'] ?? null) === $viewerId;
        $given = self::given($case);
        $ask = fn (string $question): string => ($given !== '' ? $given.', ¿' : '¿').$question;

        return [
            'summary' => self::text($session['last_summary'] ?? null) ?? self::text($payload['necesidad'] ?? null),
            'need' => self::text($lead['necesidad'] ?? null),
            'notes' => self::text($lead['notas'] ?? null),
            'facts' => array_map(fn (array $fact): array => array_diff_key($fact, ['raw' => true, 'at' => true]), $facts),
            'missing' => $missing,
            'quote' => $prefill,
            'suggestion' => $mine
                ? self::suggestion($case, $ages, $plan, $document, $hasRecord, self::handled($envelope), $warnSeconds, $now)
                : null,
            'handover' => self::handover($envelope, $viewerId, (string) ($case['area_label'] ?? '')),
            'ready' => count(array_filter([$holder, $ages, $plan, $document ?? ($hasRecord === true ? true : null)])),
            'asks' => [
                'ages' => $ask('me confirmas las edades de las personas que quieres incluir en el plan?'),
                'plan' => $ask('qué plan te interesa: Inicial, Ideal o Especial?'),
                'document' => $ask('me confirmas la cédula del titular para revisar tu ficha?'),
            ],
            'client' => self::client($envelope, $lead),
            'proposals' => self::proposals($quote, $payload, $case),
        ];
    }

    /**
     * @param  array<string, mixed>  $case
     */
    private static function given(array $case): string
    {
        $given = CrmAnalystName::given((string) ($case['name'] ?? ''));

        return $given !== '' && ($case['name'] ?? '') !== 'Sin nombre' ? $given : '';
    }

    /**
     * Desde cuándo SolIA conoce al cliente y cuántas veces lo pasó a una persona. Una consulta indexada por teléfono.
     *
     * @param  array<string, mixed>  $lead
     * @return array{since: ?string, conversations: int}
     */
    private static function client(CrmHandoffEnvelope $envelope, array $lead): array
    {
        $since = null;
        $raw = self::text($lead['created_at'] ?? null);

        if ($raw !== null) {
            try {
                $since = \Illuminate\Support\Carbon::parse($raw)->timezone('America/Caracas')->format('d/m/Y');
            } catch (\Throwable) {
                $since = null;
            }
        }

        return [
            'since' => $since,
            'conversations' => CrmHandoffEnvelope::query()->where('phone', $envelope->phone)->count(),
        ];
    }

    /**
     * Propuestas del caso: la que dejó SolIA y las que envió el equipo, de la más vieja a la más nueva.
     *
     * @param  array{control: ?string, data: array<string, mixed>}|null  $quote
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $case
     * @return list<array{by: string, mine: bool, control: string, detail: string, total: string}>
     */
    private static function proposals(?array $quote, array $payload, array $case): array
    {
        $currency = (string) config('crm-inbox.quote_currency', 'USD');
        $list = [];
        $sol = self::map($payload['cotizacion'] ?? null);
        $control = self::text($sol['control'] ?? null) ?? ($quote['control'] ?? null);

        if ($control !== null) {
            $plans = is_array($quote['data']['planes_solicitados'] ?? null) ? $quote['data']['planes_solicitados'] : [];
            $plan = CrmInboxQuote::PLANS[mb_strtolower((string) ($plans[0] ?? ''))] ?? null;
            $people = is_array($quote['data']['personas'] ?? null) ? count($quote['data']['personas']) : 0;
            $total = $sol['total'] ?? null;

            $list[] = [
                'by' => 'SolIA',
                'mine' => false,
                'control' => $control,
                'detail' => implode(' · ', array_filter([$plan, $people > 0 ? $people.' '.($people === 1 ? 'persona' : 'personas') : null])),
                'total' => is_numeric($total) ? trim($currency.' '.number_format((float) $total, 2, ',', '.')) : '',
            ];
        }

        foreach ((array) ($case['attention'] ?? []) as $message) {
            if (! is_array($message) || ($message['document'] ?? false) !== true || ($message['status'] ?? '') === 'failed') {
                continue;
            }

            $meta = is_array($message['quote'] ?? null) ? $message['quote'] : [];
            $people = (int) ($meta['people'] ?? 0);
            $caption = (string) ($message['text'] ?? '');
            preg_match('/Propuesta\s+(\S+)/u', $caption, $found);

            $list[] = [
                'by' => 'Equipo',
                'mine' => true,
                'control' => (string) (($meta['control'] ?? '') !== '' ? $meta['control'] : ($found[1] ?? 'Propuesta')),
                'detail' => implode(' · ', array_filter([
                    ($meta['plan'] ?? '') !== '' ? (string) $meta['plan'] : null,
                    $people > 0 ? $people.' '.($people === 1 ? 'persona' : 'personas') : null,
                ])),
                'total' => ($meta['total'] ?? '') !== '' ? trim($currency.' '.$meta['total']) : '',
            ];
        }

        return $list;
    }

    /**
     * Guarda lo que el analista hizo con una sugerencia. Con bloqueo de fila: dos clics no pisan la lista.
     */
    public static function record(CrmHandoffEnvelope $envelope, string $key, string $action, ?int $userId): bool
    {
        if (! in_array($key, self::SUGGESTIONS, true) || ! in_array($action, ['used', 'dismissed'], true)) {
            return false;
        }

        if (! Schema::hasColumn($envelope->getTable(), 'copilot_feedback')) {
            return false;
        }

        DB::transaction(function () use ($envelope, $key, $action, $userId): void {
            $locked = CrmHandoffEnvelope::query()->whereKey($envelope->getKey())->lockForUpdate()->first(['id', 'copilot_feedback']);

            if ($locked === null) {
                return;
            }

            $entries = is_array($locked->copilot_feedback) ? array_values($locked->copilot_feedback) : [];
            $entries[] = [
                'key' => $key,
                'action' => $action,
                'user_id' => $userId,
                'at' => now()->toIso8601String(),
            ];

            $locked->copilot_feedback = array_slice($entries, -self::FEEDBACK_LIMIT);
            $locked->save();
        });

        return true;
    }

    /**
     * @return list<int>|null
     */
    public static function ages(string $text): ?array
    {
        $found = [];
        $patterns = [
            '/(\d{1,2}(?:\s*(?:,|\by\b|\be\b|\/)\s*(?:[^\d\s,.;]+\s+){0,3}\d{1,2})*)\s*(?:años|anos|año)\b/iu',
            '/\bedad(?:es)?\s*(?:de|:)?\s*(\d{1,2}(?:\s*(?:,|\by\b|\be\b|\/)\s*\d{1,2})*)\b/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches) === false) {
                continue;
            }

            foreach ($matches[1] as $group) {
                preg_match_all('/\d{1,2}/', $group, $numbers);

                foreach ($numbers[0] as $number) {
                    $found[] = (int) $number;
                }
            }

            if ($found !== []) {
                break;
            }
        }

        $found = array_values(array_filter($found, fn (int $age): bool => $age >= 0 && $age <= 99));

        if ($found === []) {
            return null;
        }

        return array_slice($found, 0, CrmInboxQuote::MAX_PEOPLE);
    }

    public static function plan(string $text): ?string
    {
        $plans = implode('|', array_keys(CrmInboxQuote::PLANS));

        if (preg_match_all('/\b(?:plan|paquete)\s+(?:de\s+salud\s+)?('.$plans.')\b/iu', $text, $matches) && $matches[1] !== []) {
            return mb_strtolower((string) end($matches[1]));
        }

        if (preg_match('/^\s*(?:el\s+)?('.$plans.')\s*[.!]?\s*$/iu', $text, $match) === 1) {
            return mb_strtolower($match[1]);
        }

        return null;
    }

    public static function coverage(string $text, string $plan): ?int
    {
        $allowed = CrmInboxQuote::COVERAGES[$plan] ?? [];

        if ($allowed === []) {
            return null;
        }

        $candidates = [];

        if (preg_match_all('/\b(\d{1,2})\s*k\b/iu', $text, $matches)) {
            foreach ($matches[1] as $value) {
                $candidates[] = (int) $value * 1000;
            }
        }

        if (preg_match_all('/\b(\d{1,2}[.,]\d{3}|\d{4,5})\b/u', $text, $matches)) {
            foreach ($matches[1] as $value) {
                $candidates[] = (int) str_replace(['.', ','], '', $value);
            }
        }

        foreach (array_reverse($candidates) as $amount) {
            if (in_array($amount, $allowed, true)) {
                return $amount;
            }
        }

        return null;
    }

    /**
     * @return array{label: string, digits: string}|null
     */
    public static function document(string $text): ?array
    {
        $digits = null;
        $letter = null;

        if (preg_match('/\b([VEJGP])\s*[-.]?\s*(\d{1,2}\.?\d{3}\.?\d{3})\b/u', mb_strtoupper($text), $match) === 1) {
            $letter = $match[1];
            $digits = $match[2];
        } elseif (preg_match('/\bc[ée]dula\b\D{0,24}?(\d{1,2}\.?\d{3}\.?\d{3})\b/iu', $text, $match) === 1) {
            $digits = $match[1];
        }

        if ($digits === null) {
            return null;
        }

        $digits = str_replace('.', '', $digits);

        if (strlen($digits) < 6 || strlen($digits) > 9) {
            return null;
        }

        $grouped = number_format((int) $digits, 0, ',', '.');

        return [
            'label' => $letter !== null ? $letter.'-'.$grouped : $grouped,
            'digits' => $digits,
        ];
    }

    /**
     * Última cédula escrita por el cliente en el hilo, para buscar la ficha sin que el analista haga nada.
     *
     * @param  array<string, mixed>  $case
     */
    public static function detectedDocument(array $case): ?string
    {
        foreach (self::customerLines($case) as $line) {
            $document = self::document($line['text']);

            if ($document !== null) {
                return $document['digits'];
            }
        }

        return null;
    }

    /**
     * Frases del cliente, de la más reciente a la más vieja: primero la atención, luego la charla con SolIA.
     *
     * @param  array<string, mixed>  $case
     * @return list<array{text: string, time: ?string, anchor: string}>
     */
    private static function customerLines(array $case): array
    {
        $lines = [];

        foreach (array_reverse((array) ($case['attention'] ?? [])) as $message) {
            if (! is_array($message) || ($message['role'] ?? null) !== 'in') {
                continue;
            }

            $lines[] = [
                'text' => (string) ($message['text'] ?? ''),
                'time' => ($message['time'] ?? '') !== '' ? (string) $message['time'] : null,
                'anchor' => 'crm-msg-'.self::anchorId((string) ($message['id'] ?? '')),
            ];
        }

        $bot = array_values((array) ($case['messages'] ?? []));

        for ($index = count($bot) - 1; $index >= 0; $index--) {
            $message = $bot[$index];

            if (! is_array($message) || ($message['author'] ?? null) !== 'Cliente') {
                continue;
            }

            $lines[] = [
                'text' => (string) ($message['text'] ?? ''),
                'time' => $message['time'] ?? null,
                'anchor' => 'crm-bot-'.$index,
            ];
        }

        return $lines;
    }

    public static function anchorId(string $id): string
    {
        return preg_replace('/[^A-Za-z0-9_-]/', '-', $id) ?? '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{control: ?string, data: array<string, mixed>}|null  $quote
     * @return array{key: string, label: string, value: string, source: string, anchor: ?string, raw: string}|null
     */
    private static function holderFact(array $payload, ?array $quote): ?array
    {
        $fromQuote = self::text($quote['data']['titular'] ?? null);

        if ($fromQuote !== null) {
            return self::fact('holder', 'Titular', $fromQuote, self::quoteSource($quote), null, $fromQuote);
        }

        $name = self::text($payload['name'] ?? null);

        if ($name === null) {
            return null;
        }

        return self::fact('holder', 'Titular', $name, 'SolIA', null, $name);
    }

    /**
     * @param  list<array{text: string, time: ?string, anchor: string}>  $said
     * @param  array{control: ?string, data: array<string, mixed>}|null  $quote
     * @param  array<string, mixed>  $lead
     * @return array{key: string, label: string, value: string, source: string, anchor: ?string, raw: string}|null
     */
    private static function agesFact(array $said, ?array $quote, array $lead): ?array
    {
        foreach ($said as $line) {
            $ages = self::ages($line['text']);

            if ($ages !== null) {
                return self::fact('ages', 'Edades', implode(', ', $ages), self::lineSource($line), $line['anchor'], implode(', ', $ages));
            }
        }

        $people = is_array($quote['data']['personas'] ?? null) ? $quote['data']['personas'] : [];
        $fromQuote = [];

        foreach ($people as $person) {
            if (is_array($person) && is_numeric($person['edad'] ?? null) && (int) $person['edad'] >= 0 && (int) $person['edad'] <= 99) {
                $fromQuote[] = (int) $person['edad'];
            }
        }

        if ($fromQuote !== []) {
            $value = implode(', ', array_slice($fromQuote, 0, CrmInboxQuote::MAX_PEOPLE));

            return self::fact('ages', 'Edades', $value, self::quoteSource($quote), null, $value);
        }

        $notes = self::text($lead['notas'] ?? null);
        $fromNotes = $notes !== null ? self::ages($notes) : null;

        if ($fromNotes !== null) {
            $value = implode(', ', $fromNotes);

            return self::fact('ages', 'Edades', $value, 'Notas de SolIA', null, $value);
        }

        return null;
    }

    /**
     * @param  list<array{text: string, time: ?string, anchor: string}>  $said
     * @param  array{control: ?string, data: array<string, mixed>}|null  $quote
     * @return array{key: string, label: string, value: string, source: string, anchor: ?string, raw: string}|null
     */
    private static function planFact(array $said, ?array $quote): ?array
    {
        foreach ($said as $line) {
            $plan = self::plan($line['text']);

            if ($plan !== null) {
                return self::fact('plan', 'Plan de interés', CrmInboxQuote::PLANS[$plan], self::lineSource($line), $line['anchor'], $plan);
            }
        }

        $requested = is_array($quote['data']['planes_solicitados'] ?? null) ? $quote['data']['planes_solicitados'] : [];

        foreach ($requested as $plan) {
            $plan = mb_strtolower(trim((string) $plan));

            if (isset(CrmInboxQuote::PLANS[$plan])) {
                return self::fact('plan', 'Plan de interés', CrmInboxQuote::PLANS[$plan], self::quoteSource($quote), null, $plan);
            }
        }

        return null;
    }

    /**
     * @param  list<array{text: string, time: ?string, anchor: string}>  $said
     * @return array{key: string, label: string, value: string, source: string, anchor: ?string, raw: string}|null
     */
    private static function coverageFact(array $said, string $plan): ?array
    {
        foreach ($said as $line) {
            $amount = self::coverage($line['text'], $plan);

            if ($amount !== null) {
                $currency = (string) config('crm-inbox.quote_currency', 'USD');

                return self::fact('coverage', 'Cobertura', $currency.' '.number_format($amount, 0, ',', '.'), self::lineSource($line), $line['anchor'], (string) $amount);
            }
        }

        return null;
    }

    /**
     * @param  list<array{text: string, time: ?string, anchor: string}>  $said
     * @return array{key: string, label: string, value: string, source: string, anchor: ?string, raw: string}|null
     */
    private static function documentFact(array $said): ?array
    {
        foreach ($said as $line) {
            $document = self::document($line['text']);

            if ($document !== null) {
                return self::fact('document', 'Cédula', $document['label'], self::lineSource($line), $line['anchor'], $document['digits']);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $case
     * @param  array<string, string|null>|null  $ages
     * @param  array<string, string|null>|null  $plan
     * @param  array<string, string|null>|null  $document
     * @param  list<string>  $handled
     * @return array{key: string, title: string, reason: string, action: string, text: ?string}|null
     */
    private static function suggestion(array $case, ?array $ages, ?array $plan, ?array $document, ?bool $hasRecord, array $handled, int $warnSeconds, CarbonInterface $now): ?array
    {
        $attention = array_values(array_filter((array) ($case['attention'] ?? []), 'is_array'));
        $quoteSent = $attention !== [] && in_array(true, array_column($attention, 'document'), true);
        $last = null;

        for ($index = count($attention) - 1; $index >= 0; $index--) {
            if (($attention[$index]['role'] ?? null) !== 'note') {
                $last = $attention[$index];

                break;
            }
        }

        $given = self::given($case);
        $since = is_int($case['waiting_since'] ?? null) ? $case['waiting_since'] : null;
        $waited = $since !== null ? max(0, $now->getTimestamp() - $since) : 0;
        $candidates = [];

        if (! $quoteSent && $ages !== null && $plan !== null) {
            $people = count(array_filter(array_map('trim', explode(',', (string) $ages['value']))));
            $candidates[] = [
                'key' => 'quote',
                'title' => 'Cotiza el plan '.$plan['value'].' para '.$people.' '.($people === 1 ? 'persona' : 'personas'),
                'reason' => 'Ya están las edades ('.$ages['source'].') y el plan ('.$plan['source'].').',
                'action' => 'quote',
                'text' => null,
            ];
        }

        if (! $quoteSent && $plan !== null && $ages === null) {
            $candidates[] = [
                'key' => 'ask_ages',
                'title' => 'Pide las edades del grupo',
                'reason' => 'Para cotizar el plan '.$plan['value'].' hacen falta las edades de cada persona.',
                'action' => 'insert',
                'text' => ($given !== '' ? $given.', ¿' : '¿').'me confirmas las edades de las personas que quieres incluir en el plan?',
            ];
        }

        if ($last !== null && ($last['role'] ?? null) === 'in' && $waited >= $warnSeconds) {
            $minutes = max(1, intdiv($waited, 60));
            $candidates[] = [
                'key' => 'reply',
                'title' => 'Responde'.($given !== '' ? ' a '.$given : ''),
                'reason' => 'Escribió hace '.$minutes.' '.($minutes === 1 ? 'minuto' : 'minutos').' y no tiene respuesta.',
                'action' => 'focus',
                'text' => null,
            ];
        }

        if ($document === null && $hasRecord === false) {
            $candidates[] = [
                'key' => 'ask_document',
                'title' => 'Pide la cédula',
                'reason' => 'No hay ficha con este número y el cliente no ha dado la cédula.',
                'action' => 'insert',
                'text' => ($given !== '' ? $given.', ¿' : '¿').'me confirmas la cédula del titular para revisar tu ficha?',
            ];
        }

        if ($quoteSent && $last !== null && ($last['role'] ?? null) === 'out' && ($last['document'] ?? false) === true) {
            $quietSince = self::lastOutAt($attention);

            if ($quietSince !== null && $now->getTimestamp() - $quietSince >= self::FOLLOW_UP_MINUTES * 60) {
                $candidates[] = [
                    'key' => 'follow_up',
                    'title' => 'Pregunta si revisó la propuesta',
                    'reason' => 'Se envió la propuesta y el cliente no ha respondido.',
                    'action' => 'insert',
                    'text' => ($given !== '' ? $given.', ¿' : '¿').'pudiste revisar la propuesta? Si quieres la ajustamos juntos.',
                ];
            }
        }

        foreach ($candidates as $candidate) {
            if (! in_array($candidate['key'], $handled, true)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $attention
     */
    private static function lastOutAt(array $attention): ?int
    {
        for ($index = count($attention) - 1; $index >= 0; $index--) {
            if (($attention[$index]['role'] ?? null) === 'out' && is_int($attention[$index]['at'] ?? null)) {
                return $attention[$index]['at'];
            }
        }

        return null;
    }

    /**
     * @return array{label: string, time: string}|null
     */
    private static function handover(CrmHandoffEnvelope $envelope, ?int $viewerId, string $areaLabel): ?array
    {
        $byAssign = $envelope->assigned_by !== null
            && $envelope->assigned_at !== null
            && $envelope->taken_at === null
            && $envelope->assigned_to !== null
            && (int) $envelope->assigned_by !== $viewerId;
        $byMove = $envelope->moved_by !== null && $envelope->moved_at !== null && (int) $envelope->moved_by !== $viewerId;

        if (! $byAssign && ! $byMove) {
            return null;
        }

        $useAssign = $byAssign && (! $byMove || $envelope->assigned_at->greaterThanOrEqualTo($envelope->moved_at));
        $actorId = (int) ($useAssign ? $envelope->assigned_by : $envelope->moved_by);
        $at = $useAssign ? $envelope->assigned_at : $envelope->moved_at;
        $actor = self::givenName($actorId);

        if ($useAssign) {
            $forViewer = $viewerId !== null && (int) $envelope->assigned_to === $viewerId;
            $label = $forViewer ? $actor.' te lo pasó' : $actor.' lo marcó para un compañero';
        } else {
            $label = $actor.' lo pasó a '.($areaLabel !== '' ? $areaLabel : 'este equipo');
        }

        return [
            'label' => $label,
            'time' => $at->copy()->timezone('America/Caracas')->format('d/m H:i'),
        ];
    }

    private static function givenName(int $userId): string
    {
        if (! Schema::hasTable('users')) {
            return 'Un compañero';
        }

        $name = (string) User::query()->whereKey($userId)->value('name');
        $given = CrmAnalystName::given($name);

        return $given !== '' ? $given : ($name !== '' ? $name : 'Un compañero');
    }

    /**
     * Sugerencias que ya no se repiten en este caso. «Responde» vuelve cada vez que el cliente escribe,
     * así que solo se apaga si alguien dijo que no aplica.
     *
     * @return list<string>
     */
    private static function handled(CrmHandoffEnvelope $envelope): array
    {
        $entries = $envelope->getAttribute('copilot_feedback');

        if (! is_array($entries)) {
            return [];
        }

        $keys = [];

        foreach ($entries as $entry) {
            if (! is_array($entry) || ! is_string($entry['key'] ?? null)) {
                continue;
            }

            if ($entry['key'] === 'reply' && ($entry['action'] ?? null) === 'used') {
                continue;
            }

            $keys[] = $entry['key'];
        }

        return array_values(array_unique($keys));
    }

    /**
     * La cotización que SolIA ya armó: el sobre la trae en `cotizacion.quote_json` o en la sesión.
     *
     * @param  array<string, mixed>  $payload
     * @return array{control: ?string, data: array<string, mixed>}|null
     */
    private static function solQuote(array $payload): ?array
    {
        $quote = self::map($payload['cotizacion'] ?? null);
        $data = self::map($quote['quote_json'] ?? null);

        if ($data === []) {
            $data = self::map(self::map($payload['sesion'] ?? null)['last_quote_json'] ?? null);
        }

        if ($data === []) {
            return null;
        }

        return [
            'control' => self::text($quote['control'] ?? $data['control'] ?? null),
            'data' => $data,
        ];
    }

    /**
     * @param  array{control: ?string, data: array<string, mixed>}|null  $quote
     */
    private static function quoteSource(?array $quote): string
    {
        $control = $quote['control'] ?? null;

        return $control !== null ? 'Propuesta '.$control : 'Propuesta de SolIA';
    }

    /**
     * @param  array{text: string, time: ?string, anchor: string}  $line
     */
    private static function lineSource(array $line): string
    {
        return $line['time'] !== null ? 'Cliente, '.$line['time'] : 'Cliente';
    }

    /**
     * @return array{key: string, label: string, value: string, source: string, anchor: ?string, raw: string}
     */
    private static function fact(string $key, string $label, string $value, string $source, ?string $anchor, string $raw): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'value' => $value,
            'source' => $source,
            'anchor' => $anchor,
            'raw' => $raw,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function map(mixed $value): array
    {
        return is_array($value) && ! array_is_list($value) ? $value : [];
    }

    private static function text(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }
}
