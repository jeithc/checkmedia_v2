<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\AdvertisingSpace;
use App\Models\Audit;
use App\Models\AuditCriterion;
use App\Models\AuditValue;
use App\Models\AuditPhoto;
use App\Models\SpaceActivityLog;
use App\Services\ImageWatermarkService;
use Carbon\Carbon;

class AuditForm extends Component
{
    use WithFileUploads;

    public $external_code;
    public $space;
    public $booking;
    public $criteria;

    // Form Inputs
    public $values = []; // [criterion_id => ['value' => 'good']]
    public $photos = [];
    public $observation;

    public $duplicateFound = false;
    public $existingAudit = null;
    public $showExistingDetails = false;

    public function mount()
    {
        // Load active criteria once
        $this->criteria = AuditCriterion::where('is_active', true)
            ->orderBy('order_index')
            ->get();

        // Initialize default values
        foreach ($this->criteria as $criterion) {
            $this->values[$criterion->id] = [
                'value' => 'good'
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

        // DUPLICATE CHECK
        $now = now();
        $weekData = Audit::getCalendarYearAndWeek($now);
        $this->existingAudit = Audit::where('advertising_space_id', $this->space->id)
            ->where('year', $weekData['year'])
            ->where('week', $weekData['week'])
            ->first();

        if ($this->existingAudit) {
            $this->duplicateFound = true;
        }
    }

    public function viewAudit()
    {
        $this->showExistingDetails = true;
    }

    public function complementAudit()
    {
        if (!$this->existingAudit)
            return;

        $this->showExistingDetails = false;
        $this->observation = $this->existingAudit->observation;

        // Load existing values
        foreach ($this->existingAudit->values as $val) {
            if (isset($this->values[$val->audit_criterion_id])) {
                $this->values[$val->audit_criterion_id]['value'] = $val->value;
            }
        }

        $this->duplicateFound = false;
    }

    public function reuploadAudit()
    {
        $this->showExistingDetails = false;
        $this->duplicateFound = false;
        $this->resetForm(false); // Reset but keep the space
    }

    protected function resetForm($resetSpace = true)
    {
        if ($resetSpace) {
            $this->space = null;
            $this->booking = null;
            $this->external_code = '';
        }

        $this->photos = [];
        $this->observation = '';
        $this->duplicateFound = false;
        $this->existingAudit = null;
        $this->showExistingDetails = false;

        foreach ($this->criteria as $c) {
            $this->values[$c->id] = ['value' => 'good'];
        }
    }

    public function save()
    {
        $this->validate([
            'space' => 'required',
            'photos.*' => 'image|max:10240', // 10MB Max per image
        ]);

        // Custom Validation: Photos
        $totalPhotos = count($this->photos);
        if ($this->existingAudit) {
            $totalPhotos += $this->existingAudit->photos->count();
        }

        if ($totalPhotos === 0) {
            $this->addError('photos', 'Debe registrar al menos una foto para guardar la auditoría.');
            return;
        }

        // Custom Validation: Observation required if any "bad" value
        $hasIssues = collect($this->values)->contains('value', 'bad');
        if ($hasIssues && empty(trim($this->observation))) {
            $this->addError('observation', 'Debe explicar el detalle de la irregularidad en las observaciones.');
            return;
        }

        // 1. Create or Update Audit
        $date = now();
        $weekData = Audit::getCalendarYearAndWeek($date);
        $audit = Audit::updateOrCreate(
            [
                'advertising_space_id' => $this->space->id,
                'year' => $weekData['year'],
                'week' => $weekData['week'],
            ],
            [
                'user_id' => auth()->id() ?? 1,
                'audit_date' => $this->existingAudit ? $this->existingAudit->audit_date : $date,
                'observation' => $this->observation,
                'general_status' => 'good'
            ]
        );

        // Clear existing values if updating
        if ($this->existingAudit) {
            $audit->values()->delete();
            // Note: Photos are tricky. For now we append new ones, 
            // but normally re-upload might mean replacing.
            // Leaving photos as is for now.
        }

        // 2. Save Values & Calculate Status
        $generalStatus = 'good';
        foreach ($this->values as $criterionId => $data) {
            AuditValue::create([
                'audit_id' => $audit->id,
                'audit_criterion_id' => $criterionId,
                'value' => $data['value']
            ]);

            if ($data['value'] === 'bad') {
                $generalStatus = 'bad';
            } elseif ($data['value'] === 'acceptable' && $generalStatus !== 'bad') {
                $generalStatus = 'acceptable';
            }
        }

        $audit->update(['general_status' => $generalStatus]);

        // 3. Save Photos with watermark
        $watermarkService = new ImageWatermarkService();
        $photoDateTime = $audit->audit_date ?? now();
        
        foreach ($this->photos as $photo) {
            // Add watermark with audit date/time
            $watermarkedPhoto = $watermarkService->addWatermark(
                $photo, 
                $photoDateTime->format('Y-m-d g:i a')
            );
            
            $path = $watermarkedPhoto->store('audit-photos', 'public'); // Store in storage/app/public/audit-photos

            AuditPhoto::create([
                'audit_id' => $audit->id,
                'file_path' => $path,
                'file_type' => 'image'
            ]);
        }

        // 4. Log Activity
        $isNew = !$this->existingAudit;
        SpaceActivityLog::log(
            spaceId: $this->space->id,
            type: $isNew ? SpaceActivityLog::TYPE_AUDIT_CREATED : SpaceActivityLog::TYPE_AUDIT_UPDATED,
            description: $isNew 
                ? "Auditoría creada con estado: {$generalStatus}" 
                : "Auditoría actualizada. Estado: {$generalStatus}",
            auditId: $audit->id,
            metadata: [
                'general_status' => $generalStatus,
                'photos_count' => count($this->photos),
                'user_name' => auth()->user()?->name ?? 'Sistema',
            ],
            year: $weekData['year'],
            week: $weekData['week']
        );

        // 5. Reset
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
        try {
            return view('livewire.audit-form');
        } catch (\Exception $e) {
            dd([
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
