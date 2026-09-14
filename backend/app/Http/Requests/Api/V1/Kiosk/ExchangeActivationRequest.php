<?php

namespace App\Http\Requests\Api\V1\Kiosk;

use App\Http\Requests\Concerns\RejectsClientMoneyFields;
use Illuminate\Foundation\Http\FormRequest;

class ExchangeActivationRequest extends FormRequest
{
    use RejectsClientMoneyFields;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->rejectClientMoneyFields();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'string', 'max:64'],
            'activation_code' => ['required', 'string', 'max:64'],
            'software_version' => ['nullable', 'string', 'max:40'],
        ];
    }

    /**
     * @return array{device_id: string, activation_code: string, software_version?: string|null}
     */
    public function payload(): array
    {
        /** @var array{device_id: string, activation_code: string, software_version?: string|null} */
        return $this->safe()->only(['device_id', 'activation_code', 'software_version']);
    }
}
