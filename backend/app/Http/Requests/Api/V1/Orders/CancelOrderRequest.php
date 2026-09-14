<?php

namespace App\Http\Requests\Api\V1\Orders;

use App\Http\Requests\Concerns\RejectsClientMoneyFields;
use Illuminate\Foundation\Http\FormRequest;

class CancelOrderRequest extends FormRequest
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
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function reason(): ?string
    {
        $reason = $this->validated('reason') ?? null;

        return is_string($reason) && $reason !== '' ? $reason : null;
    }
}
