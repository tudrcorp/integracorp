<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TarjetaAfiliacionController extends Controller
{
    public static function generateTarjetaAfiliacion($data) {
        // dd($data);
        try {

            // 1. Validaciones preventivas de datos críticos
            if (empty($data['code'])) {
                throw new \Exception("No se proporcionó un código válido para generar la tarjeta.");
            }

            // 2. Configuración optimizada de recursos
            // Bajamos de 2048M a 512M para ser más eficientes con la RAM del servidor
            ini_set('memory_limit', '512M');
            set_time_limit(60);

            // 3. Gestión de directorios y nombres
            $name_pdf = 'TAR-' . $data['code'] . '.pdf';
            $directory = public_path('storage/tarjeta-afiliacion/');
            $fullPath = $directory . $name_pdf;

            // Aseguramos que la carpeta exista antes de guardar
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            // 4. Generación del PDF
            $pdf = Pdf::loadView('documents.tarjeta-afiliado', compact('data'));

            // Guardamos el archivo físicamente
            $pdf->save($fullPath);

            Log::info("Tarjeta de afiliación generada con éxito: {$name_pdf}");
            return true;
            
        } catch (\Throwable $th) {
            // 5. Manejo de errores profesional
            Log::error("Fallo al generar tarjeta de afiliación", [
                'error' => $th->getMessage(),
                'code'  => $data['code'] ?? 'N/A',
                'file'  => $th->getFile(),
                'line'  => $th->getLine()
            ]);

            // Enviar notificación de error al usuario si el sistema de notificaciones está disponible
            Notification::make()
                ->title('Error en proceso')
                ->body('No se pudo generar la tarjeta de afiliación. Se ha registrado el incidente técnico.')
                ->danger()
                ->send();

            return 'Error al generar la tarjeta de afiliación: ' . $th->getMessage();
        }
    }
}