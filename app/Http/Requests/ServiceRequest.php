<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canOperate() ?? false;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'exists:patients,id'],
            'carer_id' => ['required', 'exists:carers,id'],
            'starts_at' => ['required', 'date'],
            'duration_hours' => ['required', 'integer', 'min:1', 'max:24'],
            'hourly_rate' => ['required', 'numeric', 'min:0', 'max:999999.99'],
        ];
    }
}
