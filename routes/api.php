<?php

use Arseno25\DocxBuilder\Http\Controllers\DocxBuilderApiController;
use Arseno25\DocxBuilder\Http\Middleware\VerifyDocxBuilderToken;
use Illuminate\Support\Facades\Route;

Route::prefix((string) config('docx-builder.api.prefix', 'docx-builder'))
    ->middleware(
        array_merge((array) config('docx-builder.api.middleware', ['api']), [
            VerifyDocxBuilderToken::class,
        ]),
    )
    ->group(function () {
        Route::post('/generations', [
            DocxBuilderApiController::class,
            'generate',
        ])->name('docx-builder.api.generations.generate');

        Route::get('/generations/{generation}', [
            DocxBuilderApiController::class,
            'show',
        ])->name('docx-builder.api.generations.show');

        Route::get('/generations/{generation}/download', [
            DocxBuilderApiController::class,
            'download',
        ])->name('docx-builder.api.generations.download');
    });
