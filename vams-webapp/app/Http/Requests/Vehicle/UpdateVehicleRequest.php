<?php

namespace App\Http\Requests\Vehicle;

use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Route-level 'can:vehicles.update' middleware already gates this.
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
        $vehicle = $this->route('vehicle');

        return [
            // vehicle_code is read-only; cannot be changed after creation
            'plate_number' => ['required', 'string', 'max:20', Rule::unique('vehicles', 'plate_number')->ignore($vehicle)],
            // Accept the canonical list, plus whatever this record already holds,
            // so a value saved before the list existed can still be re-submitted
            // from the edit form instead of failing validation.
            'vehicle_type' => ['nullable', 'string', Rule::in(array_filter([...Vehicle::TYPES, $vehicle?->vehicle_type]))],
            'make' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:50'],
            'owner_type' => ['required', Rule::in(['employee', 'visitor'])],
            'employee_id' => ['nullable', 'required_if:owner_type,employee', Rule::exists('employees', 'id')],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
