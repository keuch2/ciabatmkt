<?php

use Illuminate\Support\Facades\Route;

// La SPA de React atiende todas las rutas que no sean de la API.
Route::view('/{any?}', 'app')->where('any', '^(?!api(/|$)|sanctum(/|$)|up$).*$');
