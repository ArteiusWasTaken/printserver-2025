<?php

namespace App\Http\Controllers;

use App\Services\DropboxService;
use App\Services\ErrorLoggerService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\Printer;

/**
 *
 */
class PrintController extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function etiquetas(Request $request): JsonResponse
    {
        $output = '';
        $tipo = $request->input('tipo');
        $data = json_decode($request->input('data'));

        $impresora = DB::table('impresora')->where('id', $data->impresora)->first();
        if (!$impresora) {
            return response()->json([
                'code' => 500,
                'message' => 'No se encontró la impresora proporcionada ' . self::logLocation()
            ]);
        }

        $ip = $impresora->ip;
        $tamanio = $impresora->tamanio;
        $port = 9100;

        $etiquetas = ($tipo == '1' && !empty($data->etiquetas)) ? $data->etiquetas : [$data];

        foreach ($etiquetas as $etiqueta) {
            try {
                $command = 'python python/label/' . $tamanio . '/sku_description.py ' .
                    escapeshellarg($etiqueta->codigo) . ' ' .
                    escapeshellarg($etiqueta->descripcion) . ' ' .
                    escapeshellarg($etiqueta->cantidad) . ' ' .
                    escapeshellarg($etiqueta->extra ?? '') . ' 2>&1';

                $output = trim(shell_exec($command));

                $socket = fsockopen($ip, $port, $errno, $errstr, 5);
                if (!$socket) {
                    throw new Exception("No se pudo conectar a la impresora: $errstr ($errno)");
                }

                fwrite($socket, $output);
                fclose($socket);

            } catch (Exception $e) {
                ErrorLoggerService::logger(
                    'Error en etiquetas. Impresora: ' . $ip,
                    'PrintController',
                    [
                        'exception' => $e->getMessage(),
                        'line' => self::logLocation()
                    ]
                );
                return response()->json([
                    'Error' => 'No se pudo imprimir: ' . $e->getMessage()
                ], 500);
            }
        }
        return response()->json([$output]);
    }

    /**
     * @return string
     */
    private static function logLocation(): string
    {
        $sis = 'BE'; // Front o Back
        $ini = 'PC'; // Primera letra del Controlador y Letra de la seguna Palabra: Controller, service
        $fin = 'INT'; // Últimas 3 letras del primer nombre del archivo *comPRAcontroller
        $trace = debug_backtrace()[0];
        return ('<br>' . $sis . $ini . $trace['line'] . $fin);
    }

    /**
     * @return JsonResponse
     * @noinspection PhpUnused
     */
    public function tickets(): JsonResponse
    {
        try {
            $ip = '192.168.15.73';
            $port = 9100;

            $socket = fsockopen($ip, $port, $errno, $errstr, 5);
            if (!$socket) {
                throw new Exception("No se pudo conectar a la impresora: $errstr ($errno)");
            }

            $commands = '';

            // Inicialización básica para GHIA
            $commands .= chr(27) . '@'; // Reset printer
            $commands .= chr(27) . 'R' . chr(0); // Set internacional character set USA
            $commands .= chr(27) . 't' . chr(0); // Codificación UTF-8

            // Encabezado del ticket
            $commands .= chr(27) . 'a' . chr(1); // Centrar texto
            $commands .= "MI EMPRESA\n";
            $commands .= "DIRECCION\n";
            $commands .= "TEL: 123-456-7890\n";
            $commands .= "-----------------------\n";

            // Detalles del ticket
            $commands .= chr(27) . 'a' . chr(0); // Alinear izquierda
            $commands .= 'Fecha: ' . date('d/m/Y H:i:s') . "\n";
            $commands .= "Ticket #: 12345\n";
            $commands .= "-----------------------\n";
            $commands .= "PRODUCTO       CANT  TOTAL\n";
            $commands .= "-----------------------\n";
            $commands .= "Producto 1     1    $10.00\n";
            $commands .= "Producto 2     2    $20.00\n";
            $commands .= "-----------------------\n";
            $commands .= "TOTAL:        $30.00\n";
            $commands .= "-----------------------\n\n";

            // CÓDIGO DE BARRAS CODE128 PARA GHIA GTP-801
            $barcodeData = 'ABC123456789'; // Datos alfanuméricos

            // Configuración específica para GHIA:
            $commands .= chr(29) . 'h' . chr(100); // Altura (1-255 dots)
            $commands .= chr(29) . 'w' . chr(2);   // Ancho (1-6, 2 es estándar)
            $commands .= chr(29) . 'f' . chr(0);   // Fuente del texto (0=A, 1=B)
            $commands .= chr(29) . 'H' . chr(2);   // Posición del texto (2=debajo)

            // Comando CODE128 modificado para GHIA:
            $len = strlen($barcodeData);
            $commands .= chr(29) . 'k' . chr(73) . chr($len) . $barcodeData;

            /*
            Estructura especial para GHIA GTP-801:
            1D 6B 49 [n] [data]
            Donde:
            - 1D 6B: Inicio código de barras
            - 49: Selecciona CODE128 (73 en decimal)
            - [n]: Longitud de los datos (1 byte)
            - [data]: Los datos del código
            */

            $commands .= "\n\n\n\n"; // Espacios después del código

            // Pie del ticket
            $commands .= chr(27) . 'a' . chr(1); // Centrar
            $commands .= "Gracias por su compra\n";
            $commands .= chr(27) . 'a' . chr(0); // Alinear izquierda
            $commands .= "-----------------------\n";

            // Corte de papel para GHIA
            $commands .= chr(29) . 'V' . chr(65) . chr(0); // Corte parcial
            // Alternativa: $commands .= chr(29)."V".chr(66).chr(0); // Corte completo

            // Enviar comandos
            fwrite($socket, $commands);
            fclose($socket);

            return response()->json([
                'success' => true,
                'message' => 'Ticket impreso',
                'hex_sent' => bin2hex($commands) // Para depuración
            ]);

            // CÓDIGO DE BARRAS (Versión funcional para TM-T88V)
            /* EAN 13 funciona
            $barcodeData = "123456789012"; // 12 dígitos para EAN-13

            // Configuración del código de barras
            $commands .= chr(29)."h".chr(100); // Altura (dots) - valor entre 1-255
            $commands .= chr(29)."w".chr(3);   // Ancho (1-6) - 3 es un buen valor medio
            $commands .= chr(29)."H".chr(2);   // Posición del texto: 2 (debajo del barcode)
            $commands .= chr(29)."k".chr(4).$barcodeData.chr(0); // EAN-13 (código 4)
            */

        } catch (Exception $exception) {
            return response()->json([
                'success' => false,
                'error' => 'No se pudo imprimir: ' . $exception->getMessage()
            ], 500);
        }
    }

    /**
     * @param $barcode
     * @return JsonResponse
     * @noinspection PhpUnused
     */
    public function ticketsUsb($barcode): JsonResponse
    {
        try {
            // 1. Configurar conector - elige una opción:

            // a) Para impresora USB directa (Linux)
            $connector = new FilePrintConnector('/dev/usb/lp0');

            // b) Para impresora de red
            // $connector = new NetworkPrintConnector("192.168.1.100", 9100);

            // 2. Crear instancia de impresora
            $printer = new Printer($connector);

            // 3. Configuración inicial
            $printer->initialize();
            $printer->setJustification(Printer::JUSTIFY_CENTER);

            // 4. Encabezado del ticket
            $printer->text("MI EMPRESA\n");
            $printer->text("DIRECCION\n");
            $printer->text("TEL: 123-456-7890\n");
            $printer->text("----------------\n");

            // 5. Detalles del ticket
            // $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text('Fecha: ' . date('d/m/Y H:i:s') . "\n");
            $printer->text("Ticket #: 12345\n");
            $printer->text("----------------\n");

            // 6. Configurar código de barras (sin constantes)

            // Calcular ancho dinámico según la longitud del código
            $length = strlen($barcode);

            // Establecer un ancho base (entre 1 y 6, recomendado por la mayoría de impresoras)
            $width = match (true) {
                $length >= 16 => 1,
                $length >= 10 => 2,
                $length >= 7 => 3,
                $length >= 5 => 4,
                $length == 4 => 5,
                default => 6,
            };

            // Configurar CODE39 (69 es el tipo numérico para CODE39)
            $printer->setBarcodeHeight(65);
            $printer->setBarcodeWidth($width);
            $printer->setBarcodeTextPosition(2);

            $printer->barcode($barcode, 69);

            // 7. Pie del ticket
            $printer->feed(2);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text("Gracias por su compra\n");

            // 8. Cortar papel (formato Epson)
            $printer->cut(Printer::CUT_PARTIAL);

            // 9. Cerrar conexión
            $printer->close();

            return response()->json(['success' => true, 'message' => 'Ticket impreso', 'tamanio' => $width]);

        } catch (Exception $exception) {
            return response()->json([
                'success' => false,
                'error' => 'No se pudo imprimir: ' . $exception->getMessage()
            ], 500);
        }
    }

    /**
     * @return JsonResponse
     * @noinspection PhpUnused
     */
    public function etiquetasData(): JsonResponse
    {
        $impresoras = DB::table('impresora')
            ->where('status', 1)
            ->get()
            ->toArray();

        $empresas = DB::table('empresa')
            ->select('empresa', 'bd')
            ->where('id', '<>', '')
            ->get()
            ->toArray();

        return response()->json([
            'code' => 200,
            'impresoras' => $impresoras,
            'empresas' => $empresas
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function etiquetasSerie(Request $request): JsonResponse
    {
        $output = '';
        $data = json_decode($request->input('data'));
        $etiquetas = [];
        $cantidad = (int)explode('.', $data->cantidad)[0];

        $impresora = DB::table('impresora')->where('id', $data->impresora)->first();
        if (!$impresora) {
            return response()->json([
                'code' => 500,
                'message' => 'No se encontró la impresora proporcionada ' . self::logLocation()
            ]);
        }

        $modelo = DB::table('modelo')
            ->select('id', 'consecutivo', 'descripcion')
            ->where('sku', $data->codigo)
            ->first();

        if (!$modelo) {
            $modelo = DB::table('modelo_sinonimo')
                ->join('modelo', 'modelo_sinonimo.id_modelo', '=', 'modelo.id')
                ->select('modelo.id', 'modelo.consecutivo', 'modelo.descripcion')
                ->where('modelo_sinonimo.codigo', trim($data->codigo))
                ->first();

            if (!$modelo) {
                return response()->json([
                    'code' => 500,
                    'message' => 'El código proporcionado no existe en la base de datos, contactar a un administrador '
                        . self::logLocation()
                ]);
            }
        }

        $fecha = date('mY');
        $prefijo = str_pad(substr($modelo->id, -5), 5, '0', STR_PAD_LEFT);
        $consecutivo_base = (int)$modelo->consecutivo;

        for ($i = 0; $i < $cantidad; $i++) {
            $consecutivo = $consecutivo_base + $i + 1;
            $sufijo = str_pad($consecutivo, 6, '0', STR_PAD_LEFT);

            $etiquetas[] = (object)[
                'serie' => $prefijo . $fecha . $sufijo,
                'codigo' => $data->codigo,
                'descripcion' => $modelo->descripcion,
                'cantidad' => 1,
                'extra' => property_exists($data, 'extra') ? $data->extra : ''
            ];
        }

        $nuevo_consecutivo = ($consecutivo_base + $cantidad >= 800000) ? 1 : ($consecutivo_base + $cantidad);
        DB::table('modelo')->where('id', $modelo->id)->update(['consecutivo' => $nuevo_consecutivo]);

        if (!$this->imprimirEtiqueta($impresora, $etiquetas)) {
            return response()->json([
                'code' => 500,
                'message' => 'Ocurrió un error al imprimir una o más etiquetas.'
            ]);
        }

        return response()->json([$output]);
    }

    private function imprimirEtiqueta($impresora, $etiquetas): bool|string
    {
        foreach ($etiquetas as $etiqueta) {
            try {
                $command = 'python python/label/' . $impresora->tamanio . '/sku_description_serie.py ' .
                    escapeshellarg($etiqueta->codigo) . ' ' .
                    escapeshellarg($etiqueta->descripcion) . ' ' .
                    escapeshellarg($etiqueta->serie) . ' ' .
                    escapeshellarg($etiqueta->cantidad) . ' ' .
                    escapeshellarg($etiqueta->extra) . ' 2>&1';

                $output = trim(shell_exec($command));

                $socket = fsockopen($impresora->ip, 9100, $errno, $errstr, 5);
                if (!$socket) {
                    throw new Exception("No se pudo conectar a la impresora: $errstr ($errno)");
                }

                fwrite($socket, $output);
                fclose($socket);
            } catch (Exception $e) {
                ErrorLoggerService::logger(
                    'Error en etiquetas. Impresora: ' . $impresora->ip,
                    'PrintController',
                    ['exception' => $e->getMessage(), 'line' => self::logLocation()]
                );
                return false;
            }
        }
        return true;
    }

    public function imprimirBusqueda(Request $request): JsonResponse
    {
        $data = json_decode($request->input('data'));

        if (!isset($data->serie, $data->codigo, $data->descripcion)) {
            return response()->json([
                'code' => 400,
                'message' => 'Faltan datos requeridos: serie, codigo o descripcion.'
            ]);
        }

        $impresora = DB::table('impresora')->where('id', 1)->first();
        if (!$impresora) {
            return response()->json([
                'code' => 500,
                'message' => 'No se encontró la impresora configurada.'
            ]);
        }

        $etiqueta = (object)[
            'serie' => $data->serie,
            'codigo' => $data->codigo,
            'descripcion' => $data->descripcion,
            'cantidad' => 1,
            'extra' => property_exists($data, 'extra') ? $data->extra : ''
        ];

        $impresion = $this->imprimirEtiqueta($impresora, [$etiqueta]);

        if (!$impresion) {
            return response()->json([
                'code' => 500,
                'message' => 'No se pudo imprimir la etiqueta.'
            ]);
        }

        return response()->json([
            'code' => 200,
            'message' => 'Etiqueta impresa correctamente',
            'serie' => $etiqueta->serie
        ]);
    }

    public function print($documentoId, $impresoraNombre, Request $request): JsonResponse
    {
        $documento = DB::table('documento')
            ->join('paqueteria', 'documento.id_paqueteria', '=', 'paqueteria.id')
            ->where('documento.id', $documentoId)
            ->where('documento.status', 1)
            ->select('documento.*', 'paqueteria.paqueteria', 'paqueteria.guia')
            ->first();

        if (!$documento) {
            return response()->json([
                'code' => 500,
                'message' => 'Documento no encontrado: ' . self::logLocation()
            ]);
        }

        $marketplace = DB::table('marketplace_area')
            ->join('documento', 'documento.id_marketplace_area', '=', 'marketplace_area.id')
            ->join('marketplace', 'marketplace_area.id_marketplace', '=', 'marketplace.id')
            ->leftJoin('marketplace_api', 'marketplace_area.id', '=', 'marketplace_api.id_marketplace_area')
            ->where('documento.id', $documentoId)
            ->select('marketplace.*', 'marketplace_api.guia')
            ->first();

        if (!$marketplace) {
            return response()->json([
                'code' => 500,
                'message' => 'No se encontró el marketplace del documento: ' . self::logLocation()
            ]);
        }

        $archivos = DB::table('documento_archivo')
            ->where('id_documento', $documentoId)
            ->where('status', 1)
            ->where('tipo', 2)
            ->get();

        $archivosImpresion = [];
        $extension = 'pdf';

        if (!empty($archivos)) {
            $dropboxService = new DropboxService();
            foreach ($archivos as $archivo) {
                $content = $dropboxService->downloadFile($archivo->dropbox);

                if (empty($content)) {
                    return response()->json([
                        'code' => 500,
                        'message' => 'Error al obtener archivo: ' . $archivo->nombre,
                        'error' => 'Contenido vacío o nulo',
                    ]);
                }

                if (!is_string($content)) {
                    return response()->json([
                        'code' => 500,
                        'message' => 'Contenido inválido para archivo: ' . $archivo->nombre,
                    ]);
                }

                $archivosImpresion[] = base64_encode($content);

                if (str_ends_with($archivo->nombre, '.zpl')) {
                    $extension = 'zpl';
                }
            }
        } elseif ($marketplace->guia) {
            $url = "https://rest.afainnova.com/logistica/envio/pendiente/documento/{$documentoId}/{$documento->id_marketplace_area}/1?token=" . $request->get('token');

            $response = json_decode(file_get_contents($url));

            if (!$response || $response->code !== 200) {
                return response()->json([
                    'code' => $response->code ?? 500,
                    'message' => $response->message ?? 'Error desconocido al obtener la guía',
                ]);
            }

            $archivosImpresion[] = $response->file;

            $extension = match ($marketplace->marketplace) {
                'MERCADOLIBRE' => 'zpl',
                default => 'pdf',
            };
        }

        if (empty($archivosImpresion)) {
            return response()->json([
                'code' => 500,
                'message' => 'No se encontraron archivos de guía para imprimir.',
            ]);
        }

        $impresora = DB::table('impresora')
            ->select('impresora.ip')
            ->where('impresora.id', $impresoraNombre)
            ->first();

        $ipImpresora = $impresora->ip;

        $outputs = [];
        foreach ($archivosImpresion as $contenido) {
            $nombreArchivo = "python/label/" . uniqid() . '.' . $extension;
            file_put_contents($nombreArchivo, base64_decode($contenido));
            chmod($nombreArchivo, 0777);

            if ($extension !== 'zpl' && $marketplace->marketplace !== 'MERCADOLIBRE') {
                $pythonScript = $extension === 'pdf' ? 'pdf_to_thermal.py' : 'image_to_thermal.py';
                $output = trim(shell_exec("python3 python/label/convert/{$pythonScript} '{$nombreArchivo}' '{$documento->zoom_guia}' 2>&1"));
                $archivoFinal = $output;
            } else {
                $archivoFinal = $nombreArchivo;
            }

//            $modo = ($extension === 'zpl' || $marketplace->marketplace === 'MERCADOLIBRE') ? '-o raw' : '';
//            exec("lp -d {$ipImpresora} -n 1 {$modo} {$archivoFinal}");
            $fp = fsockopen($ipImpresora, 9100, $errno, $errstr, 10);
            if (!$fp) {
                return response()->json([
                    'code' => 500,
                    'message' => "Error al conectar a la impresora: $errstr ($errno)"
                ]);
            }

            fwrite($fp, file_get_contents($archivoFinal));
            fclose($fp);
            $outputs[] = $archivoFinal;

            if (file_exists($archivoFinal)) unlink($archivoFinal);
            if (file_exists($nombreArchivo)) unlink($nombreArchivo);
        }

        return response()->json([
            'code' => 200,
            'message' => 'Guías enviadas a impresión.',
            'outputs' => $outputs,
        ]);
    }


}
