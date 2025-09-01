<?php

namespace App\Services;

use DateTime;
use DateTimeZone;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\Printer;
use stdClass;

/**
 *
 */
class PickingService
{
    /**
     * @return JsonResponse
     */
    public function rawinfoPicking(): JsonResponse
    {
        $servidores = DB::table('documento')
            ->join('marketplace_area', 'documento.id_marketplace_area', '=', 'marketplace_area.id')
            ->join('empresa_almacen', 'documento.id_almacen_principal_empresa', '=', 'empresa_almacen.id')
            ->join('impresora', 'empresa_almacen.id_impresora_picking', '=', 'impresora.id')
            ->where('documento.id_fase', 3)
            ->where('documento.status', 1)
            ->where('documento.id_tipo', 2)
            ->where('documento.autorizado', 1)
            ->where('documento.problema', 0)
            ->where('documento.picking', 0)
            ->whereYear('documento.created_at', date('Y'))
            ->groupBy('impresora.servidor')
            ->pluck('impresora.servidor')
            ->toArray();

        if (!empty($servidores)) {
            foreach ($servidores as $servidor) {
                $ventas = DB::table('documento')
                    ->join('marketplace_area', 'documento.id_marketplace_area', '=', 'marketplace_area.id')
                    ->join('empresa_almacen', 'documento.id_almacen_principal_empresa', '=', 'empresa_almacen.id')
                    ->join('impresora', 'empresa_almacen.id_impresora_picking', '=', 'impresora.id')
                    ->where('documento.id_fase', 3)
                    ->where('documento.status', 1)
                    ->where('documento.id_tipo', 2)
                    ->where('documento.autorizado', 1)
                    ->where('documento.problema', 0)
                    ->where('documento.picking', 0)
                    ->where('documento.picking_by', 0)
                    ->whereYear('documento.created_at', date('Y'))
                    ->where('impresora.servidor', $servidor)
                    ->where(function ($query) {
                        $query->where('marketplace_area.publico', '!=', 0)
                            ->orWhere(function ($query) {
                                $query->where('documento.pagado', '!=', 0)
                                    ->orWhere('documento.id_periodo', '!=', 1);
                            });
                    })
                    ->select(
                        'documento.id',
                        'documento.pagado',
                        'documento.id_periodo',
                        'documento.id_marketplace_area',
                        'documento.documento_extra',
                        'marketplace_area.publico',
                        'impresora.ip'
                    )
                    ->groupBy('documento.id',
                        'documento.pagado',
                        'documento.id_periodo',
                        'documento.id_marketplace_area',
                        'documento.documento_extra',
                        'marketplace_area.publico',
                        'impresora.ip')
                    ->orderBy('documento.created_at')
                    ->limit(30)
                    ->get()
                    ->toArray();

                if (!empty($ventas)) {

                    foreach ($ventas as $index => $venta) {
                        $movimientos = DB::table('movimiento')->where('id_documento', $venta->id)->first();

                        if (empty($movimientos)) {
                            DB::table('seguimiento')->insert([
                                'id_documento' => $venta->id,
                                'id_usuario' => 1,
                                'seguimiento' => 'PICKING: El pedido ha sido mandado a fase PEDIDO
                                 debido a que actualmente no contiene productos.
                                Por favor, añada artículos para proceder con la siguiente fase del proceso.'
                            ]);

                            DB::table('documento')->where('id', $venta->id)->update([
                                'id_fase' => 1,
                            ]);

                            unset($ventas[$index]);

                            $tiene_series = DB::table('movimiento_producto')
                                ->join('movimiento', 'movimiento.id', '=', 'movimiento_producto.id_movimiento')
                                ->join('producto', 'producto.id', '=', 'movimiento_producto.id_producto')
                                ->where('movimiento.id_documento', $venta->id)
                                ->select('producto.*')
                                ->get();

                            if (empty($tiene_series)) {
                                self::eliminarSeries($venta->id);
                            }
                        }
                    }

                    if (empty($venta)) continue;

                    try {
                        self::picking($ventas);
                    } catch (Exception $e) {
                        ErrorLoggerService::logger(
                            'No fue posible imprimir los picking del servidor: ' . $servidor,
                            'PrintController',
                            [
                                'exception' => $e->getMessage(),
                                'line' => self::logLocation()
                            ]
                        );
                    }
                }
            }
        }
        return response()->json([
            'Respuesta' => 'Imprimir picking Finalizado',
            'Ventas' => $ventas ?? [],
            'Servidores' => $servidores ?? []
        ]);
    }

