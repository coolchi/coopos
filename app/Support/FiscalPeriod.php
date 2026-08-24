<?php

namespace App\Support;

use App\Models\TenantSetting;
use Carbon\Carbon;

final class FiscalPeriod
{
    public function __construct(
        public readonly Carbon $start,
        public readonly Carbon $end,
    ) {}

    public static function startMonth(): int
    {
        return (int) (self::settings()?->fiscal_year_start_month ?: 1);
    }

    public static function endMonth(?int $startMonth = null): int
    {
        $startMonth ??= self::startMonth();

        return $startMonth === 1 ? 12 : $startMonth - 1;
    }

    public static function yearOneStartYear(?int $startMonth = null): int
    {
        $configured = self::settings()?->fiscal_year_one_start_year;

        if ($configured) {
            return (int) $configured;
        }

        return self::currentStartYear($startMonth);
    }

    public static function currentStartYear(?int $startMonth = null): int
    {
        $startMonth ??= self::startMonth();
        $now = now();

        return $now->month >= $startMonth ? $now->year : $now->year - 1;
    }

    public static function forYear(?int $year = null, ?int $startMonth = null): self
    {
        $startMonth ??= self::startMonth();
        $year ??= FiscalYearContext::startYear();
        $start = Carbon::create($year, $startMonth, 1)->startOfDay();
        $end = $start->copy()->addYear()->subDay()->endOfDay();

        return new self($start, $end);
    }

    public static function forDate(Carbon|string $date, ?int $startMonth = null): self
    {
        $startMonth ??= self::startMonth();
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);
        $year = $date->month >= $startMonth ? $date->year : $date->year - 1;

        return self::forYear($year, $startMonth);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function shared(): ?array
    {
        if (! TenantContext::id()) {
            return null;
        }

        try {
            $period = self::forYear(FiscalYearContext::startYear());

            return [
                'start_month' => self::startMonth(),
                'end_month' => self::endMonth(),
                'year' => $period->startYear(),
                'year_number' => $period->yearNumber(),
                'label' => $period->label(),
                'display' => $period->displayLabel(),
                'months_label' => $period->monthsLabel(),
                'range' => $period->rangeLabel(),
                'options' => FiscalYearContext::options(),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    public function previous(): self
    {
        return new self(
            $this->start->copy()->subYear(),
            $this->end->copy()->subYear(),
        );
    }

    public function label(): string
    {
        return $this->start->year === $this->end->year
            ? (string) $this->start->year
            : $this->start->year.'/'.$this->end->year;
    }

    public function yearNumber(): int
    {
        return max(1, $this->start->year - self::yearOneStartYear($this->start->month) + 1);
    }

    public function displayLabel(): string
    {
        return 'Year '.$this->yearNumber().' '.$this->label();
    }

    public function monthsLabel(): string
    {
        return self::monthName($this->start->month).' – '.self::monthName($this->end->month);
    }

    public function rangeLabel(): string
    {
        return $this->start->toFormattedDateString().' – '.$this->end->toFormattedDateString();
    }

    public function startYear(): int
    {
        return $this->start->year;
    }

    public static function monthName(int $month): string
    {
        return Carbon::create(null, $month, 1)->format('F');
    }

    private static function settings(): ?TenantSetting
    {
        $tenantId = TenantContext::id();

        return TenantSetting::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->first();
    }
}
