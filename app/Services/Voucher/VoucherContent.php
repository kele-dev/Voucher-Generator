<?php

namespace App\Services\Voucher;

use App\Models\Campaign;

/**
 * Turns the structured campaign content (menu, portions, schedule) into the
 * strings printed on a voucher, and normalises/validates raw form input.
 */
class VoucherContent
{
    /**
     * Slots the structured content can fill, in display order.
     *
     * @var list<string>
     */
    public const SLOTS = [
        'free_portion',
        'menu_items',
        'min_purchase',
        'normal_value',
        'service_type',
        'valid_schedule',
    ];

    /**
     * @var array<int, string>
     */
    public const DAYS = [
        1 => 'Sen',
        2 => 'Sel',
        3 => 'Rab',
        4 => 'Kam',
        5 => 'Jum',
        6 => 'Sab',
        7 => 'Min',
    ];

    /**
     * @var array<string, string>
     */
    public const SERVICE_TYPES = [
        'any' => 'Semua layanan',
        'dine_in' => 'Dine-in (makan di tempat)',
        'takeaway' => 'Takeaway (dibawa pulang)',
    ];

    public const MAX_MENU_ITEMS = 10;

    /**
     * Build the issue-time snapshot of a campaign's structured content, or
     * null when there is nothing structured to freeze.
     *
     * @return array<string, mixed>|null
     */
    public static function snapshot(Campaign $campaign): ?array
    {
        $snapshot = [
            'menu_items' => self::menuFromArray($campaign->menu_items),
            'min_purchase' => $campaign->min_purchase !== null ? (int) $campaign->min_purchase : null,
            'normal_value' => $campaign->normal_value !== null ? (int) $campaign->normal_value : null,
            'service_type' => $campaign->service_type ?: 'any',
            'valid_days' => self::daysFromArray($campaign->valid_days),
            'valid_time_from' => $campaign->valid_time_from,
            'valid_time_until' => $campaign->valid_time_until,
        ];

        return self::isMeaningful($snapshot) ? $snapshot : null;
    }

    /**
     * Sanitise raw (untrusted) form input into a normalised snapshot shape.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function fromInput(array $input): array
    {
        return [
            'menu_items' => self::menuFromArray($input['menu_items'] ?? null),
            'min_purchase' => self::positiveInt($input['min_purchase'] ?? null),
            'normal_value' => self::positiveInt($input['normal_value'] ?? null),
            'service_type' => self::normaliseServiceType($input['service_type'] ?? null),
            'valid_days' => self::daysFromArray($input['valid_days'] ?? null),
            'valid_time_from' => self::validTime($input['valid_time_from'] ?? null),
            'valid_time_until' => self::validTime($input['valid_time_until'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $snapshot
     * @return array<string, string>
     */
    public static function texts(?array $snapshot): array
    {
        $snapshot ??= [];
        $items = $snapshot['menu_items'] ?? [];

        $lines = [];
        $portions = 0;

        foreach ((array) $items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $name = trim((string) ($item['name'] ?? ''));
            $qty = (int) ($item['qty'] ?? 0);

            if ($name === '' || $qty < 1) {
                continue;
            }

            $lines[] = $qty.'x '.$name;
            $portions += $qty;
        }

        return [
            'free_portion' => $portions > 0 ? 'GRATIS '.$portions.' PORSI' : '',
            'menu_items' => implode(' · ', $lines),
            'min_purchase' => self::money('Min. belanja Rp', $snapshot['min_purchase'] ?? null),
            'normal_value' => self::money('Senilai Rp', $snapshot['normal_value'] ?? null),
            'service_type' => match ($snapshot['service_type'] ?? 'any') {
                'dine_in' => 'Khusus dine-in',
                'takeaway' => 'Khusus takeaway',
                default => '',
            },
            'valid_schedule' => self::schedule(
                $snapshot['valid_days'] ?? null,
                $snapshot['valid_time_from'] ?? null,
                $snapshot['valid_time_until'] ?? null,
            ),
        ];
    }

