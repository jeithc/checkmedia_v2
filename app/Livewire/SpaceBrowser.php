<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\AdvertisingSpace;
use Illuminate\Database\Eloquent\Builder;

class SpaceBrowser extends Component
{
    use WithPagination;

    // Filters
    public $filterCategory = ''; // Replaces activeTab
    public $filterCity = '';
    public $filterLocation = '';
    public $filterStatus = '';
    public $search = '';

    // Data Lists
    public $categories = [];
    public $cities = [];
    public $locations = [];
    // public $providers = []; // Removed

    public function mount()
    {
        $this->loadOptions();
    }

    public function loadOptions()
    {
        // Load categories
        $this->categories = AdvertisingSpace::select('category')
            ->distinct()
            ->whereNotNull('category')
            ->orderBy('category')
            ->pluck('category')
            ->toArray();

        // Load cities
        $this->cities = AdvertisingSpace::select('city')
            ->distinct()
            ->whereNotNull('city')
            ->orderBy('city')
            ->pluck('city')
            ->toArray();

        // Load locations
        $this->locations = AdvertisingSpace::select('location_name')
            ->distinct()
            ->whereNotNull('location_name')
            ->orderBy('location_name')
            ->pluck('location_name')
            ->toArray();
    }

    public function updated($propertyName)
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = AdvertisingSpace::query()
            ->with('latestAudit');

        // 1. Category Filter
        if (!empty($this->filterCategory)) {
            $query->where('category', $this->filterCategory);
        }

        // 2. City Filter
        if (!empty($this->filterCity)) {
            $query->where('city', $this->filterCity);
        }

        // 3. Location Filter
        if (!empty($this->filterLocation)) {
            $query->where('location_name', $this->filterLocation);
        }

        if (!empty($this->filterStatus)) {
            $query->whereHas('latestAudit', function ($q) {
                $q->where('general_status', $this->filterStatus);
            });
        }

        // 4. Search Filter
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
            'spaces' => $spaces
        ]);
    }
}
