<?php

use App\Http\Controllers\DropboxController;
use App\Http\Controllers\PrintController;
use App\Http\Middleware\JwtMiddleware;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return phpinfo();
});
//
//Route::group(['prefix' => 'dev'], function () {
//    Route::get('ka', [PrintController::class, 'keepAlive']);
//    Route::get('picking', [PrintController::class, 'picking']);
//
//    Route::get('usb/{barcode}', [PrintController::class, 'tickets_usb']);
//});
//
//Route::group(['middleware' => [JwtMiddleware::class]], function () {
//    Route::group(['prefix' => 'etiquetas'], function () {
//        Route::get('/data', [PrintController::class, 'etiquetasData']);
//        Route::post('/', [PrintController::class, 'etiquetas']);
//
//    });
//
//    Route::group(['prefix' => 'tickets'], function () {
//        Route::get('/', [PrintController::class, 'tickets']);
//    });
//});

Route::get('dropbox/actualizarToken', [DropboxController::class, 'actualizarTokenDropbox']);
