<?php

use App\Http\Controllers\DropboxController;
use App\Http\Controllers\PrintController;
use App\Http\Middleware\JwtMiddleware;
use Illuminate\Support\Facades\Route;


Route::group(['prefix' => 'dev'], function () {
    Route::get('usb/{barcode}', [PrintController::class, 'ticketsUsb']);
    Route::get('me', phpinfo());
});

Route::group(['middleware' => [JwtMiddleware::class]], function () {
    Route::group(['prefix' => 'etiquetas'], function () {
        Route::get('/data', [PrintController::class, 'etiquetasData']);

        Route::post('/', [PrintController::class, 'etiquetas']);
        Route::post('/serie', [PrintController::class, 'etiquetasSerie']);
        Route::post('/busqueda', [PrintController::class, 'imprimirBusqueda']);
        Route::post('/ensamble', [PrintController::class, 'etiquetasEnsamble']);
    });

    Route::group(['prefix' => 'tickets'], function () {
        Route::get('/', [PrintController::class, 'tickets']);
    });

    Route::group(['prefix' => 'guias'], function () {
        Route::get('/print/{documentoId}/{impresoraNombre}', [PrintController::class, 'print']);
    });

    Route::group(['prefix' => 'manifiesto'], function () {
        Route::post('/salida', [PrintController::class, 'manifiestoSalida']);
    });
});


