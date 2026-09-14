<?php

namespace App\Http\Requests\RfidTag;

use Illuminate\Contracts\Validation\Rule as ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRfidTagRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Route-level 'can:rfid_tags.update' middleware already gates this.
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
        $rfidTag = $this->route('rfid_tag');

        return [
            // tag_code is read-only; cannot be changed after creation
            'epc' => ['required', 'string', 'max:255', 'regex:/^[0-9A-Za-z]+(?:[\s:\-]*[0-9A-Za-z]+)*$/', Rule::unique('rfid_tags', 'epc')->ignore($rfidTag)],
            'credential_type' => ['required', Rule::in(['sticker', 'card'])],
            'status' => ['required', Rule::in(['active', 'inactive', 'lost', 'disabled', 'expired'])],
            'issued_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
