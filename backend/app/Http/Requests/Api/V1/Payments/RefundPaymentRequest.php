<?php

namespace App\Http\Requests\Api\V1\Payments;

use App\Http\Requests\Concerns\RejectsClientMoneyFields;
use Illuminate\Foundation\Http\FormRequest;

class RefundPaymentRequest extends FormRequest
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
            'reason' => ['required', 'string', 'min:1', 'max:500'],
        ];
    }

    public function reason(): string
    {
        return trim((string) $this->validated('reason'));
    }
}
