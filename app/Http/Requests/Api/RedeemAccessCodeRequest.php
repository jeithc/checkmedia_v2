<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class RedeemAccessCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:12'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Debe ingresar un código de acceso.',
            'code.max' => 'El código de acceso no es válido.',
        ];
    }
}
