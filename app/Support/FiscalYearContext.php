<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

final class FiscalYearContext
{
    private static ?int $startYear = null;

    private static bool $bypassed = false;

    public static function set(?int $startYear): void
    {
        self::$startYear = $startYear;
        self::$bypassed = false;
    }

    public static function startYear(): int
    {
        return self::$startYear ?? FiscalPeriod::currentStartYear();
    }

    public static function period(): FiscalPeriod
    {
        return FiscalPeriod::forYear(self::startYear());
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function between(): array
    {
        $period = self::period();

        return [$period->start->copy(), $period->end->copy()];
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function betweenDates(): array
    {
        [$start, $end] = self::between();

        return [$start->toDateString(), $end->toDateString()];
    }

    public static function constrain(Builder $query, string $column): Builder
    {
        if (self::$bypassed) {
            return $query;
        }

        [$start, $end] = self::between();

        return $query->whereBetween($query->getModel()->qualifyColumn($column), [$start, $end]);
    }

    /**
     * Prefer a business date when set; otherwise fall back to created_at.
     */
    public static function constrainDateOrCreated(Builder $query, string $dateColumn): Builder
    {
        if (self::$bypassed) {
            return $query;
        }

        [$start, $end] = self::between();
        $model = $query->getModel();
        $date = $model->qualifyColumn($dateColumn);
        $created = $model->qualifyColumn('created_at');

        return $query->where(function (Builder $inner) use ($date, $created, $start, $end) {
            $inner->whereBetween($date, [$start->toDateString(), $end->toDateString()])
                ->orWhere(function (Builder $fallback) use ($date, $created, $start, $end) {
                    $fallback->whereNull($date)->whereBetween($created, [$start, $end]);
                });
        });
    }

    public static function bypass(): void
    {
        self::$bypassed = true;
        self::$startYear = null;
    }

    public static function isBypassed(): bool
    {
        return self::$bypassed;
    }

    public static function forget(): void
    {
        self::$startYear = null;
        self::$bypassed = false;
    }

    public static function clamp(?int $year): int
    {
        $yearOne = FiscalPeriod::yearOneStartYear();
        $current = FiscalPeriod::currentStartYear();
        $year ??= $current;

        return max($yearOne, min($current, $year));
    }

    /**
     * @return list<array{year: int, year_number: int, display: string, label: string, current: bool}>
     */
    public static function options(): array
    {
        $yearOne = FiscalPeriod::yearOneStartYear();
        $current = FiscalPeriod::currentStartYear();
        $selected = self::startYear();
        $options = [];

        for ($year = $current; $year >= $yearOne; $year--) {
            $period = FiscalPeriod::forYear($year);
            $options[] = [
                'year' => $year,
                'year_number' => $period->yearNumber(),
                'display' => $period->displayLabel(),
                'label' => $period->label(),
                'current' => $year === $selected,
            ];
        }

        return $options;
    }
}
