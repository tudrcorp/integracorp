<?php

namespace App\Http\Controllers;

use App\Models\User;
use Filament\Actions\Action;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

class CorporateQuoteController extends Controller
{
    public static function generatePdfPlanIncial($details, $user)
    {
        try {

            // ✅ Reconstruye el usuario dentro del job
            $user = User::findOrFail($user);

            $collect = collect($details['data'][0]);

            ini_set('memory_limit', '2048M');
            set_time_limit(120);


            $name_user = $user->name;
            $pdf = Pdf::loadView('documents.propuesta-economica', compact('details', 'collect', 'name_user'));
            $name_pdf = $details['code'] . '.pdf';
            $pdf->save(public_path('storage/quotes/' . $name_pdf));

            Notification::make()
                ->title('¡TAREA COMPLETADA!')
                ->body('📎 ' . $details['code'] . '.pdf ya se encuentra disponible para su descarga.')
                ->success()
                ->actions([
                    Action::make('download')
                        ->label('Descargar archivo')
                        ->url('/storage/quotes/' . $details['code'] . '.pdf')
                ])
                ->sendToDatabase($user);
        } catch (\Throwable $th) {

            Log::info("generatePdfPlanIncial: FAILED");
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

            // ✅ Reconstruye el usuario dentro del job
            $user = User::findOrFail($user);

            $collect = collect($details['data']);
            $group_collect = $collect->groupBy('age_range');

            ini_set('memory_limit', '2048M');
            set_time_limit(120);

            $name_user = $user->name;
            $pdf = Pdf::loadView('documents.propuesta-economica', compact('details', 'group_collect', 'name_user'));
            $name_pdf = $details['code'] . '.pdf';
            $pdf->save(public_path('storage/quotes/' . $name_pdf));

            Notification::make()
                ->title('¡TAREA COMPLETADA!')
                ->body('📎 ' . $details['code'] . '.pdf ya se encuentra disponible para su descarga.')
                ->success()
                ->actions([
                    Action::make('download')
                        ->label('Descargar archivo')
                        ->url('/storage/quotes/' . $details['code'] . '.pdf')
                ])
                ->sendToDatabase($user);
        } catch (\Throwable $th) {

            Log::info("generatePdfPlanIdeal: FAILED");
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

            // ✅ Reconstruye el usuario dentro del job
            $user = User::findOrFail($user);

            $collect = collect($details['data']);
            $group_collect = $collect->groupBy('age_range');

            ini_set('memory_limit', '2048M');
            set_time_limit(120);

            /**
             * Logica para generar el pdf
             * ----------------------------------------------------------------------------------------------------
             */
            $name_user = $user->name;
            $pdf = Pdf::loadView('documents.propuesta-economica', compact('details', 'group_collect', 'name_user'));
            $name_pdf = $details['code'] . '.pdf';
            $pdf->save(public_path('storage/quotes/' . $name_pdf));

            Notification::make()
                ->title('¡TAREA COMPLETADA!')
                ->body('📎 ' . $details['code'] . '.pdf ya se encuentra disponible para su descarga.')
                ->success()
                ->actions([
                    Action::make('download')
                        ->label('Descargar archivo')
                        ->url('/storage/quotes/' . $details['code'] . '.pdf')
                ])
                ->sendToDatabase($user);


        } catch (\Throwable $th) {

            Log::info("generatePdfPlanEspecial: FAILED");
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

            // ✅ Reconstruye el usuario dentro del job
            $user = User::findOrFail($user);

            $details_generals = [];
            for ($i = 0; $i < count($collect_final); $i++) {
                $details_generals = [
                    'code' => $collect_final[$i]['code'],
                    'name' => $collect_final[$i]['name'],
                    'email' => $collect_final[$i]['email'],
                    'phone' => $collect_final[$i]['phone'],
                    'date' => $collect_final[$i]['date'],
                ];
                break;
            }

            ini_set('memory_limit', '2048M');
            set_time_limit(120);

            /**
             * Datos de la propuesta economica
             */
            $data_inicial = null;
            $group_collect_plan_inicial = null;
            $group_collect_plan_ideal = null;
            $group_collect_plan_especial = null;

            for ($i = 0; $i < count($collect_final); $i++) {
                if ($collect_final[$i]['plan'] == 1 && !empty($collect_final[$i]['data'])) {
                    $collect_plan_inicial = collect($collect_final[$i]['data']);
                    $group_collect_plan_inicial = $collect_plan_inicial;
                }
                if ($collect_final[$i]['plan'] == 2 && !empty($collect_final[$i]['data'])) {
                    $collect_plan_ideal = collect($collect_final[$i]['data']);
                    $group_collect_plan_ideal = $collect_plan_ideal->groupBy('age_range');
                }
                if ($collect_final[$i]['plan'] == 3 && !empty($collect_final[$i]['data'])) {
                    $collect_plan_especial = collect($collect_final[$i]['data']);
                    $group_collect_plan_especial = $collect_plan_especial->groupBy('age_range');
                }
            }

            if (!empty($group_collect_plan_inicial)) {
                $data_inicial   =  (array) $group_collect_plan_inicial[0];
                $data_ideal     = $group_collect_plan_ideal;
                $data_especial  = $group_collect_plan_especial;
            } else {
                $data_ideal     = $group_collect_plan_ideal;
                $data_especial  = $group_collect_plan_especial;
            }


            $name_user = $user->name;
            $pdf = Pdf::loadView('documents.propuesta-economica-multiple', compact('data_inicial', 'data_ideal', 'data_especial', 'details_generals', 'name_user'));
            $name_pdf = $details_generals['code'] . '.pdf';
            $pdf->save(public_path('storage/quotes/' . $name_pdf));

            Notification::make()
                ->title('¡TAREA COMPLETADA!')
                ->body('📎 ' . $details_generals['code'] . '.pdf ya se encuentra disponible para su descarga.')
                ->success()
                ->actions([
                    Action::make('download')
                        ->label('Descargar archivo')
                        ->url('/storage/quotes/' . $details_generals['code'] . '.pdf')
                ])
                ->sendToDatabase($user);

                
        } catch (\Throwable $th) {

            Log::info("generatePdfMultiple: FAILED");
            Log::error($th->getMessage());

            Notification::make()
                ->title('¡TAREA NO COMPLETADA!')
                ->body('Hubo un error en la creación de la propuesta economica. Por favor, contacte con el administrador del Sistema.')
                ->danger()
                ->sendToDatabase($user);
        }
    }
}