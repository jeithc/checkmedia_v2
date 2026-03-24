<?php

namespace App\Http\Requests\Api;

use App\Models\Audit;
use Illuminate\Foundation\Http\FormRequest;

class StoreAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $types = implode(',', [Audit::TYPE_GENERAL, Audit::TYPE_STRUCTURAL]);
        $purposes = implode(',', [
            Audit::PURPOSE_AUDIT_ONLY,
            Audit::PURPOSE_PREVENTIVE,
            Audit::PURPOSE_CORRECTIVE,
        ]);

        return [
            'external_code' => ['required', 'string', 'exists:advertising_spaces,external_code'],
            'audit_type' => ['sometimes', 'string', "in:{$types}"],
            'audit_purpose' => ['sometimes', 'string', "in:{$purposes}"],
            'values' => ['required', 'array', 'min:1'],
            'values.*.criterion_id' => ['required', 'integer', 'exists:audit_criteria,id'],
            'values.*.value' => ['required', 'string', 'in:good,bad'],
            'observation' => ['nullable', 'string', 'max:2000'],
            'photos' => ['required', 'array', 'min:1'],
            'photos.*' => ['image', 'max:10240'],
            'maintenance_category' => ['nullable', 'string', 'in:estructural,electrico,ambiental,material'],
        ];
    }

    public function messages(): array
    {
        return [
            'external_code.exists' => 'El código del espacio publicitario no existe.',
            'values.required' => 'Debe enviar al menos un criterio de auditoría.',
            'values.*.criterion_id.exists' => 'Uno de los criterios enviados no existe.',
            'photos.required' => 'Debe enviar al menos una foto.',
            'photos.min' => 'Debe enviar al menos una foto.',
            'photos.*.max' => 'Cada foto no debe superar los 10 MB.',
        ];
    }
}
