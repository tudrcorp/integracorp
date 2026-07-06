<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\IndividualQuotePdfLayout;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class CorporateQuoteController extends Controller
{
    private static function ensureQuotesDirectoryExists(): void
    {
        $directory = public_path('storage/quotes');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function generatePdf(array $details, int|string $user, string $layout): void
    {
        match ($layout) {
            IndividualQuotePdfLayout::Inicial => self::generatePdfPlanIncial($details, $user),
            IndividualQuotePdfLayout::Especial => self::generatePdfPlanEspecial($details, $user),
            default => self::generatePdfPlanIdeal($details, $user),
        };
    }

    public static function generatePdfPlanIncial($details, $user)
    {
        try {

            $user = User::findOrFail($user);

            $collect = collect($details['data'][0] ?? $details['data'] ?? []);

            ini_set('memory_limit', '2048M');
            set_time_limit(120);

            self::ensureQuotesDirectoryExists();

            $name_user = $details['agent_name'] ?? $user->name;
            $layout = $details['layout'] ?? IndividualQuotePdfLayout::Inicial;
            $pdf = Pdf::loadView('documents.propuesta-economica', compact('details', 'collect', 'name_user', 'layout'));
            $name_pdf = $details['code'].'.pdf';
            $pdf->save(public_path('storage/quotes/'.$name_pdf));

            Notification::make()
                ->title('¡TAREA COMPLETADA!')
                ->body('📎 '.$details['code'].'.pdf ya se encuentra disponible para su descarga.')
                ->success()
                ->actions([
                    Action::make('download')
                        ->label('Descargar archivo')
                        ->url('/storage/quotes/'.$details['code'].'.pdf'),
                ])
                ->sendToDatabase($user);
        } catch (\Throwable $th) {

            Log::info('generatePdfPlanIncial: FAILED');
            Log::error($th->getMessage());

            Notification::make()
                ->title('¡TAREA NO COMPLETADA!')
                ->body('Hubo un error en la creación de la propuesta economica. Por favor, contacte con el administrador del Sistema.')
                ->danger()
                ->sendToDatabase($user);
        }
    }

    public static function generatePdfPlanIdeal($details, $user)
    {
        try {

            $user = User::findOrFail($user);

            $collect = collect($details['data']);
            $group_collect = $collect->groupBy('age_range');

            ini_set('memory_limit', '2048M');
            set_time_limit(120);

            self::ensureQuotesDirectoryExists();

            $name_user = $details['agent_name'] ?? $user->name;
            $layout = $details['layout'] ?? IndividualQuotePdfLayout::Ideal;
            $pdf = Pdf::loadView('documents.propuesta-economica', compact('details', 'group_collect', 'name_user', 'layout'));
            $name_pdf = $details['code'].'.pdf';
            $pdf->save(public_path('storage/quotes/'.$name_pdf));

            Notification::make()
                ->title('¡TAREA COMPLETADA!')
                ->body('📎 '.$details['code'].'.pdf ya se encuentra disponible para su descarga.')
                ->success()
                ->actions([
                    Action::make('download')
                        ->label('Descargar archivo')
                        ->url('/storage/quotes/'.$details['code'].'.pdf'),
                ])
                ->sendToDatabase($user);
        } catch (\Throwable $th) {

            Log::info('generatePdfPlanIdeal: FAILED');
            Log::error($th->getMessage());

            Notification::make()
                ->title('¡TAREA NO COMPLETADA!')
                ->body('Hubo un error en la creación de la propuesta economica. Por favor, contacte con el administrador del Sistema.')
                ->danger()
                ->sendToDatabase($user);
        }
    }

    public static function generatePdfPlanEspecial($details, $user)
    {
        try {

            $user = User::findOrFail($user);

            $collect = collect($details['data']);
            $group_collect = $collect->groupBy('age_range');

            ini_set('memory_limit', '2048M');
            set_time_limit(120);

            self::ensureQuotesDirectoryExists();

            $name_user = $details['agent_name'] ?? $user->name;
            $layout = $details['layout'] ?? IndividualQuotePdfLayout::Especial;
            $pdf = Pdf::loadView('documents.propuesta-economica', compact('details', 'group_collect', 'name_user', 'layout'));
            $name_pdf = $details['code'].'.pdf';
            $pdf->save(public_path('storage/quotes/'.$name_pdf));

            Notification::make()
                ->title('¡TAREA COMPLETADA!')
                ->body('📎 '.$details['code'].'.pdf ya se encuentra disponible para su descarga.')
                ->success()
                ->actions([
                    Action::make('download')
                        ->label('Descargar archivo')
                        ->url('/storage/quotes/'.$details['code'].'.pdf'),
                ])
                ->sendToDatabase($user);

        } catch (\Throwable $th) {

            Log::info('generatePdfPlanEspecial: FAILED');
            Log::error($th->getMessage());

            Notification::make()
                ->title('¡TAREA NO COMPLETADA!')
                ->body('Hubo un error en la creación de la propuesta economica. Por favor, contacte con el administrador del Sistema.')
                ->danger()
                ->sendToDatabase($user);
        }
    }

    public static function generatePdfMultiple($collect_final, $user)
    {
        try {

            $user = User::findOrFail($user);

            $details_generals = [];
            for ($i = 0; $i < count($collect_final); $i++) {
                $details_generals = [
                    'code' => $collect_final[$i]['code'],
                    'name' => $collect_final[$i]['name'],
                    'agent_name' => $collect_final[$i]['agent_name'] ?? null,
                    'email' => $collect_final[$i]['email'],
                    'phone' => $collect_final[$i]['phone'],
                    'date' => $collect_final[$i]['date'],
                ];
                break;
            }

            ini_set('memory_limit', '2048M');
            set_time_limit(120);

            self::ensureQuotesDirectoryExists();

            $data_inicial = null;
            $group_collect_plan_inicial = null;
            $group_collect_plan_ideal = collect();
            $group_collect_plan_especial = collect();

            foreach ($collect_final as $planDetails) {
                $layout = $planDetails['layout'] ?? IndividualQuotePdfLayout::resolve((int) $planDetails['plan']);
                $data = collect($planDetails['data'] ?? []);

                if ($layout === IndividualQuotePdfLayout::Inicial) {
                    $group_collect_plan_inicial = $data;
                    $data_inicial = (array) ($data->first() ?? []);
                } elseif ($layout === IndividualQuotePdfLayout::Especial) {
                    $group_collect_plan_especial = $data->groupBy('age_range');
                } else {
                    $group_collect_plan_ideal = $group_collect_plan_ideal->merge(
                        $data->groupBy('age_range')
                    );
                }
            }

            if ($group_collect_plan_ideal instanceof Collection && $group_collect_plan_ideal->isEmpty()) {
                $data_ideal = null;
            } else {
                $data_ideal = $group_collect_plan_ideal;
            }

            if ($group_collect_plan_especial instanceof Collection && $group_collect_plan_especial->isEmpty()) {
                $data_especial = null;
            } else {
                $data_especial = $group_collect_plan_especial;
            }

            $name_user = $details_generals['agent_name'] ?? $user->name;
            $pdf = Pdf::loadView('documents.propuesta-economica-multiple', compact('data_inicial', 'data_ideal', 'data_especial', 'details_generals', 'name_user'));
            $name_pdf = $details_generals['code'].'.pdf';
            $pdf->save(public_path('storage/quotes/'.$name_pdf));

            Notification::make()
                ->title('¡TAREA COMPLETADA!')
                ->body('📎 '.$details_generals['code'].'.pdf ya se encuentra disponible para su descarga.')
                ->success()
                ->actions([
                    Action::make('download')
                        ->label('Descargar archivo')
                        ->url('/storage/quotes/'.$details_generals['code'].'.pdf'),
                ])
                ->sendToDatabase($user);

        } catch (\Throwable $th) {

            Log::info('generatePdfMultiple: FAILED');
            Log::error($th->getMessage());

            Notification::make()
                ->title('¡TAREA NO COMPLETADA!')
                ->body('Hubo un error en la creación de la propuesta economica. Por favor, contacte con el administrador del Sistema.')
                ->danger()
                ->sendToDatabase($user);
        }
    }
}
