<?php

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;

class RequisitionBatchStatusFilter extends Filter
{
    const ACTIVE = 'active';

    const CANCELLED = 'cancelled';

    const ALL = 'all';

    /**
     * Default hides cancelled batches: they are noise in day-to-day work.
     */
    const DEFAULT = self::ACTIVE;

    public function name(): string
    {
        return 'Estado';
    }

    public function parameters(): array
    {
        return ['status'];
    }

    /**
     * Orchid only runs a filter when its parameter is present in the request.
     * This one must run always so the default (hide cancelled) applies on a
     * bare /requisition-batches URL too.
     */
    public function isApply(): bool
    {
        return true;
    }

    public function run(Builder $builder): Builder
    {
        return match ($this->value()) {
            self::CANCELLED => $builder->whereNotNull('cancelled_at'),
            self::ALL => $builder,
            default => $builder->whereNull('cancelled_at'),
        };
    }

    /**
     * Rendered as chips by orchid.requisition-batch.status-filter, not by Orchid.
     */
    public function display(): array
    {
        return [];
    }

    /**
     * Same resolution as value(), usable from a Blade view without an instance.
     */
    public static function current(): string
    {
        $value = (string) request()->get('status', self::DEFAULT);

        return in_array($value, [self::ACTIVE, self::CANCELLED, self::ALL], true) ? $value : self::DEFAULT;
    }

    public function value(): string
    {
        return self::current();
    }
}
