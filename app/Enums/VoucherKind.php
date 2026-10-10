<?php

namespace App\Enums;

enum VoucherKind: string
{
    case Physical = 'physical';
    case Electronic = 'electronic';
    case Nominal = 'nominal';
    case ElectronicNominal = 'electronic_nominal';

    public function label(): string
    {
        return match ($this) {
            self::Physical => 'Fisik',
            self::Electronic => 'e-Voucher Fisik',
            self::Nominal => 'Nominal',
            self::ElectronicNominal => 'e-Voucher Nominal',
        };
    }

    /**
     * Whether this kind is a digital (e-voucher) rendering.
     */
    public function isDigital(): bool
    {
        return $this === self::Electronic || $this === self::ElectronicNominal;
    }

    /**
     * Whether this kind carries a nominal (money) value.
     */
    public function isNominal(): bool
    {
        return $this === self::Nominal || $this === self::ElectronicNominal;
    }
}
