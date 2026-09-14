<?php

namespace App\Http\Requests\Vehicle;

use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehicleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Route-level 'can:vehicles.create' middleware already gates this.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        return [
            // vehicle_code is auto-generated via model observer; not accepted from user input
            'plate_number' => ['required', 'string', 'max:20', Rule::unique('vehicles', 'plate_number')],
            'vehicle_type' => ['nullable', 'string', Rule::in(Vehicle::TYPES)],
            'make' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:50'],
            'owner_type' => ['required', Rule::in(['employee', 'visitor'])],
            'employee_id' => ['nullable', 'required_if:owner_type,employee', Rule::exists('employees', 'id')],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
