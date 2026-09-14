<?php

namespace App\Http\Requests\Api\V1\Kiosk;

use App\Http\Requests\Concerns\RejectsClientMoneyFields;
use Illuminate\Foundation\Http\FormRequest;

class HeartbeatRequest extends FormRequest
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
            'software_version' => ['required', 'string', 'max:40'],
            'hardware_version' => ['nullable', 'string', 'max:40'],
            'printer_ok' => ['sometimes', 'boolean'],
            'extras' => ['sometimes', 'array'],
        ];
    }

    /**
     * @return array{software_version: string, hardware_version?: string|null, printer_ok?: bool|null, extras?: array<string, mixed>|null}
     */
    public function payload(): array
    {
        /** @var array{software_version: string, hardware_version?: string|null, printer_ok?: bool|null, extras?: array<string, mixed>|null} */
        return $this->safe()->only([
            'software_version',
            'hardware_version',
            'printer_ok',
            'extras',
        ]);
    }
}
