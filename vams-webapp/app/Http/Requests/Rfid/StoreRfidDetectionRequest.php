<?php

namespace App\Http\Requests\Rfid;

use Illuminate\Contracts\Validation\Rule as ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRfidDetectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * The 'rfid.hmac' middleware already authenticates the device; there is
     * no user-based authorization concept on this machine-to-machine route.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Mirrors the device-service payload shape referenced by
     * context/SCHEMA.md §6 and the rfid_detections migration columns.
     *
     * EPC validation (FINDING #9): accepts standard hex-encoded EPC formats:
     * - EPC-96 (24 hex chars): most common UHF RFID format (e.g., E28069150000...)
     * - EPC-128/256 (32/64 hex chars): extended formats
     * - Allows uppercase/lowercase hex
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'event_uuid' => ['required', 'uuid', 'unique:rfid_detections,event_uuid'],
            // Hex-encoded EPC. Accept any reasonable format: pure hex (E280...) or
            // separated with hyphens/colons/spaces (UNKNOWN-EPC-1, E2:80:69:...).
            // Rejects SQL fragments, XSS payloads, and other injection attempts.
            // Pattern allows hex chars, letters in identifiers like "EPC", and common separators.
            'epc' => ['required', 'string', 'max:255', 'regex:/^[0-9A-Za-z]+(?:[\s:\-]*[0-9A-Za-z]+)*$/'],
            'rssi' => ['nullable', 'integer'],
            'antenna' => ['nullable', 'string', 'max:255'],
            'detected_at' => ['required', 'date'],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'epc.regex' => 'The EPC must be an alphanumeric identifier (letters, digits, optionally separated by hyphens, colons, or spaces).',
        ];
    }
}
