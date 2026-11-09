<?php

namespace App\Enums;

enum TradeAccountRole: string
{
    case Receivable = 'receivable';
    case Payable = 'payable';
    case Advance = 'advance';
    case Sales = 'sales';
    case Inventory = 'inventory';

    /** @return array{string, string, AccountType} */
    public function definition(): array
    {
        return match ($this) {
            self::Receivable => ['1120', 'Piutang Usaha', AccountType::Asset],
            self::Payable => ['2100', 'Utang Usaha', AccountType::Liability],
            self::Advance => ['2200', 'Uang Muka Pelanggan', AccountType::Liability],
            self::Sales => ['4110', 'Penjualan', AccountType::Revenue],
            self::Inventory => ['1300', 'Persediaan Barang', AccountType::Asset],
        };
    }
}
