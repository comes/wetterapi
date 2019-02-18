<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
 */

Route::group(['prefix' => '/weather'], function () {
    Route::any('/current', 'WeatherController@current');
    Route::any('/', 'WeatherController@weather');
});

Route::get('/wind', function (Request $request) {
    $value = \App\Archive::orderBy('dateTime', 'asc')->wind()->paginate(1000); // 48 = alle 30 minuten neue daten
    return $value;
});

Route::get('/barometer', function (Request $request) {
    $value = \App\Archive::orderBy('dateTime', 'asc')->barometer()->paginate(48); // 48 = alle 30 minuten neue daten
    return $value;
});

Route::get('/temperature', function (Request $request) {
    $value = \App\Archive::orderBy('dateTime', 'asc')->temperature()->paginate(1000); // 48 = alle 30 minuten neue daten
    return $value;
});

Route::get('/humidity', function (Request $request) {
    $value = \App\Archive::orderBy('dateTime', 'asc')->humidity()->paginate(48); // 48 = alle 30 minuten neue daten
    return $value;
});
