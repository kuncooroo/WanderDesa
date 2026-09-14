<?php

namespace App\Http\Requests\Api\V1\Payments;

use App\Enums\PaymentMethod;
use App\Http\Requests\Concerns\RejectsClientMoneyFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InitiatePaymentRequest extends FormRequest
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
            'method' => [
                'required',
                'string',
                Rule::in([...PaymentMethod::values(), 'digital']),
            ],
        ];
    }

    public function paymentMethod(): PaymentMethod
    {
        return PaymentMethod::fromClient((string) $this->validated('method'));
    }
}
