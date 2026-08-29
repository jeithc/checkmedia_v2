<?php

namespace App\Livewire;

use App\Models\AdvertisingSpace;
use App\Models\Audit;
use App\Models\AuditCriterion;
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

    // Criterios bloqueados al complementar: ya estaban 'bad' y no pueden volver a 'good'
    public array $lockedCriteria = [];

    public $photos = [];

    public $evidencePdf = null; // solo estructural: PDF en vez de fotos

    public $observation;

    public $duplicateFound = false;

    public $showExistingDetails = false;

    public $auditType = 'general';

    // Purpose: audit_only, preventive_maintenance
    public string $auditPurpose = 'audit_only';

    public bool $canSelectPurpose = false;

    public bool $canDoPreventive = false;

    public bool $isStructuralAuditor = false;

    public bool $canSelectAuditType = false;

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
            ->appliesTo($this->auditType)
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
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        if (! $user) {
            return redirect()->route('platform.login');
        }

        $isStructural = $user->hasAccess('audit.can_audit_structural');
        $isGeneral = $user->hasAnyAccess(['audit.can_audit']);
        $isAdmin = $user->hasAccess('platform.index');

        if ($isStructural && ! $isGeneral) {
            $this->auditType = Audit::TYPE_STRUCTURAL;
            $this->isStructuralAuditor = true;
        }

        $this->canSelectAuditType = $isStructural && $isGeneral;

        $this->canSelectPurpose = $isStructural || $isGeneral || $isAdmin || $user->hasAccess('audit.can_select_purpose');

        // Mantenimiento preventivo: solo admin (auditor de campo no lo selecciona)
        $this->canDoPreventive = $isAdmin || $user->hasAccess('audit.can_select_purpose');

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
                'comment' => '',
            ];
        }
    }

    public function updatedAuditType(): void
    {
        unset($this->criteria);

        $this->evidencePdf = null;

        $this->criteriaIds = [];
        $this->criteriaList = [];
        $this->values = [];
        $this->lockedCriteria = [];

        foreach ($this->criteria as $criterion) {
            $this->criteriaList[] = [
                'id' => $criterion->id,
                'name' => $criterion->name,
                'key' => $criterion->key,
            ];
            $this->criteriaIds[] = $criterion->id;
            $this->values[$criterion->id] = ['value' => 'good', 'comment' => ''];
        }

        $this->duplicateFound = false;
        $this->existingAuditId = null;
        $this->showExistingDetails = false;

        if ($this->spaceId) {
            $space = AdvertisingSpace::find($this->spaceId);
            if ($space) {
                $weekData = Audit::getCalendarYearAndWeek(now());
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
        $this->evidencePdf = null;
        $this->observation = '';
        foreach ($this->criteriaIds as $criterionId) {
            $this->values[$criterionId] = ['value' => 'good', 'comment' => ''];
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

        // Load existing values y bloquear criterios ya marcados 'bad' para que no regresen a 'good'
        $this->lockedCriteria = [];
        foreach ($existingAudit->values as $val) {
            if (isset($this->values[$val->audit_criterion_id])) {
                $this->values[$val->audit_criterion_id]['value'] = $val->value;
                $this->values[$val->audit_criterion_id]['comment'] = $val->comment ?? '';
                if ($val->value === 'bad') {
                    $this->lockedCriteria[] = $val->audit_criterion_id;
                }
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
        $this->evidencePdf = null;
        $this->observation = '';
        $this->duplicateFound = false;
        $this->existingAuditId = null;
        $this->showExistingDetails = false;
        $this->auditPurpose = 'audit_only';
        $this->lockedCriteria = [];

        foreach ($this->criteriaIds as $criterionId) {
            $this->values[$criterionId] = ['value' => 'good', 'comment' => ''];
        }

        unset($this->space, $this->existingAudit, $this->booking, $this->criteria);
    }

    public function save()
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        if (! $user) {
            abort(403);
        }

        $effectivePurpose = $this->canSelectPurpose ? $this->auditPurpose : Audit::PURPOSE_AUDIT_ONLY;

        // Bloquear preventivo si user no tiene permiso (auditor de campo)
        if ($effectivePurpose === Audit::PURPOSE_PREVENTIVE && ! $this->canDoPreventive) {
            $effectivePurpose = Audit::PURPOSE_AUDIT_ONLY;
        }

        $rules = [
            'spaceId' => 'required',
            'photos.*' => 'image|max:10240',
            'evidencePdf' => 'nullable|file|mimes:pdf|max:20480',
        ];

        if ($this->auditType !== Audit::TYPE_STRUCTURAL) {
            $this->evidencePdf = null;
        }

        $this->validate($rules);

        $space = $this->space;
        $existingAudit = $this->existingAudit;

        $totalPhotos = count($this->photos);
        if ($existingAudit) {
            $totalPhotos += $existingAudit->photos->count();
        }

        if ($existingAudit && $existingAudit->photos->contains('file_type', 'pdf') && (count($this->photos) > 0 || $this->evidencePdf)) {
            $this->addError('photos', 'Esta auditoría ya tiene un PDF de evidencia; no se puede agregar más evidencia.');

            return;
        }

        if ($this->evidencePdf && $totalPhotos > 0) {
            $this->addError('photos', 'Envía fotos o un PDF, no ambos.');

            return;
        }

        if ($totalPhotos === 0 && ! $this->evidencePdf) {
            $this->addError('photos', 'Debe registrar al menos una foto'.($this->auditType === Audit::TYPE_STRUCTURAL ? ' o un PDF' : '').' para guardar la auditoría.');

            return;
        }

        $missingComment = false;
        foreach ($this->values as $criterionId => $data) {
            if (($data['value'] ?? null) === 'bad' && empty(trim($data['comment'] ?? ''))) {
                $this->addError("values.$criterionId.comment", 'Describe la irregularidad de este ítem.');
                $missingComment = true;
            }
        }
        if ($missingComment) {
            return;
        }

        // Bloquear que un criterio ya reportado 'bad' regrese a 'good' al complementar
        foreach ($this->lockedCriteria as $lockedId) {
            if (isset($this->values[$lockedId]) && $this->values[$lockedId]['value'] !== 'bad') {
                $this->addError('values', 'No se puede cambiar un criterio reportado como Malo a Bueno. Solo se permite degradar el estado.');

                return;
            }
        }

        $date = now();

        $data = new \App\Services\AuditSubmissionData(
            user: $user,
            space: $space,
            auditType: $this->auditType,
            purpose: $effectivePurpose,
            values: $this->values,
            observation: $this->observation,
            capturedAt: $existingAudit && $existingAudit->audit_date ? $existingAudit->audit_date : $date,
            photos: $this->photos,
            clientUuid: null,
            allowOverwriteExisting: true,
            evidencePdf: $this->evidencePdf,
        );

        $audit = app(\App\Services\AuditSubmissionService::class)->submit($data);

        $this->resetForm(true);
        $this->dispatch('audit-saved');

        $flashMessage = 'Auditoría guardada exitosamente.';
        if ($effectivePurpose === Audit::PURPOSE_PREVENTIVE) {
            $flashMessage .= ' Mantenimiento preventivo registrado.';
        }

        session()->flash('message', $flashMessage);
    }

    public function removePdf()
    {
        $this->evidencePdf = null;
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