    /**
     * Label/value pairs for the voucher detail page.
     *
     * @param  array<string, mixed>|null  $snapshot
     * @return array<int, array{label: string, value: string}>
     */
    public static function lines(?array $snapshot): array
    {
        $texts = self::texts($snapshot);
        $labels = [
            'menu_items' => 'Menu',
            'free_portion' => 'Porsi gratis',
            'min_purchase' => 'Minimal belanja',
            'normal_value' => 'Nilai normal',
            'service_type' => 'Layanan',
            'valid_schedule' => 'Jadwal berlaku',
        ];

        $lines = [];

        foreach (self::SLOTS as $slot) {
            if (($texts[$slot] ?? '') !== '') {
                $lines[] = ['label' => $labels[$slot], 'value' => $texts[$slot]];
            }
        }

        return $lines;
    }

    /**
     * @param  array<int, mixed>|null  $days
     */
    public static function schedule(?array $days, ?string $from, ?string $until): string
    {
        $dayText = '';

        if (is_array($days)) {
            $clean = [];

            foreach ($days as $day) {
                if (is_numeric($day)) {
                    $day = (int) $day;

                    if ($day >= 1 && $day <= 7) {
                        $clean[$day] = $day;
                    }
                }
            }

            $clean = array_values($clean);
            sort($clean);

            if ($clean !== [] && count($clean) < 7) {
                $dayText = implode(', ', self::dayRuns($clean));
            }
        }

        $timeText = '';
        $from = self::formatTime($from);
        $until = self::formatTime($until);

        if ($from !== null && $until !== null) {
            $timeText = $from.'–'.$until;
        }

        return match (true) {
            $dayText !== '' && $timeText !== '' => $dayText.' · '.$timeText,
            $dayText !== '' => $dayText,
            default => $timeText,
        };
    }

    /**
     * @param  array<int, int>  $days  sorted, unique
     * @return array<int, string>
     */
    private static function dayRuns(array $days): array
    {
        $parts = [];
        $count = count($days);
        $i = 0;

        while ($i < $count) {
            $start = $days[$i];
            $end = $start;

            while ($i + 1 < $count && $days[$i + 1] === $end + 1) {
                $i++;
                $end = $days[$i];
            }

            if ($end - $start + 1 >= 3) {
                $parts[] = self::DAYS[$start].'–'.self::DAYS[$end];
            } else {
                for ($d = $start; $d <= $end; $d++) {
                    $parts[] = self::DAYS[$d];
                }
            }

            $i++;
        }

        return $parts;
    }

    /**
     * @param  mixed  $items
     * @return array<int, array{name: string, qty: int}>|null
     */
    private static function menuFromArray(mixed $items): ?array
    {
        if (! is_array($items)) {
            return null;
        }

        $menu = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $name = trim((string) ($item['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $qty = self::positiveInt($item['qty'] ?? null) ?? 1;

            $menu[] = [
                'name' => mb_substr($name, 0, 60),
                'qty' => min(99, $qty),
            ];

            if (count($menu) >= self::MAX_MENU_ITEMS) {
                break;
            }
        }

        return $menu === [] ? null : $menu;
    }

    /**
     * @param  mixed  $days
     * @return array<int, int>|null
     */
    private static function daysFromArray(mixed $days): ?array
    {
        if (! is_array($days)) {
            return null;
        }

        $clean = [];

        foreach ($days as $day) {
            if (is_numeric($day)) {
                $day = (int) $day;

                if ($day >= 1 && $day <= 7) {
                    $clean[$day] = $day;
                }
            }
        }

        $clean = array_values($clean);
        sort($clean);

        return $clean === [] ? null : $clean;
    }

    private static function normaliseServiceType(mixed $value): string
    {
        return is_string($value) && array_key_exists($value, self::SERVICE_TYPES)
            ? $value
            : 'any';
    }

    private static function positiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $value = (int) $value;

        return $value > 0 ? $value : null;
    }

    private static function validTime(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])$/', $value)) {
            return null;
        }

        return $value;
    }

    private static function formatTime(?string $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])$/', $value, $m)) {
            return null;
        }

        return $m[1].'.'.$m[2];
    }

    private static function money(string $prefix, mixed $value): string
    {
        if (! is_numeric($value) || (int) $value <= 0) {
            return '';
        }

        return $prefix.number_format((int) $value, 0, ',', '.');
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private static function isMeaningful(array $snapshot): bool
    {
        return $snapshot['menu_items'] !== null
            || $snapshot['min_purchase'] !== null
            || $snapshot['normal_value'] !== null
            || $snapshot['service_type'] !== 'any'
            || $snapshot['valid_days'] !== null
            || $snapshot['valid_time_from'] !== null
            || $snapshot['valid_time_until'] !== null;
    }
}
