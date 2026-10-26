<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\PersonIdentityFindings\Schemas;

use App\Enums\PersonIdentityFindingKind as Kind;
use App\Filament\Business\Resources\PersonIdentityFindings\PersonIdentityFindingResource;
use App\Models\PersonIdentityFinding as Finding;
use App\Models\PersonIdentityFindingEvent as Event;
use App\Support\Affiliations\PersonIdentity\PersonIdentityScanner;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * Detalle de un caso: qué se detectó, los dos registros lado a lado (tal como estaban al detectarlo
 * y como están hoy), cómo se corrige y todo lo que se hizo con él.
 */
class PersonIdentityFindingInfolist
{
    /**
     * @var array<string, string>
     */
    private const COMPARED_FIELDS = [
        'name' => 'Nombre',
        'document' => 'Cédula',
        'birth_date' => 'Nacimiento',
        'where' => 'Dónde está',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(fn (Finding $record): string => $record->kindEnum()?->label() ?? 'Caso')
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->columnSpanFull()
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('person_name')->label('Persona')->weight('bold')->copyable(),
                        TextEntry::make('severity')
                            ->label('Gravedad')
                            ->badge()
                            ->color(fn (?string $state): string => Kind::severityColor($state)),
                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->formatStateUsing(fn (Finding $record): string => $record->statusLabel())
                            ->color(fn (?string $state): string => Finding::statusColor($state)),
                    ]),
                    TextEntry::make('details.explanation')->label('Qué se detectó')->columnSpanFull(),
                    TextEntry::make('details.consequence')->label('Por qué importa')->columnSpanFull(),
                    TextEntry::make('details.repair')->label('Cómo se corrige')->columnSpanFull(),
                ]),
            Section::make('Los registros')
                ->description('Tal como están hoy en el sistema. Lo que coincide entre los dos está resaltado.')
                ->icon(Heroicon::OutlinedTableCells)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('comparison')
                        ->hiddenLabel()
                        ->state(fn (Finding $record): HtmlString => self::comparisonHtml($record))
                        ->columnSpanFull(),
                ]),
            Section::make('Resolución')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->columnSpanFull()
                ->visible(fn (Finding $record): bool => $record->status !== Finding::STATUS_OPEN)
                ->schema([
                    Grid::make(2)->schema([
                        TextEntry::make('resolved_by_name')->label('Por')->placeholder('Sistema'),
                        TextEntry::make('resolved_at')->label('Fecha')->dateTime('d/m/Y H:i'),
                    ]),
                    TextEntry::make('resolution_note')->label('Nota')->placeholder('—')->columnSpanFull(),
                ]),
            Section::make('Historial')
                ->description('Cada paso, con su nota y quién lo hizo.')
                ->icon(Heroicon::OutlinedClock)
                ->columnSpanFull()
                ->collapsible()
                ->schema([
                    TextEntry::make('history')
                        ->hiddenLabel()
                        ->state(fn (Finding $record): HtmlString => self::historyHtml($record))
                        ->columnSpanFull(),
                ]),
        ]);
    }

    private static function comparisonHtml(Finding $record): HtmlString
    {
        $sides = ['a' => $record->side('a'), 'b' => $record->side('b')];

        foreach (['a' => [$record->sourceA(), $record->record_a_id], 'b' => [$record->sourceB(), $record->record_b_id]] as $key => [$source, $id]) {
            $current = PersonIdentityScanner::currentRecord($source, $id !== null ? (int) $id : null);

            if ($current !== null) {
                $sides[$key] = $current->toArray();
            } elseif ($sides[$key] !== []) {
                $sides[$key]['where'] = ($sides[$key]['where'] ?? '').' (el registro ya no existe)';
            }
        }

        $hasB = $sides['b'] !== [];
        $head = '<th class="px-2 py-1.5 text-left font-semibold" style="width:22%"></th>'
            .'<th class="px-2 py-1.5 text-left font-semibold">Registro A</th>'
            .($hasB ? '<th class="px-2 py-1.5 text-left font-semibold">Registro B</th>' : '');
        $rows = '';

        foreach (self::COMPARED_FIELDS as $field => $label) {
            $a = trim((string) ($sides['a'][$field] ?? ''));
            $b = trim((string) ($sides['b'][$field] ?? ''));
            $keyField = match ($field) {
                'name' => 'name_key',
                'document' => 'document_key',
                'birth_date' => 'birth_date_key',
                default => null,
            };
            $matches = $hasB && $keyField !== null && filled($sides['a'][$keyField] ?? null) && ($sides['a'][$keyField] ?? null) === ($sides['b'][$keyField] ?? null);
            $cell = static fn (string $value): string => '<td class="px-2 py-1.5 align-top'.($matches ? ' font-semibold text-red-700 dark:text-red-300' : '').'">'.($value !== '' ? e($value) : '—').'</td>';

            $rows .= '<tr class="border-b border-gray-100 dark:border-white/5"><td class="px-2 py-1.5 font-medium text-gray-600 dark:text-gray-400">'.e($label).'</td>'
                .$cell($a).($hasB ? $cell($b) : '').'</tr>';
        }

        $links = '';

        foreach (['a' => 'A', 'b' => 'B'] as $key => $letter) {
            $url = $sides[$key] !== [] ? PersonIdentityFindingResource::recordUrl($sides[$key]) : null;

            if ($url !== null) {
                $links .= '<a href="'.e($url).'" class="text-blue-600 underline dark:text-blue-400" target="_blank" rel="noopener">Abrir la ficha del registro '.$letter.'</a>';
            }
        }

        return new HtmlString('<div class="space-y-3"><div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="border-b border-gray-200 dark:border-white/10">'.$head.'</tr></thead><tbody>'.$rows.'</tbody></table></div>'
            .($links !== '' ? '<div class="flex flex-wrap gap-4 text-sm">'.$links.'</div>' : '').'</div>');
    }

    private static function historyHtml(Finding $record): HtmlString
    {
        $labels = Event::actionLabels();
        $html = '<ol class="space-y-3 text-sm">';

        foreach ($record->events()->limit(50)->get() as $event) {
            $html .= '<li class="border-l-2 border-gray-200 pl-3 dark:border-white/10"><p><strong>'.e($labels[$event->action] ?? $event->action).'</strong> · '
                .e($event->created_at?->format('d/m/Y H:i') ?? '').' · '.e($event->user_name ?? 'SISTEMA').'</p>';

            if (filled($event->note)) {
                $html .= '<p class="text-gray-600 dark:text-gray-400">'.e($event->note).'</p>';
            }

            foreach ((array) ($event->payload['lines'] ?? []) as $line) {
                $html .= '<p class="text-gray-600 dark:text-gray-400">· '.e($line).'</p>';
            }

            $html .= '</li>';
        }

        return new HtmlString($html.'</ol>');
    }
}
