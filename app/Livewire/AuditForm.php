<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Computed;
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

    // Store only IDs for Livewire serialization
    public ?int $spaceId = null;
    public ?int $existingAuditId = null;

    // These are arrays/scalars — safe for Livewire
    public $criteriaIds = [];
    public $criteriaList = []; // Array of ['id' => ..., 'name' => ..., 'key' => ...]
    public $bookingData = null; // Array or null

    // Form Inputs
    public $values = []; // [criterion_id => ['value' => 'good']]
    public $photos = [];
    public $observation;

    public $duplicateFound = false;
    public $showExistingDetails = false;

    #[Computed]
    public function space()
    {
        return $this->spaceId ? AdvertisingSpace::find($this->spaceId) : null;
    }

    #[Computed]
    public function existingAudit()
    {
        return $this->existingAuditId ? Audit::find($this->existingAuditId) : null;
    }

    #[Computed]
    public function criteria()
    {
        return AuditCriterion::where('is_active', true)
            ->orderBy('order_index')
            ->get();
    }

    #[Computed]
    public function booking()
    {
        if ($this->spaceId) {
            $space = $this->space;
            return $space ? $space->getBookingForDate(now()) : null;
        }
        return null;
    }

    public function mount()
    {
        if (!auth()->user()->hasAnyAccess(['audit.can_audit'])) {
            abort(403);
        }

        // Load active criteria and initialize values
        $criteria = $this->criteria;
        foreach ($criteria as $criterion) {
            $this->criteriaList[] = [
                'id' => $criterion->id,
                'name' => $criterion->name,
                'key' => $criterion->key,
            ];
            $this->criteriaIds[] = $criterion->id;
            $this->values[$criterion->id] = [
                'value' => 'good'
            ];
        }
    }

    public function searchSpace()
    {
        $syncService = app(\App\Services\AdvisualSyncService::class);

        $this->validate([
            'external_code' => 'required'
        ]);

        // Reset duplicate state before new search
        $this->duplicateFound = false;
        $this->existingAuditId = null;
        $this->showExistingDetails = false;
        $this->spaceId = null;
        $this->bookingData = null;

        // Clear form data for the new search
        $this->photos = [];
        $this->observation = '';
        foreach ($this->criteriaIds as $criterionId) {
            $this->values[$criterionId] = ['value' => 'good'];
        }

        // 1. Try Local Search
        $space = AdvertisingSpace::where('external_code', $this->external_code)->first();

        // 2. If not found, try External Sync
        if (!$space) {
            try {
                $space = $syncService->syncSpaceByCcde($this->external_code);
            } catch (\Exception $e) {
                // Log error but treat as not found for UI
            }
        }

        if (!$space) {
            $this->addError('external_code', 'Espacio no encontrado ni en local ni en remoto.');
            return;
        }

        // Always try sync to get latest client data
        try {
            $syncedSpace = $syncService->syncSpaceByCcde($this->external_code);
            if ($syncedSpace) {
                $space = $syncedSpace;
            }
        } catch (\Exception $e) {
            // Fail silently if remote is down, use local cache
        }

        // Store only the ID
        $this->spaceId = $space->id;

        // Load Booking for current week as array data
        $booking = $space->getBookingForDate(now());
        if ($booking) {
            $this->bookingData = [
                'id' => $booking->id,
                'client_name' => $booking->client_name,
                'contract_code' => $booking->contract_code,
                'product_name' => $booking->product_name,
            ];
        }

        // DUPLICATE CHECK
        $now = now();
        $weekData = Audit::getCalendarYearAndWeek($now);
        $existingAudit = Audit::where('advertising_space_id', $space->id)
            ->where('year', $weekData['year'])
            ->where('week', $weekData['week'])
            ->first();

        if ($existingAudit) {
            $this->existingAuditId = $existingAudit->id;
            $this->duplicateFound = true;
        }
    }

    public function viewAudit()
    {
        $this->showExistingDetails = true;
    }

    public function complementAudit()
    {
        $existingAudit = $this->existingAudit;
        if (!$existingAudit) return;

        $this->showExistingDetails = false;
        $this->observation = $existingAudit->observation;

        // Load existing values
        foreach ($existingAudit->values as $val) {
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
            $this->spaceId = null;
            $this->bookingData = null;
            $this->external_code = '';
        }

        $this->photos = [];
        $this->observation = '';
        $this->duplicateFound = false;
        $this->existingAuditId = null;
        $this->showExistingDetails = false;

        foreach ($this->criteriaIds as $criterionId) {
            $this->values[$criterionId] = ['value' => 'good'];
        }

        // Clear computed property caches so the template re-evaluates them
        unset($this->space, $this->existingAudit, $this->booking, $this->criteria);
    }

    public function save()
    {
        if (!auth()->user()->hasAnyAccess(['audit.can_audit'])) {
            abort(403);
        }

        $this->validate([
            'spaceId' => 'required',
            'photos.*' => 'image|max:10240', // 10MB Max per image
        ]);

        $space = $this->space;
        $existingAudit = $this->existingAudit;

        // Custom Validation: Photos
        $totalPhotos = count($this->photos);
        if ($existingAudit) {
            $totalPhotos += $existingAudit->photos->count();
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
                'advertising_space_id' => $space->id,
                'year' => $weekData['year'],
                'week' => $weekData['week'],
            ],
            [
                'user_id' => auth()->id() ?? 1,
                'audit_date' => $existingAudit ? $existingAudit->audit_date : $date,
                'observation' => $this->observation,
                'general_status' => 'good'
            ]
        );

        // Clear existing values if updating
        if ($existingAudit) {
            $audit->values()->delete();
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

            $path = $watermarkedPhoto->store('audit-photos', 'public');

            AuditPhoto::create([
                'audit_id' => $audit->id,
                'file_path' => $path,
                'file_type' => 'image'
            ]);
        }

        // 4. Log Activity
        $isNew = !$existingAudit;
        SpaceActivityLog::log(
            spaceId: $space->id,
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
        $this->resetForm(true);
        $this->dispatch('audit-saved');
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
