<?php

namespace App\Actions\Trade;

use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;
use stdClass;

class LinePricing
{
    /** @param array<string,mixed> $input
     * @return array{unit_discount:int,tax_code:?string,tax_bps:int,tax_included:bool,tax_amount:int,total:int}
     */
    public static function calculate(array $input, int $price, int $qty, ?stdClass $source): array
    {
        $discount = (int) ($source->unit_discount ?? $input['unit_discount'] ?? 0);
        $bps = (int) ($source->tax_bps ?? $input['tax_bps'] ?? 0);
        $included = (bool) ($source->tax_included ?? $input['tax_included'] ?? false);
        $code = $source ? $source->tax_code : ($input['tax_code'] ?? null);
        if ($discount > $price || ($bps && ! $code)) {
            throw ValidationException::withMessages(['lines' => 'Diskon satuan melebihi harga atau kode pajak belum diisi.']);
        }
        $base = ($price - $discount) * $qty;
        if ($source) {
            // Allocate the original rounded tax cumulatively across partial documents.
            $used = $source->quantity - $source->remaining;
            $share = fn (int $n) => BigInteger::of($source->tax_amount)->multipliedBy($n)->dividedBy($source->quantity, RoundingMode::HalfUp)->toInt();
            $tax = $share($used + $qty) - $share($used);
        } else {
            $tax = BigInteger::of($base)->multipliedBy($bps)->dividedBy($included ? 10000 + $bps : 10000, RoundingMode::HalfUp)->toInt();
        }

        return ['unit_discount' => $discount, 'tax_code' => $code, 'tax_bps' => $bps, 'tax_included' => $included, 'tax_amount' => $tax, 'total' => $base + ($included ? 0 : $tax)];
    }
}
