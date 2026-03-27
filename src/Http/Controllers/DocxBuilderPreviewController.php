<?php

namespace Arseno25\DocxBuilder\Http\Controllers;

use Arseno25\DocxBuilder\Support\DocxBuilderPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocxBuilderPreviewController
{
    public function pdf(Request $request, string $key): StreamedResponse
    {
        if (!config('docx-builder.preview.layout.enabled', false)) {
            abort(404);
        }

        if (!$request->hasValidSignature()) {
            abort(403);
        }

        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        $can =
            method_exists($user, 'hasPermissionTo')
                ? (bool) $user->hasPermissionTo(DocxBuilderPermissions::GENERATE)
                : (bool) $user->can(DocxBuilderPermissions::GENERATE);

        if (!$can) {
            abort(403);
        }

        $cacheKey = "docx-builder:layout-preview:{$key}";
        $meta = Cache::get($cacheKey);

        if (!is_array($meta) || !isset($meta['disk'], $meta['path'])) {
            abort(404);
        }

        $disk = (string) $meta['disk'];
        $path = (string) $meta['path'];

        if (!Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        return response()->streamDownload(
            fn() => print(Storage::disk($disk)->get($path)),
            basename($path),
            ['Content-Type' => 'application/pdf'],
        );
    }
}
