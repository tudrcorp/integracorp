<?php

declare(strict_types=1);

namespace App\Filament\Telemedicina\Resources\Helpdesks\Pages;

use App\Filament\Telemedicina\Resources\Helpdesks\HelpdeskResource;
use App\Support\HelpdeskTaskStatusOptions;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

class ListHelpdesks extends ListRecords
{
    protected static string $resource = HelpdeskResource::class;

    protected static ?string $title = 'Mis tickets';

    public function getHeading(): string|Htmlable
    {
        return new HtmlString(view('filament.telemedicina.helpdesks.list-header', [
            'summary' => self::summary(),
        ])->render());
    }

    /**
     * Conteos del encabezado en una sola consulta, sobre la misma base acotada al doctor que usa la tabla.
     * «Plazo vencido» replica HelpdeskSla::isBreached() para tickets abiertos, sin depender del job horario.
     *
     * @return array{total: int, open: int, overdue: int, done: int}
     */
    public static function summary(): array
    {
        $terminal = HelpdeskTaskStatusOptions::terminalStatuses();
        $placeholders = implode(', ', array_fill(0, count($terminal), '?'));
        $isOpen = "(status IS NULL OR status NOT IN ({$placeholders}))";
        $now = Carbon::now((string) config('app.timezone'))->toDateTimeString();

        $row = HelpdeskResource::getEloquentQuery()
            ->toBase()
            ->selectRaw(
                'COUNT(*) AS total_count, '
                ."SUM(CASE WHEN {$isOpen} THEN 1 ELSE 0 END) AS open_count, "
                ."SUM(CASE WHEN {$isOpen} AND ("
                .'(first_response_due_at IS NOT NULL AND first_responded_at IS NULL AND first_response_due_at < ?) '
                .'OR (resolution_due_at IS NOT NULL AND resolved_at IS NULL AND cancelled_at IS NULL AND resolution_due_at < ?)'
                .') THEN 1 ELSE 0 END) AS overdue_count, '
                .'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS done_count',
                [...$terminal, ...$terminal, $now, $now, HelpdeskTaskStatusOptions::STATUS_DONE],
            )
            ->first();

        return [
            'total' => (int) ($row->total_count ?? 0),
            'open' => (int) ($row->open_count ?? 0),
            'overdue' => (int) ($row->overdue_count ?? 0),
            'done' => (int) ($row->done_count ?? 0),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear ticket')
                ->icon('heroicon-o-plus')
                ->visible(fn (): bool => HelpdeskResource::canCreate()),
        ];
    }
}
