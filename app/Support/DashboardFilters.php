<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * The validated dashboard/report filter values.
 */
class DashboardFilters
{
    public function __construct(
        public readonly ?int $brandId = null,
        public readonly ?int $campaignId = null,
        public readonly ?string $from = null,
        public readonly ?string $to = null,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            brandId: $request->integer('brand_id') ?: null,
            campaignId: $request->integer('campaign_id') ?: null,
            from: self::string($request->query('from')),
            to: self::string($request->query('to')),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function query(): array
    {
        return array_filter([
            'from' => $this->from,
            'to' => $this->to,
            'brand_id' => $this->brandId,
            'campaign_id' => $this->campaignId,
        ], fn ($value): bool => $value !== null && $value !== '');
    }

    private static function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
