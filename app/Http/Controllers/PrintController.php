<?php

namespace App\Http\Controllers;

use App\Services\DropboxService;
use App\Services\ErrorLoggerService;
use App\Services\ManifiestoService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

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

        $etiquetas = ((int)$tipo === 1 && !empty($data->etiquetas)) ? $data->etiquetas : [$data];

        foreach ($etiquetas as $etiqueta) {
            try {
                if ((int)$tipo === 1) {
                    $command = 'python python/label/' . $tamanio . '/sku_description.py ' .
                        escapeshellarg($etiqueta->codigo) . ' ' .
                        escapeshellarg($etiqueta->descripcion) . ' ' .
                        escapeshellarg($etiqueta->cantidad) . ' ' .
                        escapeshellarg($etiqueta->extra ?? '') . ' 2>&1';

                    $output = trim(shell_exec($command));
                } else {
                    $output = $etiqueta->archivo;
                }

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
        return response()->json([
            'Respuesta' => 'Impresion Correcta'
        ]);
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
    public function etiquetasData(): JsonResponse
    {
        $impresoras = DB::table('impresora')
            ->where('status', 1)
            ->get()
            ->toArray();

        $empresas = DB::table('empresa')
            ->select('empresa', 'id')
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

        return response()->json([
            'Respuesta' => 'Impresion Correcta', $data, $impresora, $modelo, $etiquetas
        ]);
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

        if (!isset($data->codigo, $data->descripcion)) {
            return response()->json([
                'code' => 400,
                'message' => 'Faltan datos requeridos: codigo o descripcion.'
            ]);
        }

        $impresora = DB::table('impresora')->where('id', 5)->first();
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

        if ($archivos->count() > 0) {
            $dropboxService = new DropboxService();
            foreach ($archivos as $archivo) {
                $content = $dropboxService->downloadFile($archivo->dropbox);

                if (empty($content)) {
                    return response()->json([
                        'code' => 500,
                        'message' => 'Error al obtener archivo: ' . $archivo->nombre,
                        'error' => 'Contenido vacío o nulo',
                        'archivo' => $archivo
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
            $url = "https://rest.afainnova.com/logistica/envio/pendiente/documento/$documentoId/$documento->id_marketplace_area/1?token=" . $request->get('token');

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
                $zoom = 1;
                // Recorte por izquierda para MLG
                if ($marketplace->marketplace === 'MLG') {
                    $zoom = 0.4;
                }
                // Divide en 3 bloques horizontales para ELEKTRA
//                if ($marketplace->marketplace === 'ELEKTRA') {
//                    $zoom = 3;
//                }

                $pythonScript = $extension === 'pdf' ? 'pdf_to_thermal.py' : 'image_to_thermal.py';
                $command = 'python3 python/afa/' . $pythonScript . ' ' .
                    escapeshellarg($nombreArchivo) . ' ' .
                    escapeshellarg($zoom) . ' ' .
                    escapeshellarg($ipImpresora) . ' 2>&1';


                $zplContent = trim(shell_exec($command));

                if (empty($zplContent) || !str_contains($zplContent, '^XA')) {
                    return response()->json([
                        'code' => 500,
                        'message' => 'No se generó correctamente la cadena ZPL.',
                        'output' => $zplContent,
                        'command' => $command
                    ]);
                }

            } else {
                try {
                    $socket = fsockopen($ipImpresora, 9100, $errno, $errstr, 5);
                    if (!$socket) {
                        throw new Exception("No se pudo conectar a la impresora: $errstr ($errno)");
                    }

                    fwrite($socket, $contenido);
                    fclose($socket);
                } catch (Exception $e) {
                    ErrorLoggerService::logger(
                        'Error en etiquetas. Impresora: ' . $ipImpresora,
                        'PrintController',
                        ['exception' => $e->getMessage(), 'line' => self::logLocation()]
                    );
                    $outputs[] = 'exception: ' . $e->getMessage() . ' line: ' . self::logLocation();
                    return response()->json([
                        'code' => 200,
                        'message' => 'NO. ' . $ipImpresora,
                        'outputs' => $outputs,
                    ]);
                }
            }

            $outputs[] = $nombreArchivo;

            if (file_exists($nombreArchivo)) unlink($nombreArchivo);
        }

        return response()->json([
            'code' => 200,
            'message' => 'Guías enviadas a impresión. ' . $ipImpresora,
            'outputs' => $outputs,
        ]);
    }

    public function manifiestoSalida(Request $request): JsonResponse
    {
        $raw = $request->input('data');
        $data = json_decode($raw);

        $impresion_reimpresion = $request->input('impresion_reimpresion');

        $guias_paqueteria = [];
        $guias = [];

        $impresora_data = DB::table("impresora")
            ->where("ip", $data->printer)
            ->first();

        if (in_array((string)$impresion_reimpresion, ['1', '2'], true)) {
            $impreso = ((string)$impresion_reimpresion === '1') ? 0 : 1;
            $guias = DB::table('manifiesto')
                ->join('paqueteria', 'manifiesto.id_paqueteria', '=', 'paqueteria.id')
                ->select('manifiesto.id', 'manifiesto.guia', 'paqueteria.paqueteria')
                ->where('manifiesto.manifiesto', date('dmY'))
                ->where('manifiesto.salida', 1)
                ->where('manifiesto.impreso', $impreso)
                ->where('manifiesto.id_impresora', $impresora_data->id)
                ->get();
        }

        foreach ($guias as $guia) {
            if (strtolower($guia->paqueteria) === strtolower($data->shipping_provider)) {
                $guias_paqueteria[] = $guia->guia;
            }
        }

        if (empty($guias_paqueteria)) {
            return response()->json([
                "code" => 404,
                "message" => "No se encontrarón guias de la paquetería" . " " . self::logVariableLocation()
            ]);
        }
        try {
            $reimpresion = ((string)$impresion_reimpresion === '2');

            ManifiestoService::impresion(
                $impresora_data->ip,
                (string)$data->shipping_provider,
                $guias_paqueteria,
                $reimpresion
            );

            if ((string)$impresion_reimpresion === '1') {
                foreach ($guias_paqueteria as $guia) {
                    DB::table('manifiesto')->where(['guia' => $guia])->update(['impreso' => 1]);
                }
            }

            return response()->json([
                "code" => 200,
                "message" => ((string)$impresion_reimpresion === '2') ? 'Reimpresión' : 'Impresión',
            ]);

        } catch (Throwable $e) {
            $errorMsg = $e->getMessage();

            ErrorLoggerService::logger(
                'Error en manifiesto. Impresora: ' . $impresora_data->ip,
                'PrintController',
                ['exception' => $errorMsg, 'line' => self::logLocation()]
            );

            if (
                str_contains($errorMsg, 'No route to host') ||
                str_contains($errorMsg, 'Transport endpoint is not connected') ||
                str_contains($errorMsg, 'Bad file descriptor') ||
                str_contains($errorMsg, 'Failed to write')
            ) {
                if ((string)$impresion_reimpresion === '1') {
                    foreach ($guias_paqueteria as $guia) {
                        DB::table('manifiesto')->where(['guia' => $guia])->update(['impreso' => 1]);
                    }
                }
            }

            return response()->json([
                "code" => 500,
                "message" => "No fue posible imprimir el manifiesto de la paquetería $data->shipping_provider.",
                "error" => $errorMsg,
            ], 500);
        }
    }

    private static function logVariableLocation(): string
    {
        $sis = 'BE'; //Front o Back
        $ini = 'PC'; //Primera letra del Controlador y Letra de la seguna Palabra: Controller, service
        $fin = 'INT'; //Últimas 3 letras del primer nombre del archivo *comPRAcontroller
        $trace = debug_backtrace()[0];
        return ('<br>' . $sis . $ini . $trace['line'] . $fin);
    }
}
