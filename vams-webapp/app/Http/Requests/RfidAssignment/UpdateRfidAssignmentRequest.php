<?php

namespace App\Http\Requests\RfidAssignment;

use Illuminate\Contracts\Validation\Rule as ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRfidAssignmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Route-level 'can:rfid_assignments.update' middleware already gates this.
     *
     * Scope note: this only edits the assigned_at timestamp — the tag,
     * vehicle/visit target, and release are immutable/handled elsewhere
     * (create a new assignment, or use the dedicated release action) to
     * keep assignment history accurate.
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
            'assigned_at' => ['required', 'date'],
        ];
    }
}
