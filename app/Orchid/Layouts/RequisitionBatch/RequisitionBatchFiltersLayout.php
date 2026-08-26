<?php

namespace App\Orchid\Layouts\RequisitionBatch;

use App\Orchid\Filters\RequisitionBatchStatusFilter;
use Orchid\Filters\Filter;
use Orchid\Screen\Layouts\Selection;

class RequisitionBatchFiltersLayout extends Selection
{
    /**
     * @return string[]|Filter[]
     */
    public function filters(): array
    {
        return [
            RequisitionBatchStatusFilter::class,
        ];
    }
}
