<?php

use Illuminate\Support\Facades\Schema;

it('creates required tables', function () {
    expect(Schema::hasTable('docx_template_categories'))->toBeTrue();
    expect(Schema::hasTable('docx_templates'))->toBeTrue();
    expect(Schema::hasTable('docx_template_versions'))->toBeTrue();
    expect(Schema::hasTable('docx_template_fields'))->toBeTrue();
    expect(Schema::hasTable('docx_presets'))->toBeTrue();
    expect(Schema::hasTable('docx_number_sequences'))->toBeTrue();
    expect(Schema::hasTable('docx_generations'))->toBeTrue();
    expect(Schema::hasTable('docx_settings'))->toBeTrue();
});
