<?php

use App\Http\Controllers\DropboxController;
use App\Http\Controllers\PrintController;
use App\Http\Middleware\JwtMiddleware;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return phpinfo();
});
