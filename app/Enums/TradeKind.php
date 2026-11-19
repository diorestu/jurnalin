<?php

namespace App\Enums;

enum TradeKind: string
{
    case Quotation = 'quotation';
    case Order = 'order';
    case Delivery = 'delivery';
    case Receipt = 'receipt';
    case Invoice = 'invoice';
    case Advance = 'advance';
    case Payment = 'payment';
    case Return = 'return';
    case Refund = 'refund';
    case AdvanceRefund = 'advance_refund';

    public function label(TradeDirection $direction): string
    {
        $sales = $direction === TradeDirection::Sales;

        return match ($this) {
            self::Quotation => 'Penawaran',
            self::Order => $sales ? 'Pesanan Penjualan' : 'PO Pembelian',
            self::Delivery => 'Pengiriman',
            self::Receipt => 'Penerimaan Barang',
            self::Invoice => $sales ? 'Faktur Penjualan' : 'Tagihan Pemasok',
            self::Advance => $sales ? 'Uang Muka Pelanggan' : 'Uang Muka Pemasok',
            self::Payment => $sales ? 'Penerimaan Piutang' : 'Pembayaran Utang',
            self::Return => $sales ? 'Retur Penjualan' : 'Retur Pembelian',
            self::Refund => $sales ? 'Pengembalian Dana Pelanggan' : 'Penerimaan Dana Retur',
            self::AdvanceRefund => 'Pengembalian Uang Muka',
        };
    }

    public function number(TradeDirection $direction, int $id): string
    {
        $prefix = match ($this) {
            self::Quotation => 'QT',
            self::Order => $direction === TradeDirection::Sales ? 'SO' : 'PO',
            self::Delivery => 'SJ',
            self::Receipt => 'PB',
            self::Invoice => $direction === TradeDirection::Sales ? 'INV' : 'BILL',
            self::Advance => 'DP',
            self::Payment => $direction === TradeDirection::Sales ? 'RCV' : 'PAY',
            self::Return => $direction === TradeDirection::Sales ? 'SR' : 'PR',
            self::Refund => $direction === TradeDirection::Sales ? 'RF' : 'RR',
            self::AdvanceRefund => 'RDP',
        };

        return $prefix.'-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    }

    public function isCash(): bool
    {
        return in_array($this, [self::Advance, self::Payment, self::Refund, self::AdvanceRefund], true);
    }

    /** @return list<self> */
    public static function standalone(TradeDirection $direction): array
    {
        return $direction === TradeDirection::Sales
            ? [self::Quotation, self::Order, self::Invoice]
            : [self::Order, self::Invoice];
    }

    /** @return list<self> */
    public function next(TradeDirection $direction): array
    {
        $sales = $direction === TradeDirection::Sales;

        return match ($this) {
            self::Quotation => $sales ? [self::Order] : [],
            self::Order => $sales ? [self::Delivery, self::Advance] : [self::Receipt, self::Advance],
            self::Delivery => $sales ? [self::Invoice] : [],
            self::Receipt => $sales ? [] : [self::Invoice],
            self::Invoice => [self::Payment, self::Return, self::Refund],
            self::Advance => [self::AdvanceRefund],
            default => [],
        };
    }

    /** @return list<self> */
    public static function available(TradeDirection $direction): array
    {
        $kinds = self::standalone($direction);
        foreach (self::cases() as $kind) {
            $kinds = [...$kinds, ...$kind->next($direction)];
        }

        return array_values(array_filter(self::cases(), fn (self $kind) => in_array($kind, $kinds, true)));
    }
}
