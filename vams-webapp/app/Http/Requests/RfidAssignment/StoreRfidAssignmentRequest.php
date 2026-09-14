<?php

namespace App\Http\Requests\RfidAssignment;

use App\Models\RfidAssignment;
use Illuminate\Contracts\Validation\Rule as ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRfidAssignmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Route-level 'can:rfid_assignments.create' middleware already gates this.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Exactly one of vehicle_id / visitor_visit_id must be provided: a
     * sticker is assigned to a vehicle for its lifetime, a card is assigned
     * to a single visitor visit.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'rfid_tag_id' => ['required', Rule::exists('rfid_tags', 'id')],
            'vehicle_id' => ['nullable', 'required_without:visitor_visit_id', 'prohibits:visitor_visit_id', Rule::exists('vehicles', 'id')],
            'visitor_visit_id' => ['nullable', 'required_without:vehicle_id', 'prohibits:vehicle_id', Rule::exists('visitor_visits', 'id')],
            'assigned_at' => ['required', 'date'],
        ];
    }

    /**
     * Configure the validator instance.
     *
     * Rejects assigning a tag that already has an active (unreleased)
     * assignment — it must be released first.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('rfid_tag_id')) {
                return;
            }

            $hasActiveAssignment = RfidAssignment::query()
                ->where('rfid_tag_id', $this->input('rfid_tag_id'))
                ->whereNull('released_at')
                ->exists();

            if ($hasActiveAssignment) {
                $validator->errors()->add('rfid_tag_id', 'This RFID tag already has an active assignment. Release it first.');
            }
        });
    }
}
