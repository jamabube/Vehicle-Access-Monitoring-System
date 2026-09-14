<?php

namespace App\Http\Requests\RfidReader;

use Illuminate\Contracts\Validation\Rule as ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRfidReaderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Route-level 'can:rfid_readers.update' middleware already gates this.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Credentials (api_key/api_secret_hash) are never editable here — use
     * the dedicated regenerate-credentials action instead.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        $rfidReader = $this->route('rfid_reader');

        return [
            'device_name' => ['required', 'string', 'max:255'],
            // device_code is read-only; cannot be changed after creation
            'model' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'ip_address' => ['nullable', 'ip'],
            'status' => ['required', Rule::in(['online', 'offline', 'disabled'])],
        ];
    }
}
