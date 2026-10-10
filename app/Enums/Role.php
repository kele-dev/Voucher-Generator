<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'super_admin';
    case VoucherAdmin = 'voucher_admin';
    case OutletStaff = 'outlet_staff';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::VoucherAdmin => 'Admin Voucher',
            self::OutletStaff => 'Staf Outlet',
            self::Viewer => 'Viewer',
        };
    }

    /**
     * Roles allowed to create/update/delete master data and campaigns.
     */
    public function isManager(): bool
    {
        return in_array($this, [self::SuperAdmin, self::VoucherAdmin], true);
    }

    /**
     * Roles allowed to read reports/activity.
     */
    public function isStaff(): bool
    {
        return $this === self::OutletStaff;
    }
}
