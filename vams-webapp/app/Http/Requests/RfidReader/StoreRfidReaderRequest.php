<?php

namespace App\Http\Requests\RfidReader;

use Illuminate\Contracts\Validation\Rule as ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRfidReaderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Route-level 'can:rfid_readers.create' middleware already gates this.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The api_key/api_secret_hash credentials are generated server-side in
     * the controller and are never accepted from client input.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'device_name' => ['required', 'string', 'max:255'],
            // device_code is auto-generated via model observer; not accepted from user input
            'model' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'ip_address' => ['nullable', 'ip'],
            'status' => ['required', Rule::in(['online', 'offline', 'disabled'])],
        ];
    }
}
