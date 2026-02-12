<?php

namespace App\Filament\Administration\Resources\Sales\Widgets;

use App\Filament\Administration\Resources\Sales\Pages\ListSales;
use App\Models\Sale;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

class StatsOverviewSales extends StatsOverviewWidget
{
    use InteractsWithPageTable;

    protected ?string $heading = 'ANÁLISIS DE VENTAS POR PLAN';

    protected function getTablePage(): string
    {
        return ListSales::class;
    }

    protected function getStats(): array
    {
        $now = Carbon::now();
        $nombreMes = $now->translatedFormat('F'); // Ejemplo: "enero", "febrero"

        // Estilos para el efecto de intercambio de valores
        $customStyles = "
            <style>
                .stat-hover-container { position: relative; display: block; }
                .val-mes { display: none; }
                .desc-mes { display: none; }

                .group:hover .val-historico { display: none; }
                .group:hover .val-mes { display: block; }

                .group:hover .desc-historica { display: none; }
                .group:hover .desc-mes { display: inline; }
            </style>
        ";

        $plans = [
            ['id' => 1, 'name' => 'PLAN INICIAL', 'color' => '#9ce1ff', 'icon' => 'heroicon-m-check-badge'],
            ['id' => 2, 'name' => 'PLAN IDEAL', 'color' => '#25b4e7', 'icon' => 'heroicon-m-star'],
            ['id' => 3, 'name' => 'PLAN ESPECIAL', 'color' => '#2d89ca', 'icon' => 'heroicon-m-sparkles'],
            ['id' => 'corp', 'name' => 'PLAN CORPORATIVO', 'color' => '#3b82f6', 'icon' => 'heroicon-m-building-office'],
        ];

        return array_map(function ($plan) use ($now, $nombreMes, $customStyles) {
            $query = $this->getPageTableQuery();

            if ($plan['id'] === 'corp') {
                $query->whereNull('plan_id');
            } else {
                $query->where('plan_id', $plan['id']);
            }

            // Totales
            $totalHistorico = $query->clone()->sum('total_amount');
            $totalMesActual = $query->clone()
                ->whereMonth('created_at', $now->month)
                ->whereYear('created_at', $now->year)
                ->sum('total_amount');

            $valHistorico = 'US$ ' . number_format($totalHistorico, 2, ',', '.');
            $valMes = 'US$ ' . number_format($totalMesActual, 2, ',', '.');

            // Creamos un valor que contiene ambos datos y el CSS decide cuál mostrar
            $labelHtml = new HtmlString("
                {$customStyles}
                <div class='stat-hover-container'>
                    <span class='val-historico'>{$valHistorico}</span>
                    <span class='val-mes text-primary-600 dark:text-primary-400'>{$valMes}</span>
                </div>
            ");

            $descriptionHtml = new HtmlString("
                <span class='desc-historica'>Ventas Históricas</span>
                <span class='desc-mes text-primary-500 font-bold italic'>Total " . ucfirst($nombreMes) . "</span>
            ");

            return Stat::make($plan['name'], $labelHtml)
                ->description($descriptionHtml)
                ->descriptionIcon($plan['icon'])
                ->extraAttributes([
                    'class' => "group cursor-pointer transition-all duration-300 hover:scale-[1.02] border-b-4 border-[{$plan['color']}]",
                ]);
        }, $plans);
    }

    // protected function getStats(): array
    // {
    //     // Definimos el CSS una sola vez para inyectarlo en el primer elemento
    //     $iosStyles = "
    //         <style>
    //             /* Efecto Glassmorphism iOS */
    //             .ios-stat-card {
    //                 position: relative !important;
    //                 background: rgba(255, 255, 255, 0.4) !important;
    //                 backdrop-filter: blur(16px) saturate(180%) !important;
    //                 -webkit-backdrop-filter: blur(16px) saturate(180%) !important;
    //                 border: 1px solid rgba(255, 255, 255, 0.3) !important;
    //                 border-radius: 24px !important;
    //                 box-shadow: 0 4px 30px rgba(0, 0, 0, 0.03) !important;
    //                 transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1) !important;
    //                 overflow: hidden !important;
    //             }

    //             .dark .ios-stat-card {
    //                 background: rgba(28, 28, 30, 0.6) !important;
    //                 border: 1px solid rgba(255, 255, 255, 0.1) !important;
    //                 box-shadow: 0 4px 30px rgba(0, 0, 0, 0.2) !important;
    //             }

    //             /* Resplandor al hacer Hover */
    //             .ios-stat-card:hover {
    //                 transform: translateY(-5px) !important;
    //                 background: rgba(255, 255, 255, 0.5) !important;
    //                 box-shadow: 0 12px 40px rgba(0, 0, 0, 0.08) !important;
    //             }

    //             .dark .ios-stat-card:hover {
    //                 background: rgba(44, 44, 46, 0.8) !important;
    //                 box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4) !important;
    //             }

    //             /* Capa de brillo interna */
    //             .ios-stat-card::before {
    //                 content: '';
    //                 position: absolute;
    //                 top: 0; left: 0; right: 0; bottom: 0;
    //                 background: radial-gradient(800px circle at var(--mouse-x, 50%) var(--mouse-y, 50%), rgba(255,255,255,0.1), transparent 40%);
    //                 z-index: 1;
    //                 pointer-events: none;
    //             }

    //             /* Ajuste de tipografía y spacing */
    //             .ios-stat-card .fi-wi-stats-overview-stat-label {
    //                 font-size: 0.9rem !important;
    //                 font-weight: 600 !important;
    //                 letter-spacing: -0.01em !important;
    //                 color: #8e8e93 !important;
    //             }

    //             .ios-stat-card .fi-wi-stats-overview-stat-value {
    //                 font-size: 1.75rem !important;
    //                 font-weight: 700 !important;
    //                 letter-spacing: -0.03em !important;
    //                 margin-top: 0.5rem !important;
    //             }
    //         </style>
    //     ";

    //     return [
    //         Stat::make(new HtmlString($iosStyles . 'Ventas Totales'), '$45,230.85')
    //             ->description('12% de incremento mensual')
    //             ->descriptionIcon('heroicon-m-arrow-trending-up')
    //             ->color('success')
    //             ->extraAttributes([
    //                 'class' => 'ios-stat-card',
    //                 'onmousemove' => "this.style.setProperty('--mouse-x', event.offsetX + 'px'); this.style.setProperty('--mouse-y', event.offsetY + 'px');"
    //             ]),

    //         Stat::make('Pedidos Pendientes', '124')
    //             ->description('Requieren atención inmediata')
    //             ->descriptionIcon('heroicon-m-clock')
    //             ->color('warning')
    //             ->extraAttributes([
    //                 'class' => 'ios-stat-card',
    //                 'onmousemove' => "this.style.setProperty('--mouse-x', event.offsetX + 'px'); this.style.setProperty('--mouse-y', event.offsetY + 'px');"
    //             ]),

    //         Stat::make('Ticket Promedio', '$364.00')
    //             ->description('Basado en últimos 30 días')
    //             ->descriptionIcon('heroicon-m-presentation-chart-line')
    //             ->color('info')
    //             ->extraAttributes([
    //                 'class' => 'ios-stat-card',
    //                 'onmousemove' => "this.style.setProperty('--mouse-x', event.offsetX + 'px'); this.style.setProperty('--mouse-y', event.offsetY + 'px');"
    //             ]),
    //     ];
    // }

    // protected function getStats(): array
    // {

    //     // Definición de estilos globales con animaciones de borde inferior
    //     $iosStyles = "
    //         <style>
    //             .ios-stat-card {
    //                 position: relative !important;
    //                 background: rgba(255, 255, 255, 0.4) !important;
    //                 backdrop-filter: blur(20px) saturate(180%) !important;
    //                 -webkit-backdrop-filter: blur(20px) saturate(180%) !important;
    //                 border: 1px solid rgba(255, 255, 255, 0.3) !important;
    //                 border-radius: 24px !important;
    //                 transition: all 0.5s cubic-bezier(0.23, 1, 0.32, 1) !important;
    //                 overflow: hidden !important;
    //             }

    //             .dark .ios-stat-card {
    //                 background: rgba(28, 28, 30, 0.6) !important;
    //                 border: 1px solid rgba(255, 255, 255, 0.1) !important;
    //             }

    //             /* Línea de color inferior (oculta por defecto) */
    //             .ios-stat-card::after {
    //                 content: '';
    //                 position: absolute;
    //                 bottom: 0;
    //                 left: 50%;
    //                 width: 0;
    //                 height: 4px;
    //                 background: var(--hover-color, #007aff);
    //                 transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    //                 transform: translateX(-50%);
    //                 border-radius: 2px 2px 0 0;
    //             }

    //             .ios-stat-card:hover::after {
    //                 width: 100%;
    //             }

    //             .ios-value-container {
    //                 position: relative;
    //                 height: 2.2em;
    //                 overflow: hidden;
    //             }

    //             .ios-value-wrapper {
    //                 display: flex;
    //                 flex-direction: column;
    //                 transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
    //             }

    //             .ios-stat-card:hover .ios-value-wrapper {
    //                 transform: translateY(-50%);
    //             }

    //             .ios-value-main, .ios-value-hover {
    //                 height: 2.2em;
    //                 display: flex;
    //                 align-items: center;
    //             }

    //             .ios-value-hover {
    //                 font-size: 0.85em !important;
    //                 color: var(--hover-color) !important;
    //                 font-weight: 600;
    //             }

    //             .ios-stat-card:hover {
    //                 transform: translateY(-4px) !important;
    //                 box-shadow: 0 12px 24px rgba(0,0,0,0.05) !important;
    //             }
    //         </style>
    //     ";

    //     // Helper para renderizar el valor y pasar el color dinámico vía CSS Variable
    //     $renderStat = function ($label, $mainValue, $hoverText, $colorHex, $description, $icon, $colorName) use ($iosStyles) {
    //         return Stat::make(
    //             new HtmlString($iosStyles . $label),
    //             new HtmlString("
    //                 <div class='ios-value-container' style='--hover-color: {$colorHex}'>
    //                     <div class='ios-value-wrapper'>
    //                         <div class='ios-value-main'>{$mainValue}</div>
    //                         <div class='ios-value-hover'>{$hoverText}</div>
    //                     </div>
    //                 </div>
    //             ")
    //         )
    //             ->description($description)
    //             ->descriptionIcon($icon)
    //             ->color($colorName)
    //             ->extraAttributes([
    //                 'class' => 'ios-stat-card',
    //                 'style' => "--hover-color: {$colorHex}",
    //             ]);
    //     };

    //     return [
    //         $renderStat(
    //             ' AFILIACIONES PLAN INCIAL',
    //             '$ ' . number_format($this->getPageTableQuery()->where('plan_id', 1)->sum('total_amount'), 2, ',', '.'),
    //             'Este mes: +$12,400.00',
    //             '#34c759', // Verde iOS
    //             '12% de incremento mensual',
    //             'heroicon-m-arrow-trending-up',
    //             'success'
    //         ),

    //         $renderStat(
    //             'AFILIACIONES PLAN IDEAL',
    //             '$ ' . number_format($this->getPageTableQuery()->where('plan_id', 2)->sum('total_amount'), 2, ',', '.'),
    //             '42 procesados hoy',
    //             '#ff9500', // Naranja iOS
    //             'Requieren atención inmediata',
    //             'heroicon-m-clock',
    //             'warning'
    //         ),

    //         $renderStat(
    //             'AFILIACIONES PLAN ESPECIAL',
    //             '$ ' . number_format($this->getPageTableQuery()->where('plan_id', 3)->sum('total_amount'), 2, ',', '.'),
    //             'Subió $12.50 este mes',
    //             '#007aff', // Azul iOS
    //             'Basado en últimos 30 días',
    //             'heroicon-m-presentation-chart-line',
    //             'info'
    //         ),
    //     ];
    // }

}
