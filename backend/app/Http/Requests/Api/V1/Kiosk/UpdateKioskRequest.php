<?php

namespace App\Http\Requests\Api\V1\Kiosk;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKioskRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:120'],
            'destination_id' => [
                'sometimes',
                'integer',
                Rule::exists('destinations', 'id')->where(fn ($q) => $q->where('is_active', true)),
            ],
        ];
    }

    /**
     * @return array{name?: string, destination_id?: int}
     */
    public function payload(): array
    {
        /** @var array{name?: string, destination_id?: int} */
        return $this->safe()->only(['name', 'destination_id']);
    }
}
