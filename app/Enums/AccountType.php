<?php

namespace App\Enums;

enum AccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Revenue = 'revenue';
    case Expense = 'expense';

    public function label(): string
    {
        return match ($this) {
            self::Asset => 'Aset',
            self::Liability => 'Liabilitas',
            self::Equity => 'Ekuitas',
            self::Revenue => 'Pendapatan',
            self::Expense => 'Beban',
        };
    }
}
