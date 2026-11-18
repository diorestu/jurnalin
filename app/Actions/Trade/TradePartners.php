<?php

namespace App\Actions\Trade;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use stdClass;

class TradePartners
{
    public static function resolve(int $teamId, string $direction, string $name, ?int $id = null): stdClass
    {
        $query = DB::table('trade_partners')->where('team_id', $teamId)->where('direction', $direction);
        if ($id !== null) {
            return $query->where('id', $id)->first() ?? throw ValidationException::withMessages(['partner' => 'Kontak tidak tersedia untuk perusahaan ini.']);
        }
        $matches = (clone $query)->where('name', $name)->limit(2)->get();
        if ($matches->count() > 1) {
            throw ValidationException::withMessages(['partner' => 'Nama kontak ambigu. Pilih kode kontak yang sesuai.']);
        }
        if ($match = $matches->first()) {
            return $match;
        }
        $id = DB::table('trade_partners')->insertGetId(['team_id' => $teamId, 'direction' => $direction, 'name' => $name, 'code' => 'K-'.Str::ulid()]);

        return $query->where('id', $id)->firstOrFail();
    }
}
