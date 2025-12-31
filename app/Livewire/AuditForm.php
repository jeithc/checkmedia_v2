<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\AdvertisingSpace;
use App\Models\Audit;
use App\Models\AuditCriterion;
use App\Models\AuditValue;
use App\Models\AuditPhoto;
use Carbon\Carbon;

class AuditForm extends Component
{
    use WithFileUploads;

    public $external_code;
    public $space;
    public $booking;
    public $criteria;

    // Form Inputs
    public $values = []; // [criterion_id => ['value' => 'good', 'comment' => '']]
    public $observation;
    public $photos = [];

    public function mount()
    {
        // Load active criteria once
        $this->criteria = AuditCriterion::where('is_active', true)
            ->orderBy('order_index')
            ->get();

        // Initialize default values
        foreach ($this->criteria as $criterion) {
            $this->values[$criterion->id] = [
                'value' => 'good',
                'comment' => ''
            ];
        }
    }

    public function searchSpace(\App\Services\AdvisualSyncService $syncService)
    {
        $this->validate([
            'external_code' => 'required'
        ]);

        // 1. Try Local Search
        $this->space = AdvertisingSpace::where('external_code', $this->external_code)->first();

        // 2. If not found, try External Sync
        if (!$this->space) {
            try {
                $this->space = $syncService->syncSpaceByCcde($this->external_code);
            } catch (\Exception $e) {
                // Log error but treat as not found for UI
            }
        }

        if (!$this->space) {
            $this->addError('external_code', 'Espacio no encontrado ni en local ni en remoto.');
            return;
        }

        // 3. Setup context (Booking)
        // If we just synced, the booking might have been created too. 
        // Or if it was local, we might need to Refresh booking from remote? 
        // For now, let's assume if it exists locally we trust it, OR we could force sync every time.
        // User Requirement: "buscardata2.php ... servia para buscar los ultimos datos ... importarlo para que tenga un mejor uso"
        // Better approach: ALWAYS try sync to get latest client data if connected.

        try {
            $syncedSpace = $syncService->syncSpaceByCcde($this->external_code);
            if ($syncedSpace)
                $this->space = $syncedSpace;
        } catch (\Exception $e) {
            // Fail silently if remote is down, use local cache
        }

        // Load Booking for current week
        $this->booking = $this->space->getBookingForDate(now());
    }

    public function save()
    {
        $this->validate([
            'space' => 'required',
            'photos.*' => 'image|max:10240', // 10MB Max per image
        ]);

        // 1. Create Audit
        $date = now();
        $audit = Audit::create([
            'advertising_space_id' => $this->space->id,
            'user_id' => auth()->id() ?? 1, // Fallback for dev without auth
            'year' => $date->year,
            'week' => $date->weekOfYear,
            'audit_date' => $date,
            'observation' => $this->observation,
            'general_status' => 'good' // Will update below
        ]);

        // 2. Save Values & Calculate Status
        $hasIssues = false;
        foreach ($this->values as $criterionId => $data) {
            AuditValue::create([
                'audit_id' => $audit->id,
                'audit_criterion_id' => $criterionId,
                'value' => $data['value'],
                'comment' => $data['comment'] ?? null
            ]);

            if ($data['value'] === 'bad') {
                $hasIssues = true;
            }
        }

        if ($hasIssues) {
            $audit->update(['general_status' => 'bad']);
        }

        // 3. Save Photos
        foreach ($this->photos as $photo) {
            $path = $photo->store('audit-photos', 'public'); // Store in storage/app/public/audit-photos

            AuditPhoto::create([
                'audit_id' => $audit->id,
                'file_path' => $path,
                'file_type' => 'image'
            ]);
        }

        // 4. Reset
        $this->reset(['space', 'external_code', 'photos', 'observation']);
        $this->mount(); // Reset values
        session()->flash('message', 'Auditoría guardada exitosamente.');
    }

    public function removePhoto($index)
    {
        array_splice($this->photos, $index, 1);
    }

    public function render()
    {
        return view('livewire.audit-form');
    }
}
