<?php

namespace App\Enums;

enum VoucherType: string
{
    case VoucherMakan = 'VM';
    case VoucherDiskon = 'VD';

    public function label(): string
    {
        return match ($this) {
            self::VoucherMakan => 'Voucher Makan',
            self::VoucherDiskon => 'Voucher Diskon',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::VoucherMakan => 'Makan',
            self::VoucherDiskon => 'Diskon',
        };
    }
}