    /**
     * @param $documento
     * @return void
     */
    private static function eliminarSeries($documento): void
    {
        $info = DB::table('documento')->where('id', $documento)->first();

        if (!empty($info)) {
            if ($info->id_fase != 3) {
                DB::table('documento')->where('id', $documento)->update(['id_fase' => 3]);
            }
        }

        $movimientos = DB::table('movimiento')->where('id_documento', $documento)->get();

        if (!empty($movimientos)) {
            foreach ($movimientos as $movimiento) {
                $mov_produ = DB::table('movimiento_producto')->where('id_movimiento', $movimiento->id)->get();

                if (!empty($mov_produ)) {
                    foreach ($mov_produ as $mov) {
                        DB::table('producto')->where('id', $mov->id_producto)->update(['status' => 1]);

                        DB::table('movimiento_producto')->where('id', $mov->id)->delete();
                    }
                }
            }
        }
    }

    /**
     * @param $documentos
     * @return void
     */
    private static function picking($documentos): void
    {
        foreach ($documentos as $documento) {
            $seguimiento = [];

            $info = DB::table('documento')
                ->join('empresa_almacen', 'documento.id_almacen_principal_empresa', '=', 'empresa_almacen.id')
                ->join('empresa', 'empresa_almacen.id_empresa', '=', 'empresa.id')
                ->join('almacen', 'empresa_almacen.id_almacen', '=', 'almacen.id')
                ->join('marketplace_area', 'documento.id_marketplace_area', '=', 'marketplace_area.id')
                ->join('area', 'marketplace_area.id_area', '=', 'area.id')
                ->join('marketplace', 'marketplace_area.id_marketplace', '=', 'marketplace.id')
                ->where('documento.status', 1)
                ->where('documento.id_fase', 3)
                ->where('documento.picking', 0)
                ->where('documento.id', $documento->id)
                ->select(
                    'area.area',
                    'documento.id',
                    'documento.no_venta',
                    'documento.comentario',
                    'marketplace.marketplace',
                    'empresa.empresa',
                    'almacen.almacen'
                )
                ->first();
            if (empty($info)) {
                ErrorLoggerService::logger(
                    'No se encontro informacion del documento solicitado ' . $documento->id,
                    'PrintController',
                    [
                        'exception' => 'Errors',
                        'line' => self::logLocation()
                    ]
                );
                continue;
            }

            $productos = DB::table('movimiento')
                ->join('modelo', 'movimiento.id_modelo', '=', 'modelo.id')
                ->where('movimiento.id_documento', $documento->id)
                ->select(
                    'modelo.sku',
                    'modelo.descripcion',
                    'movimiento.cantidad'
                )
                ->get()
                ->toArray();

            if (empty($productos)) {
                ErrorLoggerService::logger(
                    'El documento solicitado ' . $documento->id . 'no contiene productos.',
                    'PrintController',
                    [
                        'exception' => 'Errors',
                        'line' => self::logLocation()
                    ]
                );

                DB::table('seguimiento')->insert([
                    'id_documento' => $documento->id,
                    'id_usuario' => 1,
                    'seguimiento' => 'Pedido mandado a fase pedido por falta de información en los productos.'
                ]);

                DB::table('documento')->where(['id' => $documento->id])->update([
                    'id_fase' => 1,
                ]);

                continue;
            }

            $seguimientos = DB::table('seguimiento')
                ->join('usuario', 'seguimiento.id_usuario', '=', 'usuario.id')
                ->where('seguimiento.id_documento', $documento->id)
                ->where('seguimiento.id_usuario', '!=', 1)
                ->select('seguimiento.*', 'usuario.nombre')
                ->orderBy('seguimiento.created_at', 'desc')
                ->limit(2)
                ->get()
                ->toArray();

            if (count($seguimientos) > 0) {
                foreach ($seguimientos as $seguimientoo) {
                    $seguimiento_data = new stdClass();
                    $re = '/\b(\w)\S*\s*/m';
                    $subst = '$1';
                    $seguimiento_data->usuario = preg_replace($re, $subst, $seguimientoo->nombre)
                        . ' (' . $seguimientoo->created_at . ')';
                    $seguimiento_data->seguimiento = strip_tags($seguimientoo->seguimiento);

                    $seguimiento[] = $seguimiento_data;
                }
            }

            try {
                $connector = new NetworkPrintConnector($documento->ip, 9100);
                $printer = new Printer($connector);

                $date = (new DateTime('now', new DateTimeZone('America/Mexico_City')))->format('Y-m-d H:i:s');

                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $printer->feed(2);
                $printer->setTextSize(2, 2);
                $printer->text($info->id . "\n");
                $printer->setTextSize(1, 1);
                $printer->barcode($info->id);
                $printer->feed();

                $printer->setJustification();
                $printer->text($info->area . ' / ' . $info->marketplace . "\n");
                $printer->text($info->empresa . ' / ' . $info->almacen . "\n\n");
                $printer->text('No. de venta / ' . $info->no_venta . "\n\n");
                if ($info->marketplace == 'MERCADOLIBRE') {
                    $printer->text('No. de pack / ' . $info->comentario . "\n\n");
                }

                $printer->text("Productos\n");
                $printer->text(str_repeat('-', 48) . "\n");

                foreach ($productos as $producto) {
                    $printer->text($producto->sku . "\n");
                    $printer->text($producto->descripcion . "\n");

                    $printer->setTextSize(2, 2);
                    $printer->text($producto->cantidad . "\n");
                    $printer->setTextSize(1, 1);

                    $printer->text(str_repeat('-', 48) . "\n");
                }

                $printer->feed(2);
                $printer->text("Último seguimiento\n");
                $printer->text(str_repeat('-', 48) . "\n");
                if (empty($seguimientos)) {
                    $printer->text('Sin Seguimientos' . "\n");
                    $printer->text(str_repeat('-', 48) . "\n");
                } else {
                    foreach ($seguimiento as $s) {
                        $printer->text($s->usuario . "\n");
                        $printer->text($s->seguimiento . "\n");
                        $printer->text(str_repeat('-', 48) . "\n");
                    }
                }

                $printer->feed(2);
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $printer->text($date . "\n\n");

                $printer->cut();
                $printer->close();

                DB::table('documento')->where(['id' => $documento->id])->update([
                    'picking' => 1,
                    'picking_by' => 1,
                    'picking_date' => date('Y-m-d H:i:s')
                ]);

            } catch (Exception $e) {
                $errorMsg = $e->getMessage();

                ErrorLoggerService::logger(
                    'No fue posible imprimir la etiqueta del documento ' . $documento->id,
                    'PrintController',
                    [
                        'exception' => $errorMsg,
                        'line' => self::logLocation()
                    ]
                );

                if (
                    str_contains($errorMsg, 'No route to host') ||
                    str_contains($errorMsg, 'Transport endpoint is not connected') ||
                    str_contains($errorMsg, 'Bad file descriptor') ||
                    str_contains($errorMsg, 'Failed to write')
                ) {
                    DB::table('documento')->where('id', $documento->id)->update([
                        'picking' => 0
                    ]);

                }

                if (str_contains($errorMsg, 'error al conectar con la impresora')) {
                    break;
                }
                continue;
            }
        }
    }

    /**
     * @return string
     */
    private static function logLocation(): string
    {
        $sis = 'BE'; // Front o Back
        $ini = 'PS'; // Primera letra del Controlador y Letra de la seguna Palabra: Controller, service
        $fin = 'ING'; // Últimas 3 letras del primer nombre del archivo *comPRAcontroller
        $trace = debug_backtrace()[0];
        return ('<br>' . $sis . $ini . $trace['line'] . $fin);
    }
}
