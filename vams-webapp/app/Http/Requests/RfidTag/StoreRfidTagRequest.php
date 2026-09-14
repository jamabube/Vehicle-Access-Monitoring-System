<?php

namespace App\Http\Requests\RfidTag;

use Illuminate\Contracts\Validation\Rule as ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRfidTagRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Route-level 'can:rfid_tags.create' middleware already gates this.
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
            // tag_code is auto-generated via model observer; not accepted from user input
            'epc' => ['required', 'string', 'max:255', 'regex:/^[0-9A-Za-z]+(?:[\s:\-]*[0-9A-Za-z]+)*$/', Rule::unique('rfid_tags', 'epc')],
            'credential_type' => ['required', Rule::in(['sticker', 'card'])],
            'status' => ['required', Rule::in(['active', 'inactive', 'lost', 'disabled', 'expired'])],
            'issued_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
