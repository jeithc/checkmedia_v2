<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use App\Models\AdvertisingSpace;
use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Select;

class MaintenanceProductFilter extends Filter
{
    public function name(): string
    {
        return 'Producto';
    }

    public function parameters(): array
    {
        return ['product'];
    }

    public function run(Builder $builder): Builder
    {
        return $builder->whereHas('advertisingSpace', function (Builder $query) {
            $query->ofBusinessUnit($this->request->get('product'));
        });
    }

    public function display(): array
    {
        return [
            Select::make('product')
                ->options(array_combine(AdvertisingSpace::BUSINESS_UNITS, AdvertisingSpace::BUSINESS_UNITS))
                ->empty('Todos')
                ->value($this->request->get('product'))
                ->title('Producto'),
        ];
    }

    public function value(): string
    {
        return $this->name().': '.$this->request->get('product');
    }
}
