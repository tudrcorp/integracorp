<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineCases\Pages;

use App\Filament\Telemedicina\Resources\TelemedicineCases\TelemedicineCaseResource;
use App\Models\TelemedicineCase;
use App\Models\User;
use App\Support\Telemedicine\TelemedicineCaseFilamentListQuery;
use App\Support\Telemedicine\TelemedicineUrgentPriorities;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class ListTelemedicineCases extends ListRecords
{
    protected static string $resource = TelemedicineCaseResource::class;

    protected static ?string $title = 'Gestión de casos de telemedicina';

    public function getHeading(): string|Htmlable
    {
        $user = Auth::user();

        return new HtmlString(view('filament.telemedicina.cases.list-header', [
            'summary' => self::summary(),
            'scopeLabel' => $user instanceof User && TelemedicineCaseFilamentListQuery::userIsInTdgTelemedicinaContext($user)
                ? 'Casos de médicos TDG'
                : 'Sus casos asignados',
        ])->render());
    }

    /**
     * Conteos del encabezado en una sola consulta, con el mismo alcance que la tabla
     * ({@see TelemedicineCaseFilamentListQuery::applyTelemedicinaResourceCasesConstraints()}).
     *
     * @return array{total: int, assigned: int, follow_up: int, urgent: int, today: int}
     */
    public static function summary(): array
    {
        [$urgentCondition, $urgentBindings] = TelemedicineUrgentPriorities::sqlCondition();
        $startOfDay = Carbon::now((string) config('app.timezone'))->startOfDay()->toDateTimeString();

        $row = TelemedicineCaseFilamentListQuery::applyTelemedicinaResourceCasesConstraints(TelemedicineCase::query())
            ->toBase()
            ->selectRaw(
                'COUNT(*) AS total_count, '
                .'SUM(CASE WHEN telemedicine_cases.status = ? THEN 1 ELSE 0 END) AS assigned_count, '
                .'SUM(CASE WHEN telemedicine_cases.status = ? THEN 1 ELSE 0 END) AS follow_up_count, '
                ."SUM(CASE WHEN {$urgentCondition} THEN 1 ELSE 0 END) AS urgent_count, "
                .'SUM(CASE WHEN telemedicine_cases.created_at >= ? THEN 1 ELSE 0 END) AS today_count',
                ['ASIGNADO', 'EN SEGUIMIENTO', ...$urgentBindings, $startOfDay],
            )
            ->first();

        return [
            'total' => (int) ($row->total_count ?? 0),
            'assigned' => (int) ($row->assigned_count ?? 0),
            'follow_up' => (int) ($row->follow_up_count ?? 0),
            'urgent' => (int) ($row->urgent_count ?? 0),
            'today' => (int) ($row->today_count ?? 0),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make(),
        ];
    }
}
