<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CreateAccessCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'max_uses' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    public function messages(): array
    {
        return [
            'label.required' => 'Debe indicar un nombre o descripción para el código.',
            'expires_at.after' => 'La fecha de expiración debe ser futura.',
        ];
    }
}
