<?php

namespace App\Http\Requests\VisitorVisit;

use Illuminate\Contracts\Validation\Rule as ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVisitorVisitRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Route-level 'can:visitor_visits.update' middleware already gates this.
     *
     * Scope note: this only edits correctable visit details (purpose,
     * host, vehicle link, and the valid_from/valid_until window) — the
     * visitor and the RFID card assignment are immutable here; use
     * check-out (releases the card) then a new check-in instead.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'vehicle_id' => ['nullable', Rule::exists('vehicles', 'id')->whereNull('deleted_at')],
            'purpose' => ['nullable', 'string', 'max:255'],
            'host_name' => ['nullable', 'string', 'max:255'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after:valid_from'],
        ];
    }
}
