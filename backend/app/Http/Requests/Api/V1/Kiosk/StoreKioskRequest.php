<?php

namespace App\Http\Requests\Api\V1\Kiosk;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKioskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'device_id' => [
                'required',
                'string',
                'max:64',
                'regex:/^[A-Za-z0-9][A-Za-z0-9._:-]*$/',
                Rule::unique('devices', 'device_id'),
            ],
            'terminal_id' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('devices', 'terminal_id'),
            ],
            'destination_id' => [
                'required',
                'integer',
                Rule::exists('destinations', 'id')->where(fn ($q) => $q->where('is_active', true)),
            ],
            'name' => ['required', 'string', 'max:120'],
        ];
    }

    /**
     * @return array{device_id: string, destination_id: int, name: string, terminal_id?: string|null}
     */
    public function payload(): array
    {
        /** @var array{device_id: string, destination_id: int, name: string, terminal_id?: string|null} */
        return $this->safe()->only(['device_id', 'terminal_id', 'destination_id', 'name']);
    }
}
