<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Descargar Código QR</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Lucide Icons -->
    <script src="https://www.google.com/search?q=https://unpkg.com/lucide%40latest"></script>
    <!-- html2canvas para capturar el HTML y convertirlo a imagen -->
    <script src="https://www.google.com/search?q=https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <!-- jsPDF para generar PDFs -->
    <script src="https://www.google.com/search?q=https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <style>
        .download-button {
            @apply inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm transition duration-150 ease-in-out;
        }

        .download-button-primary {
            @apply bg-indigo-600 text-white hover: bg-indigo-700;
        }

        .download-button-secondary {
            @apply bg-white text-indigo-700 border-indigo-600 hover: bg-indigo-50;
        }

        .download-button svg {
            @apply -ml-1 mr-3 h-5 w-5;
        }

    </style>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col items-center justify-center p-8 font-sans">

    <div class="bg-white p-10 rounded-xl shadow-2xl text-center max-w-lg w-full">
        <h1 class="text-3xl font-bold text-gray-800 mb-6 border-b pb-4">
            Descarga tu Código QR
        </h1>

        <p class="text-gray-600 mb-8">
            Este código QR enlaza a: <strong class="text-indigo-600">{{ $url }}</strong>
        </p>

        <!-- Contenedor del QR que vamos a capturar -->
        <div id="qr-code-container" class="flex justify-center items-center p-4 border border-gray-200 rounded-lg bg-white mb-8">
            {!! SimpleSoftwareIO\QrCode\Facades\QrCode::size(300)->generate($url) !!}
        </div>

        <div class="flex flex-col sm:flex-row justify-center space-y-4 sm:space-y-0 sm:space-x-4">
            <!-- Botón para descargar como PNG --><button id="download-png-btn" class="download-button download-button-primary">
                <i data-lucide="image"></i>
                Descargar como PNG
            </button>

            <!-- Botón para descargar como PDF --><button id="download-pdf-btn" class="download-button download-button-secondary">
                <i data-lucide="file-text"></i>
                Descargar como PDF
            </button>
        </div>

        <div class="mt-8 text-sm text-gray-500">
            <p>Generado por Integracorp. © {{ date('Y') }}.<br> Todos los derechos reservados.</p>
        </div>
    </div>

    <script>
        // Inicializar iconos de Lucide
        lucide.createIcons();

        // Obtener el contenedor del QR
        const qrCodeContainer = document.getElementById('qr-code-container');
        const downloadPngBtn = document.getElementById('download-png-btn');
        const downloadPdfBtn = document.getElementById('download-pdf-btn');
        const url = "{{ $url }}"; // Pasar la URL para el nombre del archivo

        // Función para descargar como PNG
        downloadPngBtn.addEventListener('click', () => {
            // Capturar el contenido del contenedor del QR
            html2canvas(qrCodeContainer, {
                scale: 2 // Escala para mejor resolución del PNG
            }).then(canvas => {
                // Crear un enlace temporal
                const link = document.createElement('a');
                link.download = `qr_${new URL(url).hostname}.png`; // Nombre de archivo dinámico
                link.href = canvas.toDataURL('image/png'); // Convertir canvas a URL de datos PNG
                link.click(); // Simular clic para descargar
            });
        });

        // Función para descargar como PDF
        downloadPdfBtn.addEventListener('click', () => {
            // Capturar el contenido del contenedor del QR
            html2canvas(qrCodeContainer, {
                scale: 2 // Escala para mejor resolución en PDF
            }).then(canvas => {
                const {
                    jsPDF
                } = window.jspdf; // Obtener jsPDF del window
                const doc = new jsPDF();
                const imgData = canvas.toDataURL('image/png');

                // Calcular las dimensiones para que el QR quepa bien en el PDF
                const imgWidth = 100; // Ancho deseado del QR en el PDF (mm)
                const pageHeight = doc.internal.pageSize.height;
                const pageWidth = doc.internal.pageSize.width;
                const imgHeight = canvas.height * imgWidth / canvas.width;

                // Centrar la imagen en la página
                const x = (pageWidth - imgWidth) / 2;
                const y = (pageHeight - imgHeight) / 2;

                doc.addImage(imgData, 'PNG', x, y, imgWidth, imgHeight);
                doc.save(`qr_${new URL(url).hostname}.pdf`); // Nombre de archivo dinámico
            });
        });

    </script>


</body>
</html>
