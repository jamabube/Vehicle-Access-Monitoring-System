<?php

namespace App\Http\Requests\VisitorVisit;

use App\Models\RfidAssignment;
use Illuminate\Contracts\Validation\Rule as ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreVisitorVisitRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Route-level 'can:visitor_visits.create' middleware already gates this.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Checking in a visitor optionally links a vehicle and/or an RFID card
     * (via 'rfid_tag_id', not a direct rfid_assignments write — the
     * controller creates the RfidAssignment row itself).
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'visitor_id' => ['required', Rule::exists('visitors', 'id')->whereNull('deleted_at')],
            'vehicle_id' => ['nullable', Rule::exists('vehicles', 'id')->whereNull('deleted_at')],
            'rfid_tag_id' => ['nullable', Rule::exists('rfid_tags', 'id')->where('credential_type', 'card')],
            'purpose' => ['nullable', 'string', 'max:255'],
            'host_name' => ['nullable', 'string', 'max:255'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after:valid_from'],
        ];
    }

    /**
     * Configure the validator instance.
     *
     * Rejects checking in with a card that already has an active
     * (unreleased) assignment — it must be released first.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->filled('rfid_tag_id') || $validator->errors()->has('rfid_tag_id')) {
                return;
            }

            $hasActiveAssignment = RfidAssignment::query()
                ->where('rfid_tag_id', $this->input('rfid_tag_id'))
                ->whereNull('released_at')
                ->exists();

            if ($hasActiveAssignment) {
                $validator->errors()->add('rfid_tag_id', 'This RFID card already has an active assignment. Release it first.');
            }
        });
    }
}
