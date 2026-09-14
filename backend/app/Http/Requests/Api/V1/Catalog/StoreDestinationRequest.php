<?php

namespace App\Http\Requests\Api\V1\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDestinationRequest extends FormRequest
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
            'code' => [
                'required',
                'string',
                'max:40',
                'regex:/^[A-Za-z0-9][A-Za-z0-9_-]*$/',
                Rule::unique('destinations', 'code'),
            ],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'timezone' => ['required', 'string', 'timezone:all', 'max:64'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function destinationPayload(): array
    {
        return $this->safe()->only([
            'code',
            'name',
            'description',
            'timezone',
            'is_active',
        ]);
    }
}
