<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\BusinessAppointments\Widgets;

use App\Filament\Business\Resources\BusinessAppointments\Pages\ListBusinessAppointments;
use App\Filament\Business\Resources\ProspectAgents\Widgets\Concerns\AgencyLikeBarChartStyling;
use App\Filament\Widgets\Concerns\InteractsWithPageTable;
use App\Models\BusinessAppointmentObservation;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

class BusinessAppointmentNotesByUserChart extends ChartWidget
{
    use AgencyLikeBarChartStyling;
    use InteractsWithPageTable;

    protected string $view = 'filament.widgets.prospect-chart-agency-style';

    protected string $color = 'gray';

    protected ?string $heading = 'Cuantificación de notas por usuario';

    protected ?string $description = 'Cantidad de observaciones creadas por cada usuario.';

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '280px';

    public ?int $chartYear = null;

    public ?int $chartMonth = null;

    protected function getTablePage(): string
    {
        return ListBusinessAppointments::class;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        return self::buildChartData((clone $this->getPageTableQuery()));
    }

    /**
     * @return array{datasets: array<int, array<string, mixed>>, labels: array<int, string>}
     */
    public static function buildChartData(Builder $appointmentsQuery): array
    {
        $appointmentSubQuery = (clone $appointmentsQuery)->toBase()->select('business_appointments.id');

        $rows = BusinessAppointmentObservation::query()
            ->selectRaw("COALESCE(NULLIF(created_by, ''), 'Sin usuario') as author_name, COUNT(*) as total")
            ->whereIn('business_appointment_id', $appointmentSubQuery)
            ->groupBy('author_name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $values = $rows->pluck('total')->map(static fn (mixed $value): int => (int) $value)->values()->all();
        $colors = (new self)->glassBarColorsForValues($values);

        return [
            'datasets' => [[
                'label' => 'Notas registradas',
                'data' => $values,
                'backgroundColor' => $colors['fills'],
                'borderColor' => $colors['strokes'],
                'borderWidth' => 1.25,
                'borderRadius' => 10,
                'borderSkipped' => false,
                'hoverBackgroundColor' => $colors['hovers'],
            ]],
            'labels' => $rows->pluck('author_name')->map(static fn (mixed $value): string => (string) $value)->values()->all(),
        ];
    }

    protected function getOptions(): array
    {
        return $this->agencyStyleVerticalBarChartOptions();
    }
}
