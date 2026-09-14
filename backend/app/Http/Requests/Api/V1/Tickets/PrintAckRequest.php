<?php

namespace App\Http\Requests\Api\V1\Tickets;

use App\Enums\PrintAttemptResult;
use App\Http\Requests\Concerns\RejectsClientMoneyFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrintAckRequest extends FormRequest
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
            'result' => ['required', 'string', Rule::enum(PrintAttemptResult::class)],
            'message' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function result(): PrintAttemptResult
    {
        return PrintAttemptResult::from((string) $this->validated('result'));
    }

    public function message(): ?string
    {
        $message = $this->validated('message') ?? null;

        if (! is_string($message) || trim($message) === '') {
            return null;
        }

        return $message;
    }
}
