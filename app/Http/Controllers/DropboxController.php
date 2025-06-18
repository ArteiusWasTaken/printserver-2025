<?php

namespace App\Http\Controllers;

use App\Services\DropboxService;
use Exception;
use Illuminate\Http\JsonResponse;

/**
 *
 */
class DropboxController extends Controller
{
    protected DropboxService $dropbox;

    /**
     * @param DropboxService $dropbox
     */
    public function __construct(DropboxService $dropbox)
    {
        $this->dropbox = $dropbox;
    }

    /**
     * @return JsonResponse
     */
    public function actualizarTokenDropbox(): JsonResponse
    {
        set_time_limit(0);
        $dropbox = new DropboxService();
        try {
            $token = $dropbox->refreshAccessToken();

            return response()->json(['code' => 200, 'token' => $token]);
        } catch (Exception $e) {
            return response()->json(['code' => 500, 'error' => $e->getMessage()]);
        }
    }

    /**
     * @return string
     */
    private static function logVariableLocation(): string
    {
        $sis = 'BE'; //Front o Back
        $ini = 'AS'; //Primera letra del Controlador y Letra de la segunda Palabra: Controller, service
        $fin = 'UTH'; //Últimas 3 letras del primer nombre del archivo *comPRAcontroller
        $trace = debug_backtrace()[0];
        return ('<br>' . $sis . $ini . $trace['line'] . $fin);
    }
}
