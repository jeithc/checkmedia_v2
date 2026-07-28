<?php

namespace App\Livewire;

use App\Models\AdvertisingSpace;
use Livewire\Component;
use Livewire\WithPagination;

class SpaceBrowser extends Component
{
    // Filters
    public $filterProduct = '';

    public $filterCity = '';

    public $filterLocation = '';

    public $filterStatus = '';

    public $search = '';

    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public function setProduct($unit = '')
    {
        $this->filterProduct = $unit;
        $this->filterCity = '';
        $this->filterLocation = '';
        $this->resetPage();
    }

    public function updatedFilterCity()
    {
        $this->filterLocation = '';
        $this->resetPage();
    }

    public function updated($propertyName)
    {
        $this->resetPage();
    }

    public function render()
    {
        $applyOrthogonal = function ($q) {
            if (! empty($this->filterStatus)) {
                $q->whereHas('latestAudit', function ($subQ) {
                    $subQ->where('general_status', $this->filterStatus);
                });
            }
        };

        $applyProduct = function ($q) {
            if (! empty($this->filterProduct)) {
                $q->ofBusinessUnit($this->filterProduct);
            }
        };

        // 1. Load Cities (Dependent on Product + Orthogonal)
        $citiesQuery = AdvertisingSpace::select('city')
            ->distinct()
            ->whereNotNull('city');

        $applyProduct($citiesQuery);
        $applyOrthogonal($citiesQuery);

        $cities = $citiesQuery->orderBy('city')->pluck('city');

        // 2. Load Locations (Dependent on Product & City + Orthogonal)
        $locationsQuery = AdvertisingSpace::select('location_name')
            ->distinct()
            ->whereNotNull('location_name');

        $applyProduct($locationsQuery);
        if (! empty($this->filterCity)) {
            $locationsQuery->where('city', $this->filterCity);
        }
        $applyOrthogonal($locationsQuery);

        $locations = $locationsQuery->orderBy('location_name')->pluck('location_name');

        // 3. Main Query for Table
        $query = AdvertisingSpace::query()
            ->with('latestAudit');

        $applyProduct($query);

        if (! empty($this->filterCity)) {
            $query->where('city', $this->filterCity);
        }

        if (! empty($this->filterLocation)) {
            $query->where('location_name', $this->filterLocation);
        }

        // Apply shared orthogonal logic
        $applyOrthogonal($query);

        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->where('external_code', 'like', '%'.$this->search.'%')
                    ->orWhere('city', 'like', '%'.$this->search.'%')
                    ->orWhere('location', 'like', '%'.$this->search.'%')
                    ->orWhere('location_name', 'like', '%'.$this->search.'%');
            });
        }

        $spaces = $query->orderBy('external_code')
            ->paginate(15);

        return view('livewire.space-browser', [
            'spaces' => $spaces,
            'cities' => $cities,
            'locations' => $locations,
        ]);
    }
}
