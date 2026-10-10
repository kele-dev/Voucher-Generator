<?php

namespace App\Enums;

enum VoucherStatus: string
{
    case Issued = 'issued';
    case Voided = 'voided';

    /**
     * Derived, never stored: an issued voucher whose window has passed.
     */
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Issued => 'Aktif',
            self::Voided => 'Dibatalkan',
            self::Expired => 'Kedaluwarsa',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Issued => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            self::Voided => 'bg-rose-50 text-rose-700 border-rose-200',
            self::Expired => 'bg-amber-50 text-amber-700 border-amber-200',
        };
    }
}
