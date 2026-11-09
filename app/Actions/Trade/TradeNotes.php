<?php

namespace App\Actions\Trade;

use Illuminate\Validation\ValidationException;

class TradeNotes
{
    public const MAX_LENGTH = 500;

    /**
     * @return array{notes: string|null, notes_delta: string|null}
     */
    public function normalize(string $json): array
    {
        $runs = json_decode($json, true, 32);
        if (! is_array($runs) || ! array_is_list($runs) || count($runs) > self::MAX_LENGTH + 1) {
            $this->invalidFormat();
        }

        $text = '';
        foreach ($runs as $run) {
            if (! is_array($run) || ! is_string($run['insert'] ?? null) || array_diff_key($run, ['insert' => true, 'attributes' => true])) {
                $this->invalidFormat();
            }

            $attributes = $run['attributes'] ?? [];
            if (! is_array($attributes) || array_diff_key($attributes, ['bold' => true, 'italic' => true, 'underline' => true])
                || in_array(false, array_map(fn (mixed $enabled): bool => $enabled === true, $attributes), true)) {
                $this->invalidFormat();
            }

            $text .= $run['insert'];
        }

        if (str_ends_with($text, "\n")) {
            $text = substr($text, 0, -1);
        }

        if (mb_strlen($text) > self::MAX_LENGTH) {
            throw ValidationException::withMessages(['notes_delta' => 'Catatan maksimal '.self::MAX_LENGTH.' karakter.']);
        }

        if (trim($text) === '') {
            return ['notes' => null, 'notes_delta' => null];
        }

        return ['notes' => $text, 'notes_delta' => json_encode($runs, JSON_THROW_ON_ERROR)];
    }

    private function invalidFormat(): never
    {
        throw ValidationException::withMessages([
            'notes_delta' => 'Gunakan teks, tebal, miring, atau garis bawah pada catatan.',
        ]);
    }
}
