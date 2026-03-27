<?php

namespace Arseno25\DocxBuilder\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyDocxBuilderToken
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('docx-builder.api.enabled', false)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $expected = (string) (config('docx-builder.api.token') ?? '');

        if ($expected === '') {
            return response()->json(
                [
                    'message' =>
                        'Docx Builder API is enabled but no token is configured.',
                ],
                403,
            );
        }

        $provided =
            (string) $request->header('X-Docx-Builder-Token', '') ?:
            (string) $request->bearerToken();

        if ($provided === '' || !hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
