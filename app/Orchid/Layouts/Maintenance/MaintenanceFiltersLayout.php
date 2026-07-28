<?php

namespace App\Orchid\Layouts\Maintenance;

use App\Orchid\Filters\MaintenanceProductFilter;
use Orchid\Filters\Filter;
use Orchid\Screen\Layouts\Selection;

class MaintenanceFiltersLayout extends Selection
{
    /**
     * @return string[]|Filter[]
     */
    public function filters(): array
    {
        return [
            MaintenanceProductFilter::class,
        ];
    }
}
