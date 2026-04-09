<?php

namespace App\Livewire;

use App\Models\AdvertisingSpace;
use App\Models\Audit;
use App\Models\AuditCriterion;
use App\Models\AuditPhoto;
use App\Models\AuditValue;
use App\Models\Maintenance;
use App\Models\SpaceActivityLog;
use App\Services\ImageWatermarkService;
use App\Services\MaintenanceNotificationService;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

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

    public $auditType = 'general';

    // Purpose: audit_only, preventive_maintenance, corrective_maintenance
    public string $auditPurpose = 'audit_only';

    public bool $canSelectPurpose = false;

    public bool $isStructuralAuditor = false;

    public string $maintenanceCategory = '';

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
            ->forCategory($this->auditType)
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
        $user = auth()->user();

        if (!$user) {
            return redirect()->route('platform.login');
        }

        $isStructural = $user->hasAccess('audit.can_audit_structural');
        $isGeneral = $user->hasAnyAccess(['audit.can_audit']);

        if (! $isStructural && ! $isGeneral) {
            abort(403);
        }

        if ($isStructural && ! $isGeneral) {
            $this->auditType = Audit::TYPE_STRUCTURAL;
            $this->isStructuralAuditor = true;
        }

        $this->canSelectPurpose = $user->hasAccess('audit.can_select_purpose');

        $criteria = $this->criteria;
        foreach ($criteria as $criterion) {
            $this->criteriaList[] = [
                'id' => $criterion->id,
                'name' => $criterion->name,
                'key' => $criterion->key,
            ];
            $this->criteriaIds[] = $criterion->id;
            $this->values[$criterion->id] = [
                'value' => 'good',
            ];
        }
    }

    public function searchSpace()
    {
        $syncService = app(\App\Services\AdvisualSyncService::class);

        $this->validate([
            'external_code' => 'required',
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
        if (! $space) {
            try {
                $space = $syncService->syncSpaceByCcde($this->external_code);
            } catch (\Exception $e) {
                // Log error but treat as not found for UI
            }
        }

        if (! $space) {
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

        // DUPLICATE CHECK (by space + year + week + audit_type)
        $now = now();
        $weekData = Audit::getCalendarYearAndWeek($now);
        $existingAudit = Audit::where('advertising_space_id', $space->id)
            ->where('year', $weekData['year'])
            ->where('week', $weekData['week'])
            ->where('audit_type', $this->auditType)
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
        if (! $existingAudit) {
            return;
        }

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
        $this->auditPurpose = 'audit_only';
        $this->maintenanceCategory = '';

        foreach ($this->criteriaIds as $criterionId) {
            $this->values[$criterionId] = ['value' => 'good'];
        }

        unset($this->space, $this->existingAudit, $this->booking, $this->criteria);
    }

    public function save()
    {
        if (! auth()->user()->hasAnyAccess(['audit.can_audit', 'audit.can_audit_structural'])) {
            abort(403);
        }

        $effectivePurpose = $this->canSelectPurpose ? $this->auditPurpose : Audit::PURPOSE_AUDIT_ONLY;

        $rules = [
            'spaceId' => 'required',
            'photos.*' => 'image|max:10240',
        ];

        if ($effectivePurpose === Audit::PURPOSE_CORRECTIVE && ! $this->isStructuralAuditor) {
            $rules['maintenanceCategory'] = 'required|in:estructural,electrico,ambiental,material';
        }

        $this->validate($rules, [
            'maintenanceCategory.required' => 'Debe seleccionar la categoría del mantenimiento correctivo.',
        ]);

        $space = $this->space;
        $existingAudit = $this->existingAudit;

        $totalPhotos = count($this->photos);
        if ($existingAudit) {
            $totalPhotos += $existingAudit->photos->count();
        }

        if ($totalPhotos === 0) {
            $this->addError('photos', 'Debe registrar al menos una foto para guardar la auditoría.');

            return;
        }

        $hasIssues = collect($this->values)->contains('value', 'bad');
        if ($hasIssues && empty(trim($this->observation))) {
            $this->addError('observation', 'Debe explicar el detalle de la irregularidad en las observaciones.');

            return;
        }

        $date = now();
        $weekData = Audit::getCalendarYearAndWeek($date);
        $audit = Audit::updateOrCreate(
            [
                'advertising_space_id' => $space->id,
                'year' => $weekData['year'],
                'week' => $weekData['week'],
                'audit_type' => $this->auditType,
            ],
            [
                'user_id' => auth()->id() ?? 1,
                'audit_date' => $existingAudit ? $existingAudit->audit_date : $date,
                'audit_purpose' => $effectivePurpose,
                'observation' => $this->observation,
                'general_status' => 'good',
            ]
        );

        if ($existingAudit) {
            $audit->values()->delete();
        }

        $generalStatus = 'good';
        foreach ($this->values as $criterionId => $data) {
            AuditValue::create([
                'audit_id' => $audit->id,
                'audit_criterion_id' => $criterionId,
                'value' => $data['value'],
            ]);

            if ($data['value'] === 'bad') {
                $generalStatus = 'bad';
            }
        }

        $audit->update(['general_status' => $generalStatus]);

        $watermarkService = new ImageWatermarkService;
        $photoDateTime = $audit->audit_date ?? now();

        foreach ($this->photos as $photo) {
            $watermarkedPhoto = $watermarkService->addWatermark(
                $photo,
                $photoDateTime->format('Y-m-d g:i a')
            );

            $path = $watermarkedPhoto->store('audit-photos', 'public');

            AuditPhoto::create([
                'audit_id' => $audit->id,
                'file_path' => $path,
                'file_type' => 'image',
            ]);
        }

        $this->createMaintenanceIfNeeded($audit, $space, $effectivePurpose);

        $isNew = ! $existingAudit;
        $purposeLabel = $this->getPurposeLabel($effectivePurpose);
        SpaceActivityLog::log(
            spaceId: $space->id,
            type: $isNew ? SpaceActivityLog::TYPE_AUDIT_CREATED : SpaceActivityLog::TYPE_AUDIT_UPDATED,
            description: $isNew
                ? "Auditoría creada ({$purposeLabel}) con estado: {$generalStatus}"
                : "Auditoría actualizada ({$purposeLabel}). Estado: {$generalStatus}",
            auditId: $audit->id,
            metadata: [
                'general_status' => $generalStatus,
                'audit_purpose' => $effectivePurpose,
                'photos_count' => count($this->photos),
                'user_name' => auth()->user()?->name ?? 'Sistema',
            ],
            year: $weekData['year'],
            week: $weekData['week']
        );

        if ($generalStatus === 'bad') {
            app(MaintenanceNotificationService::class)->notify('audit_bad_created', $audit);
        }

        $this->resetForm(true);
        $this->dispatch('audit-saved');

        $flashMessage = 'Auditoría guardada exitosamente.';
        if ($effectivePurpose === Audit::PURPOSE_PREVENTIVE) {
            $flashMessage .= ' Mantenimiento preventivo registrado.';
        } elseif ($effectivePurpose === Audit::PURPOSE_CORRECTIVE) {
            $flashMessage .= ' Mantenimiento correctivo solicitado.';
        }

        session()->flash('message', $flashMessage);
    }

    protected function createMaintenanceIfNeeded(Audit $audit, AdvertisingSpace $space, string $purpose): void
    {
        if ($purpose === Audit::PURPOSE_AUDIT_ONLY) {
            return;
        }

        $category = $this->isStructuralAuditor
            ? strtolower($space->type ?? 'estructural')
            : $this->maintenanceCategory;

        if ($purpose === Audit::PURPOSE_PREVENTIVE) {
            Maintenance::create([
                'advertising_space_id' => $space->id,
                'audit_id' => $audit->id,
                'requested_by' => auth()->id(),
                'requested_at' => now(),
                'type' => Maintenance::TYPE_PREVENTIVE,
                'category' => $category,
                'status' => Maintenance::STATUS_CLOSED,
                'closed_by' => auth()->id(),
                'closed_at' => now(),
                'description' => 'Mantenimiento preventivo realizado durante auditoría #'.$audit->id,
            ]);
        } elseif ($purpose === Audit::PURPOSE_CORRECTIVE) {
            $maintenance = Maintenance::create([
                'advertising_space_id' => $space->id,
                'audit_id' => $audit->id,
                'requested_by' => auth()->id(),
                'requested_at' => now(),
                'type' => Maintenance::TYPE_CORRECTIVE,
                'category' => $category,
                'status' => Maintenance::STATUS_REPORTED,
                'description' => $audit->observation ?: 'Mantenimiento correctivo solicitado desde auditoría #'.$audit->id,
            ]);

            app(MaintenanceNotificationService::class)->notify('maintenance_requested', $maintenance);
        }
    }

    protected function getPurposeLabel(string $purpose): string
    {
        return match ($purpose) {
            Audit::PURPOSE_PREVENTIVE => 'Mant. Preventivo',
            Audit::PURPOSE_CORRECTIVE => 'Mant. Correctivo',
            default => 'Solo Auditoría',
        };
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
