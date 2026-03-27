<?php

namespace Arseno25\DocxBuilder\Services;

use Arseno25\DocxBuilder\Models\DocumentNumberSequence;
use Arseno25\DocxBuilder\Models\DocumentPreset;
use Arseno25\DocxBuilder\Models\DocumentTemplate;
use Arseno25\DocxBuilder\Models\DocumentTemplateField;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

class GenerationPayloadBuilder
{
    /**
     * @param  array<string, mixed>  $fields
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $renderLog
     * @return array{payload: array<string, mixed>, fields: array<string, mixed>, warnings: array<int, array{key: string, placeholder: string}>}
     */
    public function build(
        DocumentTemplate $template,
        array $fields,
        array $options,
        string $mode,
        array &$renderLog = [],
        bool $preview = false,
    ): array {
        $applyPresets = (bool) ($options['apply_presets'] ?? true);
        $sourceRecordId = $options['source_record_id'] ?? null;
        $strictSource = (bool) ($options['strict_source'] ?? false);
        $useDummyData = (bool) ($options['use_dummy_data'] ?? false);
        $useNumbering = (bool) ($options['use_numbering'] ?? $mode === 'final');
        $sequenceId = $options['sequence_id'] ?? null;

        $fields = $this->applyDefaultsAndPresets(
            $template,
            $fields,
            $applyPresets,
        );
        $fields = $this->applySourceRecordIfNeeded(
            $template,
            $fields,
            $sourceRecordId,
            strict: $strictSource,
        );
        $fields = $this->applyVisibilityRules($template, $fields);

        if ($mode === 'test' && $useDummyData) {
            $fields = $this->fillDummyData($template, $fields);
        }

        $fields = $this->applyTransformRules($template, $fields);

        $payload = ['doc' => $fields];

        if (
            $mode === 'final' &&
            $useNumbering &&
            DocumentNumberSequence::query()
                ->where('template_id', $template->getKey())
                ->where('is_active', true)
                ->exists()
        ) {
            $payload = $preview
                ? $this->applyNumberingPreviewIfNeeded(
                    $template,
                    $payload,
                    $sequenceId,
                    $renderLog,
                )
                : $this->applyNumberingIfNeeded(
                    $template,
                    $payload,
                    $sequenceId,
                    $renderLog,
                );
        }

        $warnings = $this->getEmptyPlaceholderWarnings($template, $payload);

        return [
            'payload' => $payload,
            'fields' => (array) Arr::get($payload, 'doc', []),
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function validateRequiredFields(
        DocumentTemplate $template,
        array $fields,
    ): array {
        /** @var Collection<int, DocumentTemplateField> $schemaFields */
        $schemaFields = DocumentTemplateField::query()
            ->where('template_id', $template->getKey())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['key', 'required']);

        $missing = [];

        foreach ($schemaFields as $schemaField) {
            if (!filled($schemaField->key)) {
                continue;
            }

            if (!(bool) $schemaField->required) {
                continue;
            }

            if (blank($fields[$schemaField->key] ?? null)) {
                $missing[] = (string) $schemaField->key;
            }
        }

        return $missing;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function applyDefaultsAndPresets(
        DocumentTemplate $template,
        array $fields,
        bool $applyPresets,
    ): array {
        /** @var Collection<int, DocumentTemplateField> $schemaFields */
        $schemaFields = DocumentTemplateField::query()
            ->where('template_id', $template->getKey())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($schemaFields as $schemaField) {
            if (!filled($schemaField->key)) {
                continue;
            }

            if (
                array_key_exists($schemaField->key, $fields) &&
                filled($fields[$schemaField->key])
            ) {
                continue;
            }

            $default = $this->getDefaultValue($schemaField);
            if (filled($default)) {
                $fields[$schemaField->key] = $default;
            }
        }

        if (!$applyPresets) {
            return $fields;
        }

        /** @var Collection<int, DocumentPreset> $presets */
        $presets = DocumentPreset::query()
            ->where('template_id', $template->getKey())
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($presets as $preset) {
            if (!filled($preset->key)) {
                continue;
            }

            if (
                array_key_exists($preset->key, $fields) &&
                filled($fields[$preset->key])
            ) {
                continue;
            }

            $fields[$preset->key] = match ($preset->type) {
                'image' => [
                    'disk' =>
                        $preset->disk ?:
                        (string) config('docx-builder.output_disk', 'local'),
                    'path' => $preset->path,
                ],
                'json' => $preset->value,
                default => (string) ($preset->value['text'] ??
                    ($preset->value ?? '')),
            };
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function applySourceRecordIfNeeded(
        DocumentTemplate $template,
        array $fields,
        mixed $recordId,
        bool $strict = false,
    ): array {
        if (blank($recordId)) {
            return $fields;
        }

        $modelClass = $this->resolveSourceModelClass($template);
        if (!$modelClass) {
            if ($strict) {
                throw new RuntimeException(
                    'This template does not have a valid source model configured.',
                );
            }

            return $fields;
        }

        /** @var Model|null $record */
        $record = $modelClass::query()->find($recordId);
        if (!$record) {
            if ($strict) {
                throw new RuntimeException('Source record was not found.');
            }

            return $fields;
        }

        /** @var Collection<int, DocumentTemplateField> $schemaFields */
        $schemaFields = DocumentTemplateField::query()
            ->where('template_id', $template->getKey())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($schemaFields as $schemaField) {
            if (!filled($schemaField->key)) {
                continue;
            }

            $dataSourceType = (string) ($schemaField->data_source_type ?: '');
            if ($dataSourceType !== '' && $dataSourceType !== 'source_record') {
                continue;
            }

            if (filled($fields[$schemaField->key] ?? null)) {
                continue;
            }

            $attribute =
                (string) ($schemaField->data_source_config['attribute'] ??
                    $schemaField->key);

            $value = data_get($record, $attribute);

            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d');
            }

            if (filled($value)) {
                $fields[$schemaField->key] = $value;
            }
        }

        return $fields;
    }

    /**
     * @return class-string<Model>|null
     */
    private function resolveSourceModelClass(
        DocumentTemplate $template,
    ): ?string {
        $class = trim((string) ($template->source_model_class ?: ''));
        if ($class === '') {
            return null;
        }

        if (!class_exists($class)) {
            return null;
        }

        if (!is_subclass_of($class, Model::class)) {
            return null;
        }

        return $class;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function applyVisibilityRules(
        DocumentTemplate $template,
        array $fields,
    ): array {
        /** @var Collection<int, DocumentTemplateField> $schemaFields */
        $schemaFields = DocumentTemplateField::query()
            ->where('template_id', $template->getKey())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($schemaFields as $schemaField) {
            if (!filled($schemaField->key)) {
                continue;
            }

            if (
                !is_array($schemaField->visibility_rules) ||
                empty($schemaField->visibility_rules)
            ) {
                continue;
            }

            if (
                !$this->evaluateVisibilityRules(
                    $schemaField->visibility_rules,
                    $fields,
                )
            ) {
                unset($fields[$schemaField->key]);
            }
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $rules
     * @param  array<string, mixed>  $fields
     */
    private function evaluateVisibilityRules(array $rules, array $fields): bool
    {
        if (array_key_exists('all', $rules) && is_array($rules['all'])) {
            foreach ($rules['all'] as $rule) {
                if (!is_array($rule)) {
                    continue;
                }

                if (!$this->evaluateVisibilityRule($rule, $fields)) {
                    return false;
                }
            }

            return true;
        }

        if (array_key_exists('any', $rules) && is_array($rules['any'])) {
            foreach ($rules['any'] as $rule) {
                if (!is_array($rule)) {
                    continue;
                }

                if ($this->evaluateVisibilityRule($rule, $fields)) {
                    return true;
                }
            }

            return false;
        }

        return $this->evaluateVisibilityRule($rules, $fields);
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  array<string, mixed>  $fields
     */
    private function evaluateVisibilityRule(array $rule, array $fields): bool
    {
        $when = (string) ($rule['when'] ?? '');
        if ($when === '') {
            return true;
        }

        $operator = (string) ($rule['operator'] ?? 'filled');
        $expected = $rule['value'] ?? null;
        $actual = $fields[$when] ?? null;

        return match ($operator) {
            'equals' => (string) $actual === (string) $expected,
            'not_equals' => (string) $actual !== (string) $expected,
            'blank' => blank($actual),
            'filled' => filled($actual),
            'in' => is_array($expected)
                ? in_array($actual, $expected, false)
                : false,
            'not_in' => is_array($expected)
                ? !in_array($actual, $expected, false)
                : true,
            default => true,
        };
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function fillDummyData(
        DocumentTemplate $template,
        array $fields,
    ): array {
        /** @var Collection<int, DocumentTemplateField> $schemaFields */
        $schemaFields = DocumentTemplateField::query()
            ->where('template_id', $template->getKey())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($schemaFields as $schemaField) {
            if (!filled($schemaField->key)) {
                continue;
            }

            if (filled($fields[$schemaField->key] ?? null)) {
                continue;
            }

            $fields[$schemaField->key] = match ($schemaField->type) {
                'date' => now()->toDateString(),
                'number' => 1,
                'textarea' => 'Dummy text',
                'select' => 'Dummy',
                'image' => null,
                default => 'Dummy',
            };
        }

        return $fields;
    }

    private function getDefaultValue(DocumentTemplateField $field): mixed
    {
        $value = $field->default_value ?? null;
        if (!is_array($value)) {
            return null;
        }

        return array_key_exists('value', $value) ? $value['value'] : null;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function applyTransformRules(
        DocumentTemplate $template,
        array $fields,
    ): array {
        /** @var Collection<int, DocumentTemplateField> $schemaFields */
        $schemaFields = DocumentTemplateField::query()
            ->where('template_id', $template->getKey())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['key', 'transform_rules']);

        foreach ($schemaFields as $schemaField) {
            $key = (string) ($schemaField->key ?? '');
            if ($key === '') {
                continue;
            }

            $rules = $schemaField->transform_rules;
            if (!is_array($rules) || empty($rules)) {
                continue;
            }

            if (!array_key_exists($key, $fields)) {
                continue;
            }

            $value = $fields[$key];
            if (blank($value)) {
                continue;
            }

            $fields[$key] = $this->applyTransformSteps($value, $rules);
        }

        return $fields;
    }

    /**
     * @param  array<int, mixed>  $rules
     */
    private function applyTransformSteps(mixed $value, array $rules): mixed
    {
        $current = $value;

        foreach ($rules as $rule) {
            $step = $this->normalizeTransformStep($rule);
            if (!$step) {
                continue;
            }

            $current = $this->applyTransformStep($current, $step);
        }

        return $current;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeTransformStep(mixed $rule): ?array
    {
        if (is_string($rule) && $rule !== '') {
            return ['type' => $rule];
        }

        if (!is_array($rule)) {
            return null;
        }

        $type = $rule['type'] ?? null;
        if (!is_string($type) || $type === '') {
            return null;
        }

        return $rule;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function applyTransformStep(mixed $value, array $rule): mixed
    {
        $type = (string) $rule['type'];

        if ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-m-d');
        }

        if (!is_scalar($value)) {
            return $value;
        }

        $string = (string) $value;

        return match ($type) {
            'trim' => trim($string),
            'upper' => mb_strtoupper($string),
            'lower' => mb_strtolower($string),
            'title' => Str::title($string),
            'replace' => $this->transformReplace($string, $rule),
            'prefix' => (string) ($rule['value'] ?? '') . $string,
            'suffix' => $string . (string) ($rule['value'] ?? ''),
            'pad_left' => $this->transformPadLeft($string, $rule),
            'date_format' => $this->transformDateFormat($string, $rule),
            'number_format' => $this->transformNumberFormat($string, $rule),
            default => $value,
        };
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function transformReplace(string $value, array $rule): string
    {
        $search = $rule['search'] ?? null;
        $replace = $rule['replace'] ?? '';

        if (!is_scalar($search)) {
            return $value;
        }

        return str_replace((string) $search, (string) $replace, $value);
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function transformPadLeft(string $value, array $rule): string
    {
        $length = $rule['length'] ?? null;
        if (!is_scalar($length)) {
            return $value;
        }

        $len = (int) $length;
        if ($len <= 0) {
            return $value;
        }

        $pad = (string) ($rule['pad'] ?? '0');
        $padChar = $pad === '' ? '0' : mb_substr($pad, 0, 1);

        return str_pad($value, $len, $padChar, STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function transformDateFormat(string $value, array $rule): string
    {
        $format = $rule['format'] ?? null;
        if (!is_scalar($format)) {
            return $value;
        }

        $format = (string) $format;
        if ($format === '') {
            return $value;
        }

        try {
            $inputFormat = $rule['input_format'] ?? null;
            $dt =
                is_scalar($inputFormat) && (string) $inputFormat !== ''
                    ? Carbon::createFromFormat((string) $inputFormat, $value)
                    : Carbon::parse($value);

            return $dt->format($format);
        } catch (\Throwable) {
            return $value;
        }
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function transformNumberFormat(string $value, array $rule): string
    {
        if (!is_numeric($value)) {
            return $value;
        }

        $decimals = (int) ($rule['decimals'] ?? 0);
        $decimalSeparator = (string) ($rule['decimal_separator'] ?? '.');
        $thousandsSeparator = (string) ($rule['thousands_separator'] ?? ',');

        return number_format(
            (float) $value,
            max(0, $decimals),
            $decimalSeparator,
            $thousandsSeparator,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $renderLog
     * @return array<string, mixed>
     */
    private function applyNumberingIfNeeded(
        DocumentTemplate $template,
        array $payload,
        mixed $sequenceId,
        array &$renderLog,
    ): array {
        if (blank($sequenceId)) {
            throw new RuntimeException('No sequence has been selected.');
        }

        $sequence = DocumentNumberSequence::query()
            ->where('template_id', $template->getKey())
            ->where('is_active', true)
            ->whereKey($sequenceId)
            ->first();

        if (!$sequence) {
            throw new RuntimeException(
                'The selected sequence was not found or is not active.',
            );
        }

        $doc = (array) Arr::get($payload, 'doc', []);
        $current = $doc[$sequence->key] ?? null;

        if (blank($current)) {
            /** @var NumberSequenceService $svc */
            $svc = app(NumberSequenceService::class);
            $value = $svc->nextNumber($sequence);

            $doc[$sequence->key] = $value;
            Arr::set($payload, 'doc', $doc);

            $renderLog['numbering'] = [
                'sequence_id' => $sequence->getKey(),
                'key' => $sequence->key,
                'value' => $value,
                'applied' => true,
            ];
        } else {
            $renderLog['numbering'] = [
                'sequence_id' => $sequence->getKey(),
                'key' => $sequence->key,
                'value' => (string) $current,
                'applied' => false,
            ];
        }

        return $payload;
    }

    /**
     * Preview-safe numbering (peek only).
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $renderLog
     * @return array<string, mixed>
     */
    private function applyNumberingPreviewIfNeeded(
        DocumentTemplate $template,
        array $payload,
        mixed $sequenceId,
        array &$renderLog,
    ): array {
        if (blank($sequenceId)) {
            return $payload;
        }

        $sequence = DocumentNumberSequence::query()
            ->where('template_id', $template->getKey())
            ->where('is_active', true)
            ->whereKey($sequenceId)
            ->first();

        if (!$sequence) {
            return $payload;
        }

        $doc = (array) Arr::get($payload, 'doc', []);
        $current = $doc[$sequence->key] ?? null;

        if (blank($current)) {
            /** @var NumberSequenceService $svc */
            $svc = app(NumberSequenceService::class);
            $value = $svc->peekNextNumber($sequence);

            $doc[$sequence->key] = $value;
            Arr::set($payload, 'doc', $doc);

            $renderLog['numbering'] = [
                'sequence_id' => $sequence->getKey(),
                'key' => $sequence->key,
                'value' => $value,
                'applied' => true,
                'preview' => true,
            ];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{key: string, placeholder: string}>
     */
    private function getEmptyPlaceholderWarnings(
        DocumentTemplate $template,
        array $payload,
    ): array {
        $doc = (array) Arr::get($payload, 'doc', []);

        /** @var Collection<int, DocumentTemplateField> $fields */
        $fields = DocumentTemplateField::query()
            ->where('template_id', $template->getKey())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $fields
            ->filter(
                fn(DocumentTemplateField $field) => filled(
                    $field->placeholder_tag,
                ),
            )
            ->filter(
                fn(DocumentTemplateField $field) => blank(
                    $doc[$field->key] ?? null,
                ),
            )
            ->map(
                fn(DocumentTemplateField $field): array => [
                    'key' => (string) $field->key,
                    'placeholder' => (string) $field->placeholder_tag,
                ],
            )
            ->values()
            ->all();
    }
}
