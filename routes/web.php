<?php

use Arseno25\DocxBuilder\Http\Controllers\DocxBuilderPreviewController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'signed'])->group(function () {
    Route::get('/docx-builder/previews/{key}.pdf', [DocxBuilderPreviewController::class, 'pdf'])
        ->name('docx-builder.preview.pdf');
});
