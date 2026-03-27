<?php

// config for Arseno25/DocxBuilder
return [
    'template_disk' => env('DOCX_BUILDER_TEMPLATE_DISK', 'local'),

    'output_disk' => env('DOCX_BUILDER_OUTPUT_DISK', 'local'),

    'output_path_prefix' => env(
        'DOCX_BUILDER_OUTPUT_PATH_PREFIX',
        'docx-builder',
    ),

    'retention_days' => [
        'test' => env('DOCX_BUILDER_RETENTION_TEST_DAYS', 7),
        'final' => env('DOCX_BUILDER_RETENTION_FINAL_DAYS', null),
    ],

    'payload_snapshot_policy' => env(
        'DOCX_BUILDER_PAYLOAD_SNAPSHOT_POLICY',
        'off',
    ),

    'queue' => [
        'enabled' => env('DOCX_BUILDER_QUEUE_ENABLED', false),
        'connection' => env('DOCX_BUILDER_QUEUE_CONNECTION', null),
        'queue' => env('DOCX_BUILDER_QUEUE', null),
    ],

    'preview' => [
        'enabled_by_default' => env('DOCX_BUILDER_PREVIEW_ENABLED', true),
        'max_chars' => env('DOCX_BUILDER_PREVIEW_MAX_CHARS', 12000),
        'debounce_ms' => env('DOCX_BUILDER_PREVIEW_DEBOUNCE_MS', 700),
        'layout' => [
            'enabled' => env('DOCX_BUILDER_LAYOUT_PREVIEW_ENABLED', false),
            'enabled_by_default' => env(
                'DOCX_BUILDER_LAYOUT_PREVIEW_ENABLED_BY_DEFAULT',
                false,
            ),
            'driver' => env(
                'DOCX_BUILDER_LAYOUT_PREVIEW_DRIVER',
                'libreoffice',
            ),
            'soffice_binary' => env(
                'DOCX_BUILDER_LAYOUT_PREVIEW_SOFFICE',
                'soffice',
            ),
            'disk' => env('DOCX_BUILDER_LAYOUT_PREVIEW_DISK', null),
            'path_prefix' => env(
                'DOCX_BUILDER_LAYOUT_PREVIEW_PATH_PREFIX',
                'docx-builder/previews',
            ),
            'ttl_minutes' => env('DOCX_BUILDER_LAYOUT_PREVIEW_TTL_MINUTES', 10),
        ],
    ],

    'api' => [
        'enabled' => env('DOCX_BUILDER_API_ENABLED', false),
        'prefix' => env('DOCX_BUILDER_API_PREFIX', 'docx-builder'),
        'middleware' => ['api'],
        'token' => env('DOCX_BUILDER_API_TOKEN'),
    ],
];
