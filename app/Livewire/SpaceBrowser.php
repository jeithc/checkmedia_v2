<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\AdvertisingSpace;
use Illuminate\Database\Eloquent\Builder;

class SpaceBrowser extends Component
{
    // Filters
    public $filterCategory = '';
    public $filterCity = '';
    public $filterLocation = '';
    public $filterStatus = '';
    public $search = '';

    use WithPagination;
    protected $paginationTheme = 'bootstrap';

    public function updatedFilterCategory()
    {
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
            if (!empty($this->filterStatus)) {
                $q->whereHas('latestAudit', function ($subQ) {
                    $subQ->where('general_status', $this->filterStatus);
                });
            }
        };

        // 1. Load Categories (Filtered by Status)
        $categoriesQuery = AdvertisingSpace::select('category')
            ->distinct()
            ->whereNotNull('category');
        $applyOrthogonal($categoriesQuery);
        $categories = $categoriesQuery->orderBy('category')->pluck('category');

        // 2. Load Cities (Dependent on Category + Orthogonal)
        $citiesQuery = AdvertisingSpace::select('city')
            ->distinct()
            ->whereNotNull('city');
            
        if (!empty($this->filterCategory)) {
            $citiesQuery->where('category', $this->filterCategory);
        }
        $applyOrthogonal($citiesQuery);
        
        $cities = $citiesQuery->orderBy('city')->pluck('city');

        // 3. Load Locations (Dependent on Category & City + Orthogonal)
        $locationsQuery = AdvertisingSpace::select('location_name')
            ->distinct()
            ->whereNotNull('location_name');

        if (!empty($this->filterCategory)) {
            $locationsQuery->where('category', $this->filterCategory);
        }
        if (!empty($this->filterCity)) {
            $locationsQuery->where('city', $this->filterCity);
        }
        $applyOrthogonal($locationsQuery);

        $locations = $locationsQuery->orderBy('location_name')->pluck('location_name');


        // 4. Main Query for Table
        $query = AdvertisingSpace::query()
            ->with('latestAudit');

        if (!empty($this->filterCategory)) {
            $query->where('category', $this->filterCategory);
        }

        if (!empty($this->filterCity)) {
            $query->where('city', $this->filterCity);
        }

        if (!empty($this->filterLocation)) {
            $query->where('location_name', $this->filterLocation);
        }

        // Apply shared orthogonal logic
        $applyOrthogonal($query);

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('external_code', 'like', '%' . $this->search . '%')
                  ->orWhere('city', 'like', '%' . $this->search . '%')
                  ->orWhere('location', 'like', '%' . $this->search . '%')
                  ->orWhere('location_name', 'like', '%' . $this->search . '%');
            });
        }

        $spaces = $query->orderBy('external_code')
            ->paginate(15);

        return view('livewire.space-browser', [
            'spaces' => $spaces,
            'categories' => $categories,
            'cities' => $cities,
            'locations' => $locations
        ]);
    }
}
