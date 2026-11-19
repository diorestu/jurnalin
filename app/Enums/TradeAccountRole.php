<?php

namespace App\Enums;

enum TradeAccountRole: string
{
    case Receivable = 'receivable';
    case Payable = 'payable';
    case Advance = 'advance';
    case SupplierAdvance = 'supplier_advance';
    case VatInput = 'vat_input';
    case VatOutput = 'vat_output';
    case Sales = 'sales';
    case Inventory = 'inventory';

    /** @return array{string, string, AccountType} */
    public function definition(): array
    {
        return match ($this) {
            self::Receivable => ['1120', 'Piutang Usaha', AccountType::Asset],
            self::Payable => ['2100', 'Utang Usaha', AccountType::Liability],
            self::Advance => ['2200', 'Uang Muka Pelanggan', AccountType::Liability],
            self::SupplierAdvance => ['1201', 'Uang Muka Pemasok', AccountType::Asset],
            self::VatInput => ['1291', 'PPN Masukan', AccountType::Asset],
            self::VatOutput => ['2151', 'PPN Keluaran', AccountType::Liability],
            self::Sales => ['4110', 'Penjualan', AccountType::Revenue],
            self::Inventory => ['1300', 'Persediaan Barang', AccountType::Asset],
        };
    }
}
