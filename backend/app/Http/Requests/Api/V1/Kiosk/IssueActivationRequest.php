<?php

namespace App\Http\Requests\Api\V1\Kiosk;

use Illuminate\Foundation\Http\FormRequest;

class IssueActivationRequest extends FormRequest
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
            'rotate_secret' => ['sometimes', 'boolean'],
        ];
    }

    public function rotateSecret(): bool
    {
        return $this->boolean('rotate_secret', true);
    }
}
