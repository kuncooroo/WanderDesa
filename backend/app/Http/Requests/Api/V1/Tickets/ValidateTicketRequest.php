<?php

namespace App\Http\Requests\Api\V1\Tickets;

use App\Http\Requests\Concerns\RejectsClientMoneyFields;
use App\Models\Destination;
use App\Models\Gate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidateTicketRequest extends FormRequest
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
            'qr_payload' => ['required', 'string', 'max:512'],
            'destination_id' => [
                'required',
                'integer',
                Rule::exists(Destination::class, 'id')->whereNull('deleted_at'),
            ],
            'gate_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists(Gate::class, 'id')->whereNull('deleted_at'),
            ],
        ];
    }

    public function qrPayload(): string
    {
        return (string) $this->validated('qr_payload');
    }

    public function destinationId(): int
    {
        return (int) $this->validated('destination_id');
    }

    public function gateId(): ?int
    {
        $gateId = $this->validated('gate_id') ?? null;

        return $gateId === null ? null : (int) $gateId;
    }
}
