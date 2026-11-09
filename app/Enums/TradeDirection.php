<?php

namespace App\Enums;

enum TradeDirection: string
{
    case Sales = 'penjualan';
    case Purchases = 'pembelian';

    public function label(): string
    {
        return match ($this) {
            self::Sales => 'Penjualan & Piutang',
            self::Purchases => 'Pembelian & Utang',
        };
    }
}
