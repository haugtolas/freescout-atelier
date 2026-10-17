<?php

Route::group(['middleware' => 'web', 'prefix' => \Helper::getSubdirectory(), 'namespace' => 'Modules\Atelier\Http\Controllers'], function () {
    // Public on purpose: also used by the login page
    Route::get('/atelier/vars.css', ['uses' => 'AtelierController@vars'])->name('atelier.vars');
    Route::get('/atelier/logo/{variant}', ['uses' => 'AtelierController@logo'])->where('variant', 'light|dark')->name('atelier.logo');
    Route::get('/atelier/favicon/{domain}', ['uses' => 'AtelierController@favicon', 'middleware' => ['auth']])->where('domain', '[A-Za-z0-9.-]+')->name('atelier.favicon');
    Route::post('/atelier/news/seen', ['uses' => 'AtelierController@newsSeen', 'middleware' => ['auth', 'roles'], 'roles' => ['admin']])->name('atelier.news.seen');
    Route::post('/atelier/save', ['uses' => 'AtelierController@save', 'middleware' => ['auth', 'roles'], 'roles' => ['admin']])->name('atelier.save');
    Route::get('/atelier/export', ['uses' => 'AtelierController@export', 'middleware' => ['auth', 'roles'], 'roles' => ['admin']])->name('atelier.export');
    Route::get('/atelier/update/check', ['uses' => 'AtelierController@updateCheck', 'middleware' => ['auth', 'roles'], 'roles' => ['admin']])->name('atelier.update.check');
    Route::post('/atelier/update/apply', ['uses' => 'AtelierController@updateApply', 'middleware' => ['auth', 'roles'], 'roles' => ['admin']])->name('atelier.update.apply');
    Route::post('/atelier/import', ['uses' => 'AtelierController@import', 'middleware' => ['auth', 'roles'], 'roles' => ['admin']])->name('atelier.import');
});
