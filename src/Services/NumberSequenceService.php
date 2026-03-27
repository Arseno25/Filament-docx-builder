<?php

namespace Arseno25\DocxBuilder\Services;

use Arseno25\DocxBuilder\Models\DocumentNumberSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NumberSequenceService
{
    public function peekNextNumber(
        DocumentNumberSequence $sequence,
        ?\DateTimeInterface $now = null,
    ): string {
        $now ??= now();

        $counter = (int) $sequence->counter;

        if ($this->shouldReset($sequence, $now)) {
            $counter = 0;
        }

        $counter++;

        return $this->format($sequence->pattern, $counter, $now);
    }

    public function nextNumber(
        DocumentNumberSequence $sequence,
        ?\DateTimeInterface $now = null,
    ): string {
        $now ??= now();

        return DB::transaction(function () use ($sequence, $now) {
            $sequence = DocumentNumberSequence::query()
                ->whereKey($sequence->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->shouldReset($sequence, $now)) {
                $sequence->counter = 0;
                $sequence->last_reset_at = $now;
            }

            $sequence->counter++;
            $sequence->save();

            return $this->format(
                $sequence->pattern,
                (int) $sequence->counter,
                $now,
            );
        });
    }

    private function shouldReset(
        DocumentNumberSequence $sequence,
        \DateTimeInterface $now,
    ): bool {
        $policy = (string) ($sequence->reset_policy ?: 'never');
        if ($policy === 'never') {
            return false;
        }

        $last = $sequence->last_reset_at;
        if (!$last) {
            return true;
        }

        $lastDate =
            $last instanceof \DateTimeInterface
                ? $last
                : new \DateTimeImmutable((string) $last);

        return match ($policy) {
            'daily' => $lastDate->format('Y-m-d') !== $now->format('Y-m-d'),
            'monthly' => $lastDate->format('Y-m') !== $now->format('Y-m'),
            'yearly' => $lastDate->format('Y') !== $now->format('Y'),
            default => false,
        };
    }

    private function format(
        string $pattern,
        int $counter,
        \DateTimeInterface $now,
    ): string {
        $year = $now->format('Y');
        $month = $now->format('m');
        $romanMonth = $this->toRoman((int) $month);

        $out =
            preg_replace_callback(
                '/\\{seq(?::(\\d+))?\\}/',
                function (array $m) use ($counter) {
                    $pad = isset($m[1]) ? (int) $m[1] : 0;
                    return $pad > 0
                        ? str_pad((string) $counter, $pad, '0', STR_PAD_LEFT)
                        : (string) $counter;
                },
                $pattern,
            ) ?? $pattern;

        $out = str_replace(
            ['{year}', '{month}', '{roman_month}'],
            [$year, $month, $romanMonth],
            $out,
        );

        return Str::of($out)->trim()->toString();
    }

    private function toRoman(int $number): string
    {
        $map = [
            10 => 'X',
            9 => 'IX',
            5 => 'V',
            4 => 'IV',
            1 => 'I',
        ];

        $out = '';
        $n = $number;

        foreach ($map as $value => $symbol) {
            while ($n >= $value) {
                $out .= $symbol;
                $n -= $value;
            }
        }

        return $out;
    }
}
