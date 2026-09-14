<?php

namespace App\Http\Requests\Api\V1\Catalog;

use App\Models\Destination;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDestinationRequest extends FormRequest
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
        /** @var Destination $destination */
        $destination = $this->route('destination');

        return [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:40',
                'regex:/^[A-Za-z0-9][A-Za-z0-9_-]*$/',
                Rule::unique('destinations', 'code')->ignore($destination->id),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string'],
            'timezone' => ['sometimes', 'required', 'string', 'timezone:all', 'max:64'],
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
