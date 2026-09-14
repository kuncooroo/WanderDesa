<?php

namespace App\Http\Requests\Api\V1\Payments;

use App\Http\Requests\Concerns\RejectsClientMoneyFields;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmCashPaymentRequest extends FormRequest
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
            'received' => ['required', 'accepted'],
        ];
    }
}
